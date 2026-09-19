<?php
/**
 * Plugin Name: RC Core
 * Plugin URI: https://www.robotiqueconcept.com/
 * Description: Socle technique partagé, connecteurs, cache, journalisation et contrats pour les extensions Robotique Concept.
 * Version: 0.6.0-alpha12
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Author: Robotique Concept
 * Author URI: https://www.robotiqueconcept.com/
 * Text Domain: rc-core
 * Network: true
 * License: GPL-2.0-or-later
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('RC_CORE_VERSION', '0.6.0-alpha12');
define('RC_CORE_FILE', __FILE__);
define('RC_CORE_PATH', plugin_dir_path(__FILE__));
define('RC_CORE_URL', plugin_dir_url(__FILE__));

require_once RC_CORE_PATH . 'src/Autoloader.php';
\WPRC\Core\Autoloader::register();

require_once RC_CORE_PATH . 'src/functions.php';

\WPRC\Core\Integration\PrivatePluginMetadata::register();

register_activation_hook(__FILE__, [\WPRC\Core\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [\WPRC\Core\Plugin::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    \WPRC\Core\Plugin::instance()->boot();
}, 1);
