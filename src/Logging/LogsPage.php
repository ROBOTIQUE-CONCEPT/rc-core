<?php

declare(strict_types=1);

namespace WPRC\Core\Logging;

use WPRC\Core\Site\SiteContext;

defined('ABSPATH') || exit;

final class LogsPage
{
    public function __construct(
        private readonly LogRepository $repository,
        private readonly SiteContext $sites
    ) {
    }

    public function register(): void
    {
        if (is_multisite()) {
            add_submenu_page(
                'settings.php',
                __('Logs', 'rc-core'),
                __('Logs', 'rc-core'),
                $this->capability(),
                'rc-core-logs',
                [$this, 'render']
            );

            return;
        }

        add_management_page(
            __('Logs', 'rc-core'),
            __('Logs', 'rc-core'),
            $this->capability(),
            'rc-core-logs',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can($this->capability())) {
            wp_die(esc_html__('Vous n’avez pas l’autorisation d’accéder aux logs RC Core.', 'rc-core'));
        }

        $level = isset($_GET['level']) ? sanitize_key(wp_unslash((string) $_GET['level'])) : '';
        $channel = isset($_GET['channel']) ? sanitize_key(wp_unslash((string) $_GET['channel'])) : '';
        $siteId = is_multisite() && isset($_GET['site_id']) ? absint($_GET['site_id']) : 0;
        $ipAddress = isset($_GET['ip']) ? sanitize_text_field(wp_unslash((string) $_GET['ip'])) : '';
        $ipAddress = filter_var($ipAddress, FILTER_VALIDATE_IP) !== false ? $ipAddress : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $perPage = 50;

        $queryArgs = [
            'level' => $level,
            'channel' => $channel,
            'search' => $search,
            'ip_address' => $ipAddress,
            'page' => $page,
            'per_page' => $perPage,
        ];

        if (!is_multisite()) {
            $result = $this->repository->query($queryArgs);
        } elseif ($siteId > 0) {
            $result = $this->sites->onSite($siteId, fn (): array => $this->repository->query($queryArgs));
        } else {
            $result = $this->queryAllSites($queryArgs, $page, $perPage);
        }

        $pages = max(1, (int) ceil($result['total'] / $perPage));
        $channels = $this->channels();
        $networkSites = is_multisite() ? get_sites(['number' => 200]) : [];

        echo '<div class="wrap"><h1>' . esc_html__('Logs', 'rc-core') . '</h1>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="rc-core-logs">';
        echo '<select name="level"><option value="">' . esc_html__('Tous les niveaux', 'rc-core') . '</option>';
        foreach (['info' => 'Info', 'warning' => 'Avertissement', 'error' => 'Erreur'] as $value => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($level, $value, false), esc_html($label));
        }
        echo '</select> ';

