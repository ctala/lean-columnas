<?php
/**
 * Plugin Name: Lean Columnas
 * Plugin URI: https://github.com/ctala/lean-columnas
 * Description: Opinion columns management with Columnist and Agency roles, editorial workflow, and quality gates.
 * Version: 0.2.6
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

define('LEAN_COLUMNAS_VERSION', '0.2.6');
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

    // Restore users who were demoted during a previous deactivation.
    lean_columnas_restore_users();

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'lean_columnas_activate');

/**
 * Plugin deactivation hook.
 *
 * Demotes columnists and agencies to subscriber and stores their
 * original role in user_meta so it can be restored on reactivation.
 * Does NOT delete users or remove their content.
 */
function lean_columnas_deactivate(): void
{
    $plugin_roles = ['lc_columnista', 'lc_agencia'];

    foreach ($plugin_roles as $role_slug) {
        $users = get_users(['role' => $role_slug]);
        foreach ($users as $user) {
            // Store original role for restoration.
            update_user_meta($user->ID, '_lc_deactivated_role', $role_slug);
            // Demote to subscriber (keeps the account active but limited).
            $user->set_role('subscriber');
        }
    }

    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'lean_columnas_deactivate');

/**
 * Restore users who were demoted during plugin deactivation.
 *
 * Checks for the _lc_deactivated_role meta and reassigns the original role.
 */
function lean_columnas_restore_users(): void
{
    $demoted = get_users([
        'meta_key'   => '_lc_deactivated_role',
        'meta_compare' => 'EXISTS',
    ]);

    foreach ($demoted as $user) {
        $original_role = get_user_meta($user->ID, '_lc_deactivated_role', true);
        if (in_array($original_role, ['lc_columnista', 'lc_agencia'], true)) {
            $user->set_role($original_role);
        }
        delete_user_meta($user->ID, '_lc_deactivated_role');
    }
}

/**
 * Initialize the plugin on plugins_loaded.
 *
 * This ensures WordPress core and all dependencies are available.
 */
add_action('plugins_loaded', function (): void {
    $plugin = new \LeanColumnas\Plugin();
    $plugin->init();
}, 10);
