<?php

declare(strict_types=1);

namespace WPRC\Core\Admin;

use WPRC\Core\Reference\ManufacturerRepository;
use WPRC\Core\Site\SiteContext;

defined('ABSPATH') || exit;

final class ManufacturersPage
{
    public function __construct(private readonly ManufacturerRepository $manufacturers, private readonly SiteContext $sites) {}

    public function register(): void
    {
        if (!$this->sites->isApplicationSite()) return;
        add_management_page(__('Fabricants RC', 'rc-core'), __('Fabricants RC', 'rc-core'), 'manage_options', 'rc-core-manufacturers', [$this, 'render']);
    }

    public function handle(): void
    {
        if (!$this->sites->isApplicationSite() || !is_admin() || !current_user_can('manage_options')) return;
        if (($_POST['rc_core_action'] ?? '') !== 'save_manufacturer') return;
        check_admin_referer('rc_core_save_manufacturer');
        try {
            $this->manufacturers->save(
                isset($_POST['manufacturer_id']) ? (int)$_POST['manufacturer_id'] : null,
                (string)wp_unslash($_POST['name'] ?? ''),
                (string)wp_unslash($_POST['slug'] ?? ''),
                (string)wp_unslash($_POST['status'] ?? 'active')
            );
            wp_safe_redirect(add_query_arg(['page'=>'rc-core-manufacturers','updated'=>'1'], admin_url('tools.php'))); exit;
        } catch (\Throwable $e) {
            wp_safe_redirect(add_query_arg(['page'=>'rc-core-manufacturers','error'=>rawurlencode($e->getMessage())], admin_url('tools.php'))); exit;
        }
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Accès refusé.', 'rc-core'));
        $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
        $edit = $editId > 0 ? $this->manufacturers->find($editId) : null;
        echo '<div class="wrap"><h1>' . esc_html__('Fabricants RC', 'rc-core') . '</h1>';
        if (isset($_GET['updated'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Fabricant enregistré.', 'rc-core') . '</p></div>';
        if (isset($_GET['error'])) echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['error']))) . '</p></div>';
        echo '<p>' . esc_html__('Référentiel transversal utilisé par les modules Assets et Produits.', 'rc-core') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Nom', 'rc-core') . '</th><th>Slug</th><th>' . esc_html__('Statut', 'rc-core') . '</th><th></th></tr></thead><tbody>';
        foreach ($this->manufacturers->all() as $manufacturer) {
            $url = add_query_arg(['page'=>'rc-core-manufacturers','edit'=>$manufacturer->id], admin_url('tools.php'));
            echo '<tr><td><strong>' . esc_html($manufacturer->name) . '</strong></td><td><code>' . esc_html($manufacturer->slug) . '</code></td><td>' . esc_html($manufacturer->status) . '</td><td><a href="' . esc_url($url) . '">' . esc_html__('Modifier', 'rc-core') . '</a></td></tr>';
        }
        echo '</tbody></table><hr><h2>' . esc_html($edit ? __('Modifier le fabricant', 'rc-core') : __('Ajouter un fabricant', 'rc-core')) . '</h2><form method="post">';
        wp_nonce_field('rc_core_save_manufacturer');
        echo '<input type="hidden" name="rc_core_action" value="save_manufacturer"><input type="hidden" name="manufacturer_id" value="' . esc_attr((string)($edit?->id ?? 0)) . '">';
        echo '<table class="form-table"><tr><th><label for="rc-mfr-name">' . esc_html__('Nom', 'rc-core') . '</label></th><td><input class="regular-text" id="rc-mfr-name" name="name" required value="' . esc_attr($edit?->name ?? '') . '"></td></tr>';
        echo '<tr><th><label for="rc-mfr-slug">Slug</label></th><td><input class="regular-text" id="rc-mfr-slug" name="slug" value="' . esc_attr($edit?->slug ?? '') . '"></td></tr>';
        echo '<tr><th><label for="rc-mfr-status">' . esc_html__('Statut', 'rc-core') . '</label></th><td><select id="rc-mfr-status" name="status"><option value="active"' . selected($edit?->status ?? 'active','active',false) . '>' . esc_html__('Actif', 'rc-core') . '</option><option value="archived"' . selected($edit?->status ?? '','archived',false) . '>' . esc_html__('Archivé', 'rc-core') . '</option></select></td></tr></table>';
        submit_button($edit ? __('Enregistrer', 'rc-core') : __('Ajouter', 'rc-core'));
        echo '</form></div>';
    }
}
