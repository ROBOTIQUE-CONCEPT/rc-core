<?php

declare(strict_types=1);

namespace WPRC\Core\UI;

use WPRC\Core\Auth\AccessPolicy;
use WPRC\Core\Site\SiteContext;

defined('ABSPATH') || exit;

/**
 * Generic wp-admin adapter for module pages registered on the Admin surface.
 * Existing legacy module menus may coexist while they are migrated.
 */
final class AdminSurface
{
    public function __construct(
        private readonly UiRegistry $ui,
        private readonly SiteContext $sites
    ) {
    }

    public function init(): void
    {
        if (!$this->sites->isApplicationSite()) {
            return;
        }
        add_action('admin_menu', [$this, 'registerMenus'], 30);
    }

    public function registerMenus(): void
    {
        $pages = $this->ui->all()[Surface::ADMIN] ?? [];
        if ($pages === []) {
            return;
        }

        add_menu_page(
            __('Robotique Concept', 'rc-core'),
            __('RC', 'rc-core'),
            AccessPolicy::CAP_BACKEND,
            'rc-platform',
            [$this, 'renderIndex'],
            'dashicons-admin-generic',
            3
        );

        foreach ($pages as $page) {
            $slug = $this->slug($page);
            $parent = $page->navigation ? 'rc-platform' : null;
            add_submenu_page(
                $parent,
                $page->label,
                $page->label,
                $page->capability,
                $slug,
                fn (): mixed => $this->renderPage($page)
            );
        }
    }

    public function renderIndex(): void
    {
        if (!current_user_can(AccessPolicy::CAP_BACKEND)) {
            wp_die(esc_html__('Accès refusé.', 'rc-core'));
        }

        echo '<div class="wrap"><h1>' . esc_html__('Robotique Concept', 'rc-core') . '</h1><p>'
            . esc_html__('Sélectionnez un module dans le menu.', 'rc-core')
            . '</p></div>';
    }

    private function renderPage(Page $page): void
    {
        if (!current_user_can($page->capability)) {
            wp_die(esc_html__('Accès refusé.', 'rc-core'));
        }

        foreach ($page->styles as $handle) {
            wp_enqueue_style($handle);
        }
        foreach ($page->scripts as $handle) {
            wp_enqueue_script($handle);
        }

        $context = new PageContext(wp_get_current_user(), Surface::ADMIN, $page->route, '');
        echo '<div class="wrap"><h1>' . esc_html($page->label) . '</h1>' . $page->render($context) . '</div>';
    }

    private function slug(Page $page): string
    {
        return 'rc-' . str_replace('/', '-', $page->route);
    }
}
