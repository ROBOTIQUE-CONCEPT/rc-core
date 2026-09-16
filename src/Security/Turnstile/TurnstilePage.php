<?php

declare(strict_types=1);

namespace WPRC\Core\Security\Turnstile;

use WPRC\Core\Contracts\LoggerInterface;
use WPRC\Core\Site\SiteContext;

defined('ABSPATH') || exit;

/**
 * Standalone Turnstile runtime settings page under WordPress Settings.
 */
final class TurnstilePage
{
    public function __construct(
        private readonly TurnstileSettings $settings,
        private readonly LoggerInterface $logger,
        private readonly SiteContext $sites
    ) {
    }

    public function registerMenu(): void
    {
        if (is_multisite()) {
            add_submenu_page(
                'settings.php',
                __('Turnstile', 'rc-core'),
                __('Turnstile', 'rc-core'),
                $this->capability(),
                'rc-core-turnstile',
                [$this, 'render']
            );
            return;
        }

        add_options_page(
            __('Turnstile', 'rc-core'),
            __('Turnstile', 'rc-core'),
            $this->capability(),
            'rc-core-turnstile',
            [$this, 'render']
        );
    }

    public function handleSave(): void
    {
        $action = isset($_POST['rc_core_action'])
            ? sanitize_key((string) wp_unslash($_POST['rc_core_action']))
            : '';

        if ($action !== 'save_turnstile_settings' || !$this->canManage()) {
            return;
        }

        if (is_multisite() && !is_network_admin()) {
            return;
        }

        check_admin_referer('rc_core_save_turnstile_settings');

        $this->settings->replace([
            'enabled' => isset($_POST['enabled']),
            'enable_native_forms' => isset($_POST['enable_native_forms']),
            'enable_login' => isset($_POST['enable_login']),
            'enable_registration' => isset($_POST['enable_registration']),
            'enable_lost_password' => isset($_POST['enable_lost_password']),
            'enable_comments' => isset($_POST['enable_comments']),
            'enable_portal_login' => isset($_POST['enable_portal_login']),
            'enable_woo_login' => isset($_POST['enable_woo_login']),
            'enable_woo_registration' => isset($_POST['enable_woo_registration']),
            'skip_logged_in_comments' => isset($_POST['skip_logged_in_comments']),
            'theme' => sanitize_key((string) wp_unslash($_POST['theme'] ?? 'auto')),
            'size' => sanitize_key((string) wp_unslash($_POST['size'] ?? 'normal')),
            'failure_mode' => sanitize_key((string) wp_unslash($_POST['failure_mode'] ?? 'closed')),
        ]);

        $this->logger->info('Turnstile settings updated.', 'core', [], 'turnstile_settings_updated');

        wp_safe_redirect(add_query_arg(
            ['page' => 'rc-core-turnstile', 'updated' => '1'],
            $this->adminUrl()
        ));
        exit;
    }

    public function render(): void
    {
        if (!$this->canManage()) {
            wp_die(esc_html__('Vous n’avez pas l’autorisation de gérer Turnstile.', 'rc-core'));
        }

        $credentials = rc_core()->turnstile()->credentials();
        $configured = $this->settings->isConfigured();

        echo '<div class="wrap"><h1>' . esc_html__('Turnstile', 'rc-core') . '</h1>';

        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Réglages enregistrés.', 'rc-core') . '</p></div>';
        }

        echo '<form method="post">';
        wp_nonce_field('rc_core_save_turnstile_settings');
        echo '<input type="hidden" name="rc_core_action" value="save_turnstile_settings">';
        echo '<table class="form-table" role="presentation"><tbody>';

