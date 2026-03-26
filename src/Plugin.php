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

            // Customize dashboard for columnists (remove clutter, add news).
            add_action('wp_dashboard_setup', [$this, 'customizeColumnistDashboard'], 999);
        }

        // Use classic editor for columna-opinion (Gutenberg doesn't support
        // custom statuses or admin_notices for quality gate feedback).
        add_filter('use_block_editor_for_post_type', [$this, 'disableBlockEditor'], 10, 2);

        // Include columna-opinion in author archive queries.
        add_action('pre_get_posts', [$this, 'includeColumnsInAuthorArchive']);

        // Frontend assets — only on our CPT pages.
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

        // Show columnists in the author dropdown for our CPT.
        add_filter('wp_dropdown_users_args', [$this, 'filterAuthorDropdown'], 10, 2);

        // Enforce quality gates before save (intercept pending → lc_submitted, block bad publishes).
        add_filter('wp_insert_post_data', [$this, 'enforceQualityGatesOnSave'], 10, 2);

        // Show quality gate failure/warning notices in admin.
        add_action('admin_notices', [$this, 'showQualityGateNotices']);

        // Fix misleading "Post published" message after quality gate intervention.
        add_filter('redirect_post_location', [$this, 'fixPostRedirectMessage'], 10, 2);

        // Custom Spanish messages for columna-opinion post actions.
        add_filter('post_updated_messages', [$this, 'customPostMessages']);

        // Translate "Submit for Review" button to Spanish for our CPT.
        add_filter('gettext', [$this, 'translateSubmitButton'], 10, 3);

        // Hide category metabox from columnists (only editors/admins assign categories).
        add_action('add_meta_boxes', [$this, 'removeColumnistMetaboxes'], 99);

        // Restrict columnist admin menus to only their CPT.
        add_action('admin_menu', [$this, 'restrictColumnistaMenus'], 999);

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

    /**
     * Disable the block editor for columna-opinion.
     *
     * The classic editor supports admin_notices for quality gate feedback
     * and handles custom post statuses properly in the status dropdown.
     *
     * @param bool   $use_block_editor Whether to use the block editor.
     * @param string $post_type        The post type.
     * @return bool
     */
    public function disableBlockEditor(bool $use_block_editor, string $post_type): bool
    {
        if ($post_type === PostType::SLUG) {
            return false;
        }

        return $use_block_editor;
    }

    /**
     * Include columna-opinion in author archive queries.
     *
     * WordPress author archives only show the default 'post' type.
     * We add our CPT so columnist pages show their columns.
     */
    public function includeColumnsInAuthorArchive(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if (!$query->is_author()) {
            return;
        }

        $query->set('post_type', ['post', PostType::SLUG]);
    }

    /**
     * Include columnists in the author dropdown for columna-opinion posts.
     *
     * WordPress only shows users who can edit_others_posts by default.
     * We need columnists to appear when editing columna-opinion posts.
     *
     * @param array<string, mixed> $query_args WP_User_Query arguments.
     * @param array<string, mixed> $parsed_args The parsed arguments.
     * @return array<string, mixed>
     */
    public function filterAuthorDropdown(array $query_args, array $parsed_args): array
    {
        global $post;

        // Only modify on our CPT edit screen.
        if (!is_admin()) {
            return $query_args;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_our_cpt = ($screen && $screen->post_type === PostType::SLUG);

        if (!$is_our_cpt && (!$post || $post->post_type !== PostType::SLUG)) {
            return $query_args;
        }

        // Include columnists and agencies in addition to default users.
        $query_args['role__in'] = ['administrator', 'editor', 'author', 'lc_columnista', 'lc_agencia'];
        // Remove the who=authors restriction that filters by edit_others_posts.
        unset($query_args['who']);

        return $query_args;
    }

    /**
     * Enforce quality gates before the post is saved to the database.
     *
     * Intercepts WordPress "Submit for Review" (pending) and redirects to
     * lc_submitted if gates pass, or reverts to draft if they fail.
     * Also blocks direct publish attempts that don't pass quality gates.
     *
     * @param array<string, mixed> $data    Slashed post data.
     * @param array<string, mixed> $postarr Raw post array including ID.
     * @return array<string, mixed>
     */
    public function enforceQualityGatesOnSave(array $data, array $postarr): array
    {
        if (($data['post_type'] ?? '') !== PostType::SLUG) {
            return $data;
        }

        $new_status = $data['post_status'] ?? 'draft';
        $post_id = (int) ($postarr['ID'] ?? 0);

        // Only intercept pending (Submit for Review) and publish.
        if ($new_status !== 'pending' && $new_status !== 'publish') {
            return $data;
        }

        // For publish: allow if coming from lc_approved (editor workflow).
        if ($new_status === 'publish' && $post_id > 0) {
            $old_status = get_post_status($post_id);
            if ($old_status === 'lc_approved') {
                return $data;
            }
        }

        // Build a temporary post object for validation (data is slashed by WP).
        $temp = new \stdClass();
        $temp->ID = $post_id;
        $temp->post_title = wp_unslash($data['post_title'] ?? '');
        $temp->post_content = wp_unslash($data['post_content'] ?? '');
        $temp->post_excerpt = wp_unslash($data['post_excerpt'] ?? '');
        $fake_post = new \WP_Post($temp);

        $gates = new Editorial\QualityGates();
        $result = $gates->validate($fake_post);

        $user_id = get_current_user_id();

        if (!$result['passed']) {
            // Quality gates failed — revert to draft and store failures.
            $data['post_status'] = 'draft';
            set_transient('lc_qg_failures_' . $user_id, $result['failures'], 120);
            if (!empty($result['warnings'])) {
                set_transient('lc_qg_warnings_' . $user_id, $result['warnings'], 120);
            }
        } else {
            // Quality gates passed — clear any leftover failure transient.
            delete_transient('lc_qg_failures_' . $user_id);
            if ($new_status === 'pending') {
                // Redirect pending → lc_submitted (our editorial workflow).
                $data['post_status'] = 'lc_submitted';
            }
            if (!empty($result['warnings'])) {
                set_transient('lc_qg_warnings_' . $user_id, $result['warnings'], 120);
            } else {
                delete_transient('lc_qg_warnings_' . $user_id);
            }
        }

        return $data;
    }

    /**
     * Show admin notices when quality gates block or warn.
     *
     * Uses user-based transients so notices survive the post-save redirect.
     */
    public function showQualityGateNotices(): void
    {
        global $post;

        if (!$post || $post->post_type !== PostType::SLUG) {
            return;
        }

        $user_id = get_current_user_id();

        $failures = get_transient('lc_qg_failures_' . $user_id);
        if (is_array($failures) && !empty($failures)) {
            delete_transient('lc_qg_failures_' . $user_id);
            echo '<div class="notice notice-error"><p><strong>';
            echo esc_html__('La columna no cumple los requisitos para enviarla a revision:', 'lean-columnas');
            echo '</strong></p><ul>';
            foreach ($failures as $failure) {
                echo '<li>' . esc_html($failure) . '</li>';
            }
            echo '</ul></div>';
        }

        $warnings = get_transient('lc_qg_warnings_' . $user_id);
        if (is_array($warnings) && !empty($warnings)) {
            delete_transient('lc_qg_warnings_' . $user_id);
            echo '<div class="notice notice-warning"><p><strong>';
            echo esc_html__('Recomendaciones para mejorar la columna:', 'lean-columnas');
            echo '</strong></p><ul>';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    /**
     * Fix the redirect message after saving a columna-opinion.
     *
     * WordPress shows "Post published" (message=6) even when our quality gates
     * changed the status to draft or lc_submitted. This filter corrects the
     * message parameter in the redirect URL.
     *
     * @param string $location The redirect URL.
     * @param int    $post_id  The post ID.
     * @return string
     */
    public function fixPostRedirectMessage(string $location, int $post_id): string
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== PostType::SLUG) {
            return $location;
        }

        $status = $post->post_status;

        // If quality gates reverted to draft, show "Draft updated" (message=10).
        if ($status === 'draft' && get_transient('lc_qg_failures_' . get_current_user_id())) {
            return add_query_arg('message', 10, remove_query_arg('message', $location));
        }

        // If status is lc_submitted, show "Post submitted" (message=8).
        if ($status === 'lc_submitted') {
            return add_query_arg('message', 8, remove_query_arg('message', $location));
        }

        return $location;
    }

    /**
     * Restrict columnist admin menus to only relevant items.
     *
     * Columnists get edit_posts to work with the CPT, but we don't want
     * them seeing the regular Posts menu or other post types.
     */
    public function restrictColumnistaMenus(): void
    {
        $user = wp_get_current_user();
        if (!in_array('lc_columnista', $user->roles, true) && !in_array('lc_agencia', $user->roles, true)) {
            return;
        }

        // Whitelist approach: remove everything except what columnists need.
        global $menu;
        $allowed = [
            'index.php',           // Dashboard
            'upload.php',          // Media
            'lean-columnas',       // Our custom Columnas menu
            'profile.php',         // Profile
            'separator1',          // Separator
            'separator2',          // Separator
            'separator-last',      // Separator
        ];
        foreach ($menu as $key => $item) {
            if (!in_array($item[2] ?? '', $allowed, true)) {
                remove_menu_page($item[2]);
            }
        }
    }

    /**
     * Customize the dashboard for columnists.
     *
     * Remove default widgets and keep only what's useful:
     * our "Mis Columnas" widget and a Quick Draft for columns.
     */
    public function customizeColumnistDashboard(): void
    {
        $user = wp_get_current_user();
        if (!in_array('lc_columnista', $user->roles, true) && !in_array('lc_agencia', $user->roles, true)) {
            return;
        }

        // Remove default dashboard widgets.
        remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_site_health', 'dashboard', 'normal');

        // Add site news widget (our own content).
        wp_add_dashboard_widget(
            'lc_noticias_sitio',
            __('Ultimas Noticias del Sitio', 'lean-columnas'),
            [$this, 'renderSiteNewsWidget']
        );

        // Add external news widget for inspiration.
        wp_add_dashboard_widget(
            'lc_noticias_inspiracion',
            __('Noticias para Inspiracion', 'lean-columnas'),
            [$this, 'renderExternalNewsWidget']
        );

        // Force 3-column layout: one widget per column.
        $user_id = $user->ID;
        update_user_meta($user_id, 'screen_layout_dashboard', 3);
        $order = [
            'normal'   => 'lc_mis_columnas',
            'column3'  => 'lc_noticias_sitio',
            'side'     => 'lc_noticias_inspiracion',
            'column4'  => '',
        ];
        update_user_meta($user_id, 'meta-box-order_dashboard', $order);
    }

    /**
     * Render latest posts from our own site.
     */
    public function renderSiteNewsWidget(): void
    {
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 8,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        if (empty($posts)) {
            echo '<p>' . esc_html__('No hay noticias recientes.', 'lean-columnas') . '</p>';
            return;
        }

        echo '<ul style="margin:0;padding:0;list-style:none;">';
        foreach ($posts as $p) {
            $title = esc_html($p->post_title);
            $link = esc_url(get_permalink($p->ID));
            $date = get_the_date('j M', $p);
            $category = '';
            $cats = get_the_category($p->ID);
            if (!empty($cats)) {
                $category = $cats[0]->name;
            }
            echo '<li style="padding:6px 0;border-bottom:1px solid #f0f0f1;font-size:13px;">';
            echo '<a href="' . $link . '" target="_blank" rel="noopener">' . $title . '</a>';
            echo ' <span style="color:#646970;font-size:12px;">';
            if ($category !== '') {
                echo esc_html($category) . ' — ';
            }
            echo esc_html($date);
            echo '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Render external news feed for columnist inspiration.
     */
    public function renderExternalNewsWidget(): void
    {
        $rss_url = 'https://news.google.com/rss?hl=es-419&gl=CL&ceid=CL:es-419';

        $rss = fetch_feed($rss_url);
        if (is_wp_error($rss)) {
            echo '<p>' . esc_html__('No se pudieron cargar las noticias externas.', 'lean-columnas') . '</p>';
            return;
        }

        $items = $rss->get_items(0, 8);
        if (empty($items)) {
            echo '<p>' . esc_html__('No hay noticias disponibles.', 'lean-columnas') . '</p>';
            return;
        }

        echo '<ul style="margin:0;padding:0;list-style:none;">';
        foreach ($items as $item) {
            $title = esc_html($item->get_title());
            $link = esc_url($item->get_permalink());
            $date = $item->get_date('j M');
            echo '<li style="padding:6px 0;border-bottom:1px solid #f0f0f1;font-size:13px;">';
            echo '<a href="' . $link . '" target="_blank" rel="noopener">' . $title . '</a>';
            echo ' <span style="color:#646970;font-size:12px;">— ' . esc_html($date) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<p style="margin-top:10px;font-size:12px;color:#646970;">';
        echo esc_html__('Fuente: Google News Chile', 'lean-columnas');
        echo '</p>';
    }

    /**
     * Custom Spanish messages for columna-opinion post actions.
     *
     * WordPress default messages are in English (or the site locale).
     * We provide explicit Spanish messages for all columna-opinion actions.
     *
     * @param array<string, array<int, string>> $messages Messages per post type.
     * @return array<string, array<int, string>>
     */
    public function customPostMessages(array $messages): array
    {
        global $post;

        if (!$post || $post->post_type !== PostType::SLUG) {
            return $messages;
        }

        $permalink = get_permalink($post->ID);
        $preview_url = get_preview_post_link($post);

        $messages[PostType::SLUG] = [
            0  => '', // Unused.
            1  => sprintf(
                __('Columna actualizada. <a href="%s">Ver columna</a>', 'lean-columnas'),
                esc_url($permalink)
            ),
            2  => __('Campo personalizado actualizado.', 'lean-columnas'),
            3  => __('Campo personalizado eliminado.', 'lean-columnas'),
            4  => __('Columna actualizada.', 'lean-columnas'),
            5  => '', // Revision restored — not used for our CPT.
            6  => sprintf(
                __('Columna publicada. <a href="%s">Ver columna</a>', 'lean-columnas'),
                esc_url($permalink)
            ),
            7  => __('Columna guardada.', 'lean-columnas'),
            8  => sprintf(
                __('Columna enviada a revision. <a target="_blank" href="%s">Vista previa</a>', 'lean-columnas'),
                esc_url($preview_url)
            ),
            9  => sprintf(
                __('Columna programada. <a target="_blank" href="%s">Vista previa</a>', 'lean-columnas'),
                esc_url($preview_url)
            ),
            10 => sprintf(
                __('Borrador actualizado. <a target="_blank" href="%s">Vista previa</a>', 'lean-columnas'),
                esc_url($preview_url)
            ),
        ];

        return $messages;
    }

    /**
     * Translate the "Submit for Review" button to Spanish on our CPT screen.
     *
     * Uses the gettext filter to intercept WP core strings only on the
     * columna-opinion edit screen.
     *
     * @param string $translation Translated text.
     * @param string $text        Original text.
     * @param string $domain      Text domain.
     * @return string
     */
    public function translateSubmitButton(string $translation, string $text, string $domain): string
    {
        if ($domain !== 'default') {
            return $translation;
        }

        // Only apply on our CPT edit screen.
        global $post;
        if (!$post || $post->post_type !== PostType::SLUG) {
            return $translation;
        }

        $translations = [
            'Submit for Review' => __('Enviar a Revision', 'lean-columnas'),
            'Save Draft'        => __('Guardar Borrador', 'lean-columnas'),
            'Preview'           => __('Vista Previa', 'lean-columnas'),
        ];

        return $translations[$text] ?? $translation;
    }

    /**
     * Remove metaboxes that columnists should not see.
     *
     * Categories are managed by editors/admins only. Columnists
     * don't need to see or assign categories.
     */
    public function removeColumnistMetaboxes(): void
    {
        $user = wp_get_current_user();
        if (!in_array('lc_columnista', $user->roles, true) && !in_array('lc_agencia', $user->roles, true)) {
            return;
        }

        remove_meta_box('columna-categoriadiv', PostType::SLUG, 'side');
    }
}
