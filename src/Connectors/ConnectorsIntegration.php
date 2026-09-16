<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors;

use WPRC\Core\Site\SiteContext;
use WP_Connector_Registry;

defined('ABSPATH') || exit;

final class ConnectorsIntegration
{
    public function __construct(private readonly SiteContext $sites)
    {
    }

    public function init(): void
    {
        add_action('wp_connectors_init', [$this, 'register']);
    }

    public function register(WP_Connector_Registry $registry): void
    {
        if (!$this->sites->isConnectorSite()) {
            return;
        }

        $registry->register('wprc-axonaut', [
            'name' => __('Axonaut', 'rc-core'),
            'description' => __('Fournisseur des données CRM et métier Robotique Concept.', 'rc-core'),
            'type' => 'crm',
            'authentication' => [
                'method' => 'api_key',
                'setting_name' => ConnectorCredentials::AXONAUT_SETTING,
                'constant_name' => 'RC_CORE_AXONAUT_API_KEY',
                'env_var_name' => 'RC_CORE_AXONAUT_API_KEY',
            ],
        ]);


        $registry->register('wprc-mailjet', [
            'name' => __('Mailjet', 'rc-core'),
            'description' => __('Utilisez la clé API Mailjet comme identifiant et la clé secrète comme mot de passe.', 'rc-core'),
            'type' => 'email_marketing',
            'authentication' => [
                'method' => 'application_password',
                'setting_name' => ConnectorCredentials::MAILJET_SETTING,
                'constant_name' => 'RC_CORE_MAILJET_CREDENTIALS',
                'env_var_name' => 'RC_CORE_MAILJET_CREDENTIALS',
            ],
        ]);

        $registry->register('rc-turnstile', [
            'name' => __('Cloudflare Turnstile', 'rc-core'),
            'description' => __('Utilisez la clé de site Turnstile comme identifiant et la clé secrète comme mot de passe.', 'rc-core'),
            'type' => 'security',
            'authentication' => [
                'method' => 'application_password',
                'setting_name' => ConnectorCredentials::TURNSTILE_SETTING,
                'constant_name' => 'RC_CORE_TURNSTILE_CREDENTIALS',
                'env_var_name' => 'RC_CORE_TURNSTILE_CREDENTIALS',
            ],
        ]);

    }
}
