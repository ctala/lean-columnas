<?php
/**
 * Main plugin orchestrator.
 *
 * Registers all WordPress hooks, initializes components, and wires
 * together the different subsystems of the plugin.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    /**
     * Initialize the plugin and register all hooks.
     */
    public function init(): void
    {
        $this->checkVersion();
        $this->loadTextDomain();
        $this->registerHooks();
    }

    /**
     * Check if the plugin version has changed and run upgrades if needed.
     */
    private function checkVersion(): void
    {
        $stored_version = get_option('lean_columnas_version', '0.0.0');

        if (version_compare($stored_version, LEAN_COLUMNAS_VERSION, '<')) {
            $installer = new Installer();
            $installer->activate();

            $roles = new Roles();
            $roles->register();

            update_option('lean_columnas_version', LEAN_COLUMNAS_VERSION);
        }
    }

    /**
     * Load plugin text domain for translations.
     */
    private function loadTextDomain(): void
    {
        add_action('init', function (): void {
            load_plugin_textdomain(
                'lean-columnas',
                false,
                dirname(LEAN_COLUMNAS_BASENAME) . '/languages'
            );
        }, 1);
    }

    /**
     * Register all WordPress hooks for plugin components.
     */
    private function registerHooks(): void
    {
        // Register CPT and taxonomies.
        $post_type = new PostType();
        add_action('init', [$post_type, 'register'], 5);

        // Template loading.
        $template_loader = new Templates\TemplateLoader();
        add_filter('template_include', [$template_loader, 'loadTemplate']);

        // Schema markup.
        $schema = new Schema\OpinionArticleSchema();
        add_action('wp_head', [$schema, 'output'], 5);

        // User profile fields.
        $user_profile = new UserProfile();
        $user_profile->register();

        // Admin pages and dashboard widgets.
        if (is_admin()) {
            $admin_page = new Admin\AdminPage();
            add_action('admin_menu', [$admin_page, 'registerMenus']);
            add_action('admin_enqueue_scripts', [$admin_page, 'enqueueAssets']);

            $dashboard_widget = new Admin\DashboardWidget();
            add_action('wp_dashboard_setup', [$dashboard_widget, 'register']);
        }

        // Frontend assets — only on our CPT pages.
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

        // Editorial workflow hooks.
        $workflow = new Editorial\WorkflowManager();
        add_action('transition_post_status', [$workflow, 'handleStatusTransition'], 10, 3);

        // Email notifications for editorial workflow.
        $notifier = new Editorial\EmailNotifier();
        $notifier->register();
    }

    /**
     * Enqueue frontend CSS on relevant pages.
     */
    public function enqueueFrontendAssets(): void
    {
        if (!is_singular('columna-opinion') && !is_post_type_archive('columna-opinion')) {
            return;
        }

        wp_enqueue_style(
            'lean-columnas-frontend',
            LEAN_COLUMNAS_URL . 'assets/css/frontend.css',
            [],
            LEAN_COLUMNAS_VERSION
        );
    }
}