        echo '<select name="channel"><option value="">' . esc_html__('Tous les canaux', 'rc-core') . '</option>';
        foreach ($channels as $value) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($channel, $value, false), esc_html($value));
        }
        echo '</select> ';

        if (is_multisite()) {
            echo '<select name="site_id"><option value="0">' . esc_html__('Tous les sites', 'rc-core') . '</option>';
            foreach ($networkSites as $site) {
                $details = get_blog_details((int) $site->blog_id);
                $label = $details ? $details->blogname . ' (#' . $site->blog_id . ')' : '#' . $site->blog_id;
                printf(
                    '<option value="%d"%s>%s</option>',
                    (int) $site->blog_id,
                    selected($siteId, (int) $site->blog_id, false),
                    esc_html($label)
                );
            }
            echo '</select> ';
        }

        printf(
            '<input type="search" name="ip" value="%s" placeholder="%s" style="width:150px"> ',
            esc_attr($ipAddress),
            esc_attr__('Adresse IP', 'rc-core')
        );

        printf(
            '<input type="search" name="s" value="%s" placeholder="%s"> ',
            esc_attr($search),
            esc_attr__('Rechercher dans les logs', 'rc-core')
        );
        submit_button(__('Filtrer', 'rc-core'), 'secondary', '', false);
        echo '</form>';

        $headings = ['UTC', 'Niveau', 'Canal', 'Événement'];
        if (is_multisite()) {
            $headings[] = 'Site';
        }
        $headings = array_merge($headings, ['Utilisateur', 'IP', 'Requête', 'Message / Contexte']);

        echo '<table class="widefat striped" style="margin-top:12px"><thead><tr>';
        foreach ($headings as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ($result['items'] === []) {
            printf(
                '<tr><td colspan="%d">%s</td></tr>',
                count($headings),
                esc_html__('Aucun log trouvé.', 'rc-core')
            );
        }

        foreach ($result['items'] as $row) {
            $context = '';
            if (!empty($row['context_payload'])) {
                $decoded = json_decode((string) $row['context_payload'], true);
                $context = is_array($decoded)
                    ? wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : (string) $row['context_payload'];
            }

            echo '<tr>';
            printf('<td><code>%s</code></td>', esc_html((string) $row['created_at']));
            printf('<td><strong>%s</strong></td>', esc_html(strtoupper((string) $row['level'])));
            printf('<td><code>%s</code></td>', esc_html((string) $row['channel']));
            printf('<td><code>%s</code></td>', esc_html((string) ($row['event'] ?? '')));
            if (is_multisite()) {
                printf('<td>%d</td>', (int) $row['site_id']);
            }
            printf('<td>%d</td>', (int) $row['user_id']);
            $rowIp = (string) ($row['ip_address'] ?? '');
            if ($rowIp !== '') {
                $ipUrl = add_query_arg(['page' => 'rc-core-logs', 'ip' => $rowIp], $this->adminUrl());
                printf('<td><a href="%s"><code>%s</code></a></td>', esc_url($ipUrl), esc_html($rowIp));
            } else {
                echo '<td>—</td>';
            }
            printf('<td><code>%s</code></td>', esc_html((string) $row['request_id']));
            echo '<td><div>' . esc_html((string) $row['message']) . '</div>';
            if ($context !== '') {
                echo '<details><summary>' . esc_html__('Contexte', 'rc-core') . '</summary><pre style="white-space:pre-wrap;max-width:900px">' . esc_html($context) . '</pre></details>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        $base = add_query_arg(
            array_filter([
                'page' => 'rc-core-logs',
                'level' => $level,
                'channel' => $channel,
                'site_id' => $siteId ?: null,
                'ip' => $ipAddress ?: null,
                's' => $search,
            ]),
            $this->adminUrl()
        );

        echo '<div class="tablenav"><div class="tablenav-pages">';
        $pagination = paginate_links([
            'base' => add_query_arg('paged', '%#%', $base),
            'format' => '',
            'current' => $page,
            'total' => $pages,
        ]);

        if (is_string($pagination) && $pagination !== '') {
            echo wp_kses_post($pagination);
        }
        echo '</div></div></div>';
    }

    /**
     * @param array<string,mixed> $args
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    private function queryAllSites(array $args, int $page, int $perPage): array
    {
        $fetchPerSite = min(1000, max($perPage, $page * $perPage));
        $items = [];
        $total = 0;

        foreach ($this->sites->siteIds() as $id) {
            $siteResult = $this->sites->onSite($id, function () use ($args, $fetchPerSite): array {
                $siteArgs = $args;
                $siteArgs['page'] = 1;
                $siteArgs['per_page'] = $fetchPerSite;

                return $this->repository->query($siteArgs);
            });

            $total += (int) ($siteResult['total'] ?? 0);
            if (!empty($siteResult['items']) && is_array($siteResult['items'])) {
                $items = array_merge($items, $siteResult['items']);
            }
        }

        usort($items, static function (array $left, array $right): int {
            $dateCompare = strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total' => $total,
        ];
    }

    /** @return array<int,string> */
    private function channels(): array
    {
        if (!is_multisite()) {
            return $this->repository->channels();
        }

        $channels = [];
        foreach ($this->sites->siteIds() as $id) {
            $values = $this->sites->onSite($id, fn (): array => $this->repository->channels());
            $channels = array_merge($channels, $values);
        }

        $channels = array_values(array_unique(array_filter(array_map('strval', $channels))));
        sort($channels, SORT_NATURAL | SORT_FLAG_CASE);

        return $channels;
    }

    private function capability(): string
    {
        return is_multisite() ? 'manage_network_options' : 'manage_options';
    }

    private function adminUrl(): string
    {
        return is_multisite() ? network_admin_url('settings.php') : admin_url('tools.php');
    }
}
