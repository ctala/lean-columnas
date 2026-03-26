<?php
/**
 * Plugin Name: Lean Columnas
 * Plugin URI: https://github.com/ctala/lean-columnas
 * Description: Opinion columns management with Columnist and Agency roles, editorial workflow, and quality gates.
 * Version: 0.2.2
 * Author: Cristian Tala
 * Author URI: https://github.com/ctala
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lean-columnas
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('LEAN_COLUMNAS_VERSION', '0.2.2');
define('LEAN_COLUMNAS_PATH', plugin_dir_path(__FILE__));
define('LEAN_COLUMNAS_URL', plugin_dir_url(__FILE__));
define('LEAN_COLUMNAS_BASENAME', plugin_basename(__FILE__));
define('LEAN_COLUMNAS_FILE', __FILE__);

/**
 * Autoloader for plugin classes.
 *
 * Maps the LeanColumnas namespace to the src/ directory.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'LeanColumnas\\';
    $base_dir = LEAN_COLUMNAS_PATH . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Plugin activation hook.
 *
 * Runs the installer to create tables and register roles.
 * Must be registered in the main file, not inside a class.
 */
function lean_columnas_activate(): void
{
    require_once LEAN_COLUMNAS_PATH . 'src/Installer.php';
    require_once LEAN_COLUMNAS_PATH . 'src/Roles.php';

    $installer = new \LeanColumnas\Installer();
    $installer->activate();

    $roles = new \LeanColumnas\Roles();
    $roles->register();

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'lean_columnas_activate');

/**
 * Plugin deactivation hook.
 *
 * Cleans up scheduled tasks and flushes rewrite rules.
 * Does NOT remove tables or roles (that happens on uninstall).
 */
function lean_columnas_deactivate(): void
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'lean_columnas_deactivate');

/**
 * Initialize the plugin on plugins_loaded.
 *
 * This ensures WordPress core and all dependencies are available.
 */
add_action('plugins_loaded', function (): void {
    $plugin = new \LeanColumnas\Plugin();
    $plugin->init();
}, 10);
