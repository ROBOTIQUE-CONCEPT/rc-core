<?php

declare(strict_types=1);

namespace WPRC\Core\Admin;

use WPRC\Core\Connectors\ConnectorCredentials;
use WPRC\Core\Contracts\LoggerInterface;
use WPRC\Core\Settings\Settings;

defined('ABSPATH') || exit;

final class AdminPage
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ConnectorCredentials $credentials,
        private readonly LoggerInterface $logger
    ) {
    }

    public function registerMenu(): void
    {
        if (is_multisite()) {
            add_submenu_page(
                'settings.php',
                __('Réglages RConcept', 'rc-core'),
                __('Réglages RConcept', 'rc-core'),
                $this->capability(),
                'rc-core',
                [$this, 'render']
            );

            return;
        }

        add_options_page(
            __('Réglages RConcept', 'rc-core'),
            __('Réglages RConcept', 'rc-core'),
            $this->capability(),
            'rc-core',
            [$this, 'render']
        );
    }

    public function handleSave(): void
    {
        $action = isset($_POST['rc_core_action'])
            ? sanitize_key((string) wp_unslash($_POST['rc_core_action']))
            : '';

        if ($action !== 'save_settings' || !$this->canManage()) {
            return;
        }

        if (is_multisite() && !is_network_admin()) {
            return;
        }

        check_admin_referer('rc_core_save_settings');

        $settings = $this->settings->all();

        if (is_multisite()) {
            $settings['public_site_id'] = absint($_POST['public_site_id'] ?? 0);
            $settings['application_site_id'] = absint($_POST['application_site_id'] ?? 0);
            $settings['connector_site_id'] = absint($_POST['connector_site_id'] ?? 0);
        } else {
            unset($settings['public_site_id'], $settings['application_site_id'], $settings['connector_site_id']);
        }

        $settings['logging_enabled'] = isset($_POST['logging_enabled']);
        $settings['wordpress_security_logging_enabled'] = isset($_POST['wordpress_security_logging_enabled']);
        $settings['log_retention_days'] = max(1, min(365, absint($_POST['log_retention_days'] ?? 30)));
        $settings['log_max_rows'] = max(1000, min(500000, absint($_POST['log_max_rows'] ?? 50000)));
        $settings['axonaut_api_url'] = esc_url_raw(trim((string) wp_unslash($_POST['axonaut_api_url'] ?? '')));

        $this->settings->replace($settings);

        $this->logger->info(
            'RC Core settings updated.',
            'core',
            ['multisite' => is_multisite()],
            'settings_updated'
        );

        wp_safe_redirect(add_query_arg(
            ['page' => 'rc-core', 'updated' => '1'],
            $this->adminUrl()
        ));
        exit;
    }

    public function render(): void
    {
        if (!$this->canManage()) {
            wp_die(esc_html__('Vous n’avez pas l’autorisation de gérer RC Core.', 'rc-core'));
        }

        $sites = is_multisite() ? get_sites(['number' => 200]) : [];

        echo '<div class="wrap rc-core-settings"><h1>' . esc_html__('Réglages RConcept', 'rc-core') . '</h1>';

        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Réglages enregistrés.', 'rc-core') . '</p></div>';
        }

        echo '<style>
            .rc-core-settings-grid {
                display: grid;
                grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
                gap: 24px;
                align-items: start;
                max-width: 1280px;
            }
            .rc-core-settings-panel {
                min-width: 0;
            }
            .rc-core-settings-status {
                margin-top: 0;
            }
            .rc-core-settings-status th {
                width: 48%;
            }
            @media screen and (max-width: 960px) {
                .rc-core-settings-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>';

        echo '<div class="rc-core-settings-grid">';

        echo '<section class="rc-core-settings-panel">';
        echo '<h2>' . esc_html__('Réglages', 'rc-core') . '</h2><form method="post">';
        wp_nonce_field('rc_core_save_settings');
        echo '<input type="hidden" name="rc_core_action" value="save_settings"><table class="form-table"><tbody>';

        if (is_multisite()) {
            $this->siteSelectRow('Site public (www)', 'public_site_id', $this->settings->publicSiteId(), $sites);
            $this->siteSelectRow('Site métier (my)', 'application_site_id', $this->settings->applicationSiteId(), $sites);
            $this->siteSelectRow('Site des connecteurs', 'connector_site_id', $this->settings->connectorSiteId(), $sites);
        }

        $this->checkboxRow(
            'Activer les logs',
            'logging_enabled',
            $this->settings->loggingEnabled(),
            'Active ou désactive toutes les écritures du logger RC Core.'
        );
        $this->checkboxRow(
            'Événements de sécurité WordPress',
            'wordpress_security_logging_enabled',
            $this->settings->wordpressSecurityLoggingEnabled(),
            'Connexion, comptes utilisateurs, rôles, mots de passe et activation ou désactivation des extensions.'
        );
        $this->inputRow('Durée de conservation des logs (jours)', 'log_retention_days', (string) $this->settings->logRetentionDays(), 'number');
        $this->inputRow('Nombre maximal de logs conservés', 'log_max_rows', (string) $this->settings->logMaxRows(), 'number');
        $this->inputRow('URL de l’API Axonaut', 'axonaut_api_url', $this->settings->axonautApiUrl(), 'url');
        echo '</tbody></table>';
        submit_button(__('Enregistrer les modifications', 'rc-core'));
        echo '</form></section>';

        echo '<aside class="rc-core-settings-panel">';
        echo '<h2>' . esc_html__('Statut', 'rc-core') . '</h2><table class="widefat striped rc-core-settings-status"><tbody>';
        $this->statusRow('Version', RC_CORE_VERSION);
        $this->statusRow('Mode WordPress', is_multisite() ? 'Multisite' : 'Site unique');
        $this->statusRow('Cache objet externe', wp_using_ext_object_cache() ? 'Oui' : 'Non');
        $this->statusRow('Axonaut', $this->credentials->axonautConfigured() ? 'Configuré' : 'Non configuré');
        $this->statusRow('Mailjet', $this->credentials->mailjetConfigured() ? 'Configuré' : 'Non configuré');
        $this->statusRow('Cloudflare Turnstile', $this->credentials->turnstileConfigured() ? 'Configuré' : 'Non configuré');
        $this->statusRow('Logs', $this->settings->loggingEnabled() ? 'Activés' : 'Désactivés');
        $this->statusRow('Événements de sécurité WordPress', $this->settings->wordpressSecurityLoggingEnabled() ? 'Activés' : 'Désactivés');
        echo '</tbody></table></aside>';

        echo '</div></div>';
    }

    private function canManage(): bool
    {
        return current_user_can($this->capability());
    }

    private function capability(): string
    {
        return is_multisite() ? 'manage_network_options' : 'manage_options';
    }

    private function adminUrl(): string
    {
        return is_multisite() ? network_admin_url('settings.php') : admin_url('options-general.php');
    }

    private function statusRow(string $label, string $value): void
    {
        printf(
            '<tr><th>%s</th><td>%s</td></tr>',
            esc_html($label),
            esc_html($value)
        );
    }

    /** @param array<int,object> $sites */
    private function siteSelectRow(string $label, string $name, int $selected, array $sites): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td><select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">';

        foreach ($sites as $site) {
            $id = (int) $site->blog_id;
            $details = get_blog_details($id);
            $text = $details ? $details->blogname . ' — ' . $details->siteurl : '#' . $id;
            printf(
                '<option value="%d"%s>%s</option>',
                $id,
                selected($selected, $id, false),
                esc_html($text)
            );
        }

        echo '</select></td></tr>';
    }

    private function checkboxRow(string $label, string $name, bool $checked, string $description = ''): void
    {
        printf(
            '<tr><th scope="row">%1$s</th><td><label><input name="%2$s" type="checkbox" value="1"%3$s> %4$s</label></td></tr>',
            esc_html($label),
            esc_attr($name),
            checked($checked, true, false),
            esc_html($description)
        );
    }

    private function inputRow(string $label, string $name, string $value, string $type): void
    {
        printf(
            '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input class="regular-text" id="%1$s" name="%1$s" type="%3$s" value="%4$s"></td></tr>',
            esc_attr($name),
            esc_html($label),
            esc_attr($type),
            esc_attr($value)
        );
    }
}