        $this->statusRow(
            __('Identifiants Cloudflare', 'rc-core'),
            $configured ? __('Configurés dans Connecteurs', 'rc-core') : __('Non configurés', 'rc-core')
        );
        $this->checkboxRow('Activer Turnstile', 'enabled', $this->settings->enabled(), 'Active la protection Turnstile pour les contextes cochés ci-dessous.');
        $this->checkboxRow('Formulaires publics RC', 'enable_native_forms', $this->settings->enabledForNativeForms(), 'Protège les formulaires publics RC rendus sur le site vitrine.');
        $this->checkboxRow('Connexion WordPress', 'enable_login', $this->settings->enabledForLogin(), 'Protège wp-login.php.');
        $this->checkboxRow('Inscription WordPress', 'enable_registration', $this->settings->enabledForRegistration(), 'Protège le formulaire d’inscription natif.');
        $this->checkboxRow('Mot de passe oublié', 'enable_lost_password', $this->settings->enabledForLostPassword(), 'Protège la demande de réinitialisation de mot de passe.');
        $this->checkboxRow('Commentaires WordPress', 'enable_comments', $this->settings->enabledForComments(), 'Protège les formulaires de commentaires natifs.');
        $this->checkboxRow('Connexion RC Portal (my)', 'enable_portal_login', $this->settings->enabledForPortalLogin(), 'Protège le formulaire de connexion frontal RC Portal sur le site métier.');
        $this->checkboxRow('Connexion WooCommerce (my)', 'enable_woo_login', $this->settings->enabledForWooLogin(), 'Protège la connexion WooCommerce My Account sur le site métier.');
        $this->checkboxRow('Inscription WooCommerce (my)', 'enable_woo_registration', $this->settings->enabledForWooRegistration(), 'Protège l’inscription WooCommerce My Account sur le site métier.');
        $this->checkboxRow('Ignorer les utilisateurs connectés', 'skip_logged_in_comments', $this->settings->skipLoggedInComments(), 'Ne demande pas Turnstile aux utilisateurs connectés lorsqu’ils commentent.');
        $this->selectRow('Thème du widget', 'theme', $this->settings->theme(), [
            'auto' => 'Automatique',
            'light' => 'Clair',
            'dark' => 'Sombre',
        ]);
        $this->selectRow('Taille du widget', 'size', $this->settings->size(), [
            'normal' => 'Normale',
            'flexible' => 'Flexible',
            'compact' => 'Compacte',
        ]);
        $this->selectRow('En cas d’erreur API', 'failure_mode', $this->settings->failureMode(), [
            'closed' => 'Bloquer la soumission',
            'open' => 'Laisser passer et journaliser',
        ]);

        echo '</tbody></table>';
        submit_button(__('Enregistrer les modifications', 'rc-core'));
        echo '</form>';

        if (!$configured) {
            $connectorsUrl = is_multisite()
                ? get_admin_url($this->sites->connectorSiteId(), 'options-connectors.php')
                : admin_url('options-connectors.php');
            echo '<p><a class="button button-secondary" href="' . esc_url($connectorsUrl) . '">' . esc_html__('Configurer les identifiants dans Connecteurs', 'rc-core') . '</a></p>';
        }

        // Keep values hidden; only expose the credential source for diagnostics.
        if (!empty($credentials['source'])) {
            $sources = [
                'environment' => __('Environnement serveur', 'rc-core'),
                'constant' => __('Constante RC Core', 'rc-core'),
                'database' => __('Connecteurs WordPress', 'rc-core'),
                'legacy_constants' => __('Constantes historiques', 'rc-core'),
                'none' => __('Aucune', 'rc-core'),
            ];
            $source = $sources[(string) $credentials['source']] ?? __('Inconnue', 'rc-core');
            echo '<p class="description">' . esc_html(sprintf(__('Source des identifiants : %s', 'rc-core'), $source)) . '</p>';
        }

        echo '</div>';
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
            '<tr><th scope="row">%s</th><td><strong>%s</strong></td></tr>',
            esc_html($label),
            esc_html($value)
        );
    }

    private function checkboxRow(string $label, string $name, bool $checked, string $description): void
    {
        printf(
            '<tr><th scope="row">%1$s</th><td><label><input name="%2$s" type="checkbox" value="1"%3$s> %4$s</label></td></tr>',
            esc_html($label),
            esc_attr($name),
            checked($checked, true, false),
            esc_html($description)
        );
    }

    /** @param array<string,string> $options */
    private function selectRow(string $label, string $name, string $value, array $options): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td><select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">';
        foreach ($options as $optionValue => $optionLabel) {
            printf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr($optionValue),
                selected($value, $optionValue, false),
                esc_html($optionLabel)
            );
        }
        echo '</select></td></tr>';
    }
}
