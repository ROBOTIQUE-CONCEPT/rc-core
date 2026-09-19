<?php

declare(strict_types=1);

namespace WPRC\Core;

use WPRC\Core\Admin\AdminPage;
use WPRC\Core\Admin\ManufacturersPage;
use WPRC\Core\Auth\AccessPolicy;
use WPRC\Core\Auth\PersonaResolver;
use WPRC\Core\Cache\WordPressCache;
use WPRC\Core\Content\PlaceholderEngine;
use WPRC\Core\Content\PlaceholderRenderer;
use WPRC\Core\Connectors\Axonaut\AxonautClient;
use WPRC\Core\Connectors\Axonaut\Providers\AddressProvider as AxonautAddressProvider;
use WPRC\Core\Connectors\Axonaut\Providers\CompanyProvider as AxonautCompanyProvider;
use WPRC\Core\Connectors\Axonaut\Providers\EmployeeProvider as AxonautEmployeeProvider;
use WPRC\Core\Connectors\Axonaut\Providers\OpportunityProvider as AxonautOpportunityProvider;
use WPRC\Core\Connectors\Axonaut\Providers\ProductProvider as AxonautProductProvider;
use WPRC\Core\Connectors\Axonaut\Providers\QuotationProvider as AxonautQuotationProvider;
use WPRC\Core\Connectors\ConnectorCredentials;
use WPRC\Core\Connectors\ConnectorsIntegration;
use WPRC\Core\Connectors\Mailjet\MailjetClient;
use WPRC\Core\Connectors\Mailjet\MailjetContactsClient;
use WPRC\Core\Connectors\Turnstile\TurnstileClient;
use WPRC\Core\Contracts\CacheInterface;
use WPRC\Core\Contracts\ERP\AddressProviderInterface;
use WPRC\Core\Contracts\ERP\CompanyProviderInterface;
use WPRC\Core\Contracts\ERP\EmployeeProviderInterface;
use WPRC\Core\Contracts\ERP\OpportunityProviderInterface;
use WPRC\Core\Contracts\ERP\ProductProviderInterface;
use WPRC\Core\Contracts\ERP\QuotationProviderInterface;
use WPRC\Core\Contracts\LoggerInterface;
use WPRC\Core\ERP\Cache\CacheLock;
use WPRC\Core\ERP\Cache\ErpCacheKeyFactory;
use WPRC\Core\ERP\Cache\RequestCache;
use WPRC\Core\ERP\ErpTelemetry;
use WPRC\Core\InternalApi\Authenticator as InternalApiAuthenticator;
use WPRC\Core\InternalApi\Client as InternalApiClient;
use WPRC\Core\InternalApi\InternalApiSecret;
use WPRC\Core\InternalApi\ReplayGuard;
use WPRC\Core\InternalApi\RequestSigner;
use WPRC\Core\Runtime\RequestContext;
use WPRC\Core\Reference\ManufacturerRepository;
use WPRC\Core\Security\Capabilities\CapabilityRegistry;
use WPRC\Core\Security\Capabilities\PermissionsPage;
use WPRC\Core\Security\Capabilities\RoleManager;
use WPRC\Core\Security\Capabilities\RolePolicy;
use WPRC\Core\Security\Capabilities\RoleRegistry;
use WPRC\Core\Database\Installer;
use WPRC\Core\Database\TableNames;
use WPRC\Core\ERP\ProviderRegistry;
use WPRC\Core\Logging\ContextSanitizer;
use WPRC\Core\Logging\DatabaseLogger;
use WPRC\Core\Logging\LogRepository;
use WPRC\Core\Logging\LogsPage;
use WPRC\Core\Logging\RetentionScheduler;
use WPRC\Core\Logging\SecurityLogRateLimiter;
use WPRC\Core\Logging\WordPressSecurityEvents;
use WPRC\Core\Security\RequestIpResolver;
use WPRC\Core\Security\Turnstile\TurnstileIntegration;
use WPRC\Core\Security\Turnstile\TurnstilePage;
use WPRC\Core\Security\Turnstile\TurnstileRenderer;
use WPRC\Core\Security\Turnstile\TurnstileSettings;
use WPRC\Core\Security\Turnstile\TurnstileVerifier;
use WPRC\Core\Settings\Settings;
use WPRC\Core\Site\SiteContext;
use WPRC\Core\Support\UidGenerator;
use WPRC\Core\Translation\PolylangAdapter;
use WPRC\Core\Translation\TranslationRegistry;
use WPRC\Core\Translation\TranslationService;
use WPRC\Core\UI\AdminSurface;
use WPRC\Core\UI\UiRegistry;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?self $instance = null;
    private Container $container;
    private bool $booted = false;

    private function __construct()
    {
        $this->container = new Container();
        $this->registerServices();
        $this->registerErpProviders();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function activate(bool $networkWide = false): void
    {
        $plugin = self::instance();

        if (is_multisite() && $networkWide && function_exists('get_sites')) {
            foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
                switch_to_blog((int) $siteId);
                $plugin->installer()->install();
                restore_current_blog();
            }
        } else {
            $plugin->installer()->install();
        }

        $plugin->logger()->info(
            'RC Core activated.',
            'core',
            ['network_wide' => $networkWide, 'version' => RC_CORE_VERSION],
            'activated'
        );
    }

    public static function deactivate(bool $networkWide = false): void
    {
        self::instance()->container->get(RetentionScheduler::class)->unscheduleOnPrimarySite();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $this->installer()->maybeUpgrade();

        if (function_exists('wp_cache_add_global_groups')) {
            wp_cache_add_global_groups([
                'wprc_core',
                'wprc_security',
                'wprc_axonaut',
                'wprc_mailjet',
                'wprc_turnstile',
                'wprc_erp',
                'wprc_erp_locks',
                'wprc_internal_api',
            ]);
        }

        $this->container->get(ConnectorsIntegration::class)->init();
        $this->container->get(TurnstileIntegration::class)->init();
        $this->container->get(RetentionScheduler::class)->init();
        $this->container->get(WordPressSecurityEvents::class)->init();
        $this->container->get(PlaceholderRenderer::class)->init();
        $this->container->get(TranslationService::class)->init();
        $this->container->get(AdminSurface::class)->init();

        if ($this->sites()->isApplicationSite()) {
            add_action('admin_menu', [$this->container->get(ManufacturersPage::class), 'register']);
            add_action('admin_init', [$this->container->get(ManufacturersPage::class), 'handle']);
        }

        if (is_multisite()) {
            add_action('wp_initialize_site', [$this, 'installNewSite'], 100, 1);
            add_action('network_admin_menu', [$this->container->get(AdminPage::class), 'registerMenu']);
            add_action('network_admin_menu', [$this->container->get(TurnstilePage::class), 'registerMenu']);
            add_action('network_admin_menu', [$this->container->get(LogsPage::class), 'register']);
            add_action('network_admin_menu', [$this->container->get(PermissionsPage::class), 'registerMenu']);
        } else {
            add_action('admin_menu', [$this->container->get(AdminPage::class), 'registerMenu']);
            add_action('admin_menu', [$this->container->get(TurnstilePage::class), 'registerMenu']);
            add_action('admin_menu', [$this->container->get(LogsPage::class), 'register']);
        }

        add_action('admin_init', [$this->container->get(AdminPage::class), 'handleSave']);
        add_action('admin_init', [$this->container->get(TurnstilePage::class), 'handleSave']);
        add_action('admin_init', [$this->container->get(PermissionsPage::class), 'handleSave']);

        // Core owns the platform access capabilities used by Portal and the
        // Network Admin role policy. Business modules add their own caps later.
        $this->capabilities()->register('core', AccessPolicy::capabilities());

        $this->container->get(RolePolicy::class)->registerRoles($this->roles());
        if ($this->sites()->isApplicationSite()) {
            $this->container->get(RoleManager::class)->apply();
        }

        do_action('wprc/core/ready', $this);
    }

    public function installNewSite(\WP_Site $site): void
    {
        $this->container->get(SiteContext::class)->onSite((int) $site->blog_id, function (): void {
            $this->installer()->install();
        });
    }

    public function container(): Container { return $this->container; }
    public function services(): ServiceRegistry { return $this->container->get(ServiceRegistry::class); }
    public function capabilities(): CapabilityRegistry { return $this->container->get(CapabilityRegistry::class); }
    public function roles(): RoleRegistry { return $this->container->get(RoleRegistry::class); }
    public function access(): AccessPolicy { return $this->container->get(AccessPolicy::class); }
    public function personas(): PersonaResolver { return $this->container->get(PersonaResolver::class); }
    public function ui(): UiRegistry { return $this->container->get(UiRegistry::class); }
    public function requestContext(): RequestContext { return $this->container->get(RequestContext::class); }
    public function erpTelemetry(): ErpTelemetry { return $this->container->get(ErpTelemetry::class); }
    public function internalApi(): InternalApiClient { return $this->container->get(InternalApiClient::class); }
    public function internalApiAuthenticator(): InternalApiAuthenticator { return $this->container->get(InternalApiAuthenticator::class); }
    public function erp(): ProviderRegistry { return $this->container->get(ProviderRegistry::class); }
    public function cache(): CacheInterface { return $this->container->get(CacheInterface::class); }
    public function uid(): UidGenerator { return $this->container->get(UidGenerator::class); }
    public function logger(): LoggerInterface { return $this->container->get(LoggerInterface::class); }
    public function placeholders(): PlaceholderEngine { return $this->container->get(PlaceholderEngine::class); }
    public function translations(): TranslationService { return $this->container->get(TranslationService::class); }
    public function tables(): TableNames { return $this->container->get(TableNames::class); }
    public function settings(): Settings { return $this->container->get(Settings::class); }
    public function sites(): SiteContext { return $this->container->get(SiteContext::class); }
    public function credentials(): ConnectorCredentials { return $this->container->get(ConnectorCredentials::class); }
    public function mailjet(): MailjetClient { return $this->container->get(MailjetClient::class); }
    public function mailjetContacts(): MailjetContactsClient { return $this->container->get(MailjetContactsClient::class); }
    public function turnstile(): TurnstileClient { return $this->container->get(TurnstileClient::class); }
    public function turnstileSettings(): TurnstileSettings { return $this->container->get(TurnstileSettings::class); }
    public function turnstileRenderer(): TurnstileRenderer { return $this->container->get(TurnstileRenderer::class); }
    public function turnstileVerifier(): TurnstileVerifier { return $this->container->get(TurnstileVerifier::class); }
    public function manufacturers(): ManufacturerRepository { return $this->container->get(ManufacturerRepository::class); }
    public function installer(): Installer { return $this->container->get(Installer::class); }

    private function registerServices(): void
    {
        $this->container->singleton(UidGenerator::class, static fn (): UidGenerator => new UidGenerator());
        $this->container->singleton(Settings::class, static fn (): Settings => new Settings());
        $this->container->singleton(SiteContext::class, static fn (Container $c): SiteContext => new SiteContext($c->get(Settings::class)));
        $this->container->singleton(TableNames::class, static fn (): TableNames => new TableNames());
        $this->container->singleton(ManufacturerRepository::class, static fn (Container $c): ManufacturerRepository => new ManufacturerRepository($c->get(TableNames::class), $c->get(UidGenerator::class)));
        $this->container->singleton(ServiceRegistry::class, static fn (): ServiceRegistry => new ServiceRegistry());
        $this->container->singleton(CapabilityRegistry::class, static fn (Container $c): CapabilityRegistry => new CapabilityRegistry($c->get(Settings::class)));
        $this->container->singleton(RoleRegistry::class, static fn (): RoleRegistry => new RoleRegistry());
        $this->container->singleton(RolePolicy::class, static fn (Container $c): RolePolicy => new RolePolicy($c->get(Settings::class), $c->get(CapabilityRegistry::class)));
        $this->container->singleton(RoleManager::class, static fn (Container $c): RoleManager => new RoleManager($c->get(RoleRegistry::class), $c->get(SiteContext::class)));
        $this->container->singleton(AccessPolicy::class, static fn (): AccessPolicy => new AccessPolicy());
        $this->container->singleton(PersonaResolver::class, static fn (Container $c): PersonaResolver => new PersonaResolver($c->get(AccessPolicy::class)));
        $this->container->singleton(UiRegistry::class, static fn (): UiRegistry => new UiRegistry());
        $this->container->singleton(AdminSurface::class, static fn (Container $c): AdminSurface => new AdminSurface($c->get(UiRegistry::class), $c->get(SiteContext::class)));
        $this->container->singleton(PermissionsPage::class, static fn (Container $c): PermissionsPage => new PermissionsPage($c->get(CapabilityRegistry::class), $c->get(RolePolicy::class), $c->get(RoleManager::class), $c->get(SiteContext::class), $c->get(LoggerInterface::class)));
        $this->container->singleton(RequestContext::class, static fn (): RequestContext => new RequestContext());
        $this->container->singleton(ProviderRegistry::class, static fn (): ProviderRegistry => new ProviderRegistry('axonaut'));
        $this->container->singleton(CacheInterface::class, static fn (): CacheInterface => new WordPressCache());
        $this->container->singleton(RequestCache::class, static fn (): RequestCache => new RequestCache());
        $this->container->singleton(ErpCacheKeyFactory::class, static fn (): ErpCacheKeyFactory => new ErpCacheKeyFactory());
        $this->container->singleton(CacheLock::class, static fn (): CacheLock => new CacheLock());
        $this->container->singleton(ErpTelemetry::class, static fn (): ErpTelemetry => new ErpTelemetry());
        $this->container->singleton(PlaceholderEngine::class, static fn (): PlaceholderEngine => new PlaceholderEngine());
        $this->container->singleton(PlaceholderRenderer::class, static fn (Container $c): PlaceholderRenderer => new PlaceholderRenderer($c->get(PlaceholderEngine::class)));
        $this->container->singleton(TranslationRegistry::class, static fn (): TranslationRegistry => new TranslationRegistry());
        $this->container->singleton(PolylangAdapter::class, static fn (): PolylangAdapter => new PolylangAdapter());
        $this->container->singleton(TranslationService::class, static fn (Container $c): TranslationService => new TranslationService(
            $c->get(TranslationRegistry::class),
            $c->get(PolylangAdapter::class),
            $c->get(PlaceholderEngine::class)
        ));
        $this->container->singleton(ContextSanitizer::class, static fn (): ContextSanitizer => new ContextSanitizer());
        $this->container->singleton(RequestIpResolver::class, static fn (): RequestIpResolver => new RequestIpResolver());
        $this->container->singleton(SecurityLogRateLimiter::class, static fn (Container $c): SecurityLogRateLimiter => new SecurityLogRateLimiter($c->get(RequestIpResolver::class)));
        $this->container->singleton(LogRepository::class, static fn (Container $c): LogRepository => new LogRepository($c->get(TableNames::class)));
        $this->container->singleton(LoggerInterface::class, static fn (Container $c): LoggerInterface => new DatabaseLogger($c->get(LogRepository::class), $c->get(ContextSanitizer::class), $c->get(Settings::class), $c->get(RequestIpResolver::class), $c->get(UidGenerator::class)));
        $this->container->singleton(WordPressSecurityEvents::class, static fn (Container $c): WordPressSecurityEvents => new WordPressSecurityEvents($c->get(LoggerInterface::class), $c->get(Settings::class), $c->get(SecurityLogRateLimiter::class)));
        $this->container->singleton(RetentionScheduler::class, static fn (Container $c): RetentionScheduler => new RetentionScheduler($c->get(LogRepository::class), $c->get(Settings::class), $c->get(SiteContext::class)));
        $this->container->singleton(Installer::class, static fn (Container $c): Installer => new Installer($c->get(TableNames::class), $c->get(RetentionScheduler::class), $c->get(SiteContext::class)));

        $this->container->singleton(ConnectorCredentials::class, static fn (Container $c): ConnectorCredentials => new ConnectorCredentials($c->get(SiteContext::class)));
        $this->container->singleton(ConnectorsIntegration::class, static fn (Container $c): ConnectorsIntegration => new ConnectorsIntegration($c->get(SiteContext::class)));
        $this->container->singleton(AxonautClient::class, static fn (Container $c): AxonautClient => new AxonautClient(
            $c->get(ConnectorCredentials::class)->axonautApiKey(),
            $c->get(Settings::class)->axonautApiUrl(),
            $c->get(LoggerInterface::class),
            $c->get(CacheInterface::class),
            $c->get(RequestCache::class),
            $c->get(ErpCacheKeyFactory::class),
            $c->get(CacheLock::class),
            $c->get(ErpTelemetry::class)
        ));
        $this->container->singleton(MailjetClient::class, static fn (Container $c): MailjetClient => new MailjetClient($c->get(ConnectorCredentials::class), $c->get(LoggerInterface::class)));
        $this->container->singleton(MailjetContactsClient::class, static fn (Container $c): MailjetContactsClient => new MailjetContactsClient($c->get(ConnectorCredentials::class), $c->get(LoggerInterface::class)));
        $this->container->singleton(TurnstileClient::class, static fn (Container $c): TurnstileClient => new TurnstileClient($c->get(ConnectorCredentials::class), $c->get(LoggerInterface::class), $c->get(SecurityLogRateLimiter::class)));
        $this->container->singleton(TurnstileSettings::class, static fn (Container $c): TurnstileSettings => new TurnstileSettings($c->get(Settings::class), $c->get(TurnstileClient::class)));
        $this->container->singleton(TurnstileRenderer::class, static fn (Container $c): TurnstileRenderer => new TurnstileRenderer($c->get(TurnstileSettings::class)));
        $this->container->singleton(TurnstileVerifier::class, static fn (Container $c): TurnstileVerifier => new TurnstileVerifier(
            $c->get(TurnstileSettings::class),
            $c->get(TurnstileClient::class),
            $c->get(RequestIpResolver::class),
            $c->get(SecurityLogRateLimiter::class),
            $c->get(LoggerInterface::class)
        ));
        $this->container->singleton(TurnstileIntegration::class, static fn (Container $c): TurnstileIntegration => new TurnstileIntegration(
            $c->get(TurnstileSettings::class),
            $c->get(TurnstileRenderer::class),
            $c->get(TurnstileVerifier::class),
            $c->get(SiteContext::class)
        ));
        $this->container->singleton(TurnstilePage::class, static fn (Container $c): TurnstilePage => new TurnstilePage($c->get(TurnstileSettings::class), $c->get(LoggerInterface::class), $c->get(SiteContext::class)));

        $this->container->singleton(InternalApiSecret::class, static fn (Container $c): InternalApiSecret => new InternalApiSecret($c->get(Settings::class)));
        $this->container->singleton(RequestSigner::class, static fn (Container $c): RequestSigner => new RequestSigner($c->get(InternalApiSecret::class)));
        $this->container->singleton(ReplayGuard::class, static fn (): ReplayGuard => new ReplayGuard());
        $this->container->singleton(InternalApiAuthenticator::class, static fn (Container $c): InternalApiAuthenticator => new InternalApiAuthenticator($c->get(RequestSigner::class), $c->get(ReplayGuard::class)));
        $this->container->singleton(InternalApiClient::class, static fn (Container $c): InternalApiClient => new InternalApiClient($c->get(RequestSigner::class)));

        $this->container->singleton(AxonautProductProvider::class, static fn (Container $c): AxonautProductProvider => new AxonautProductProvider($c->get(AxonautClient::class)));
        $this->container->singleton(AxonautCompanyProvider::class, static fn (Container $c): AxonautCompanyProvider => new AxonautCompanyProvider($c->get(AxonautClient::class)));
        $this->container->singleton(AxonautEmployeeProvider::class, static fn (Container $c): AxonautEmployeeProvider => new AxonautEmployeeProvider($c->get(AxonautClient::class)));
        $this->container->singleton(AxonautAddressProvider::class, static fn (Container $c): AxonautAddressProvider => new AxonautAddressProvider($c->get(AxonautClient::class)));
        $this->container->singleton(AxonautOpportunityProvider::class, static fn (Container $c): AxonautOpportunityProvider => new AxonautOpportunityProvider($c->get(AxonautClient::class)));
        $this->container->singleton(AxonautQuotationProvider::class, static fn (Container $c): AxonautQuotationProvider => new AxonautQuotationProvider($c->get(AxonautClient::class)));

        $this->container->singleton(AdminPage::class, static fn (Container $c): AdminPage => new AdminPage($c->get(Settings::class), $c->get(ConnectorCredentials::class), $c->get(LoggerInterface::class)));
        $this->container->singleton(ManufacturersPage::class, static fn (Container $c): ManufacturersPage => new ManufacturersPage($c->get(ManufacturerRepository::class), $c->get(SiteContext::class)));
        $this->container->singleton(LogsPage::class, static fn (Container $c): LogsPage => new LogsPage($c->get(LogRepository::class), $c->get(SiteContext::class)));
    }

    private function registerErpProviders(): void
    {
        $erp = $this->container->get(ProviderRegistry::class);
        $source = 'axonaut';
        $erp->register($source, ProductProviderInterface::class, $this->container->get(AxonautProductProvider::class));
        $erp->register($source, CompanyProviderInterface::class, $this->container->get(AxonautCompanyProvider::class));
        $erp->register($source, EmployeeProviderInterface::class, $this->container->get(AxonautEmployeeProvider::class));
        $erp->register($source, AddressProviderInterface::class, $this->container->get(AxonautAddressProvider::class));
        $erp->register($source, OpportunityProviderInterface::class, $this->container->get(AxonautOpportunityProvider::class));
        $erp->register($source, QuotationProviderInterface::class, $this->container->get(AxonautQuotationProvider::class));
    }
}
