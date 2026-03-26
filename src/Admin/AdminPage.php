<?php
/**
 * Admin pages for Column Editors.
 *
 * Provides a wp-admin interface for managing the editorial queue,
 * columnists, and agencies using standard WordPress admin patterns.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas\Admin;

use LeanColumnas\PostType;
use LeanColumnas\Editorial\WorkflowManager;

if (!defined('ABSPATH')) {
    exit;
}

class AdminPage
{
    /**
     * Register admin menu pages.
     *
     * Hooked to `admin_menu`.
     */
    public function registerMenus(): void
    {
        // Main menu page: accessible to anyone with lc_write_columns (columnists + editors + admin).
        add_menu_page(
            __('Lean Columnas', 'lean-columnas'),
            __('Columnas', 'lean-columnas'),
            'lc_write_columns',
            'lean-columnas',
            [$this, 'renderMainPage'],
            'dashicons-welcome-widgets-menus',
            26
        );

        // Submenu: My Columns (for columnists).
        add_submenu_page(
            'lean-columnas',
            __('Mis Columnas', 'lean-columnas'),
            __('Mis Columnas', 'lean-columnas'),
            'lc_write_columns',
            'lean-columnas',
            [$this, 'renderMainPage']
        );

        // Submenu: New Column shortcut.
        add_submenu_page(
            'lean-columnas',
            __('Crear Nueva', 'lean-columnas'),
            __('Crear Nueva', 'lean-columnas'),
            'lc_write_columns',
            'post-new.php?post_type=columna-opinion'
        );

        // Submenu: Editorial Queue (editors only).
        add_submenu_page(
            'lean-columnas',
            __('Cola Editorial', 'lean-columnas'),
            __('Cola Editorial', 'lean-columnas'),
            'lc_review_columns',
            'lean-columnas-queue',
            [$this, 'renderQueuePage']
        );

        // Submenu: Columnists (editors only).
        add_submenu_page(
            'lean-columnas',
            __('Columnistas', 'lean-columnas'),
            __('Columnistas', 'lean-columnas'),
            'lc_manage_all_columnists',
            'lean-columnas-columnistas',
            [$this, 'renderColumnistsPage']
        );

        // Submenu: Agencies (editors only).
        add_submenu_page(
            'lean-columnas',
            __('Agencias', 'lean-columnas'),
            __('Agencias', 'lean-columnas'),
            'lc_manage_agencies',
            'lean-columnas-agencias',
            [$this, 'renderAgenciesPage']
        );
    }

    /**
     * Render the main page — redirects based on role.
     *
     * Columnists see their own columns; editors see the editorial queue.
     */
    public function renderMainPage(): void
    {
        if (current_user_can('lc_review_columns')) {
            $this->renderQueuePage();
            return;
        }

        // Columnists see their own columns list.
        $this->renderMyColumnsPage();
    }

    /**
     * Render the "My Columns" page for columnists.
     */
    private function renderMyColumnsPage(): void
    {
        $user_id = get_current_user_id();
        $statuses = array_keys(PostType::CUSTOM_STATUSES);
        $statuses[] = 'draft';
        $statuses[] = 'publish';

        $columns = get_posts([
            'post_type'      => PostType::SLUG,
            'post_status'    => $statuses,
            'author'         => $user_id,
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $status_labels = [
            'draft'        => __('Borrador', 'lean-columnas'),
            'lc_submitted' => __('Enviada', 'lean-columnas'),
            'lc_in_review' => __('En Revision', 'lean-columnas'),
            'lc_approved'  => __('Aprobada', 'lean-columnas'),
            'lc_returned'  => __('Devuelta', 'lean-columnas'),
            'lc_rejected'  => __('Rechazada', 'lean-columnas'),
            'publish'      => __('Publicada', 'lean-columnas'),
        ];

        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Mis Columnas', 'lean-columnas'); ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . PostType::SLUG)); ?>" class="page-title-action">
                    <?php esc_html_e('Crear Nueva', 'lean-columnas'); ?>
                </a>
            </h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Titulo', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Estado', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Palabras', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Fecha', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Acciones', 'lean-columnas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($columns)) : ?>
                        <tr>
                            <td colspan="5">
                                <?php esc_html_e('Aun no tienes columnas. Crea tu primera columna de opinion.', 'lean-columnas'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($columns as $column) :
                            $word_count = str_word_count(wp_strip_all_tags($column->post_content));
                            $status_label = $status_labels[$column->post_status] ?? $column->post_status;
                            $badge_class = $column->post_status === 'publish' ? 'lc-status-active' :
                                ($column->post_status === 'lc_rejected' ? 'lc-status-inactive' : 'lc-status-pending');
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(get_edit_post_link($column->ID)); ?>">
                                            <?php echo esc_html($column->post_title ?: __('(sin titulo)', 'lean-columnas')); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <span class="lc-status-badge <?php echo esc_attr($badge_class); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html((string) $word_count); ?></td>
                                <td><?php echo esc_html(get_the_date('', $column)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($column->ID)); ?>" class="button">
                                        <?php esc_html_e('Editar', 'lean-columnas'); ?>
                                    </a>
                                    <?php if ($column->post_status === 'publish') : ?>
                                        <a href="<?php echo esc_url(get_permalink($column->ID)); ?>" class="button" target="_blank">
                                            <?php esc_html_e('Ver', 'lean-columnas'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Enqueue admin CSS.
     *
     * Hooked to `admin_enqueue_scripts`.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     */
    public function enqueueAssets(string $hook_suffix): void
    {
        $our_pages = [
            'toplevel_page_lean-columnas',
            'columnas_page_lean-columnas-queue',
            'columnas_page_lean-columnas-columnistas',
            'columnas_page_lean-columnas-agencias',
        ];

        if (!in_array($hook_suffix, $our_pages, true)) {
            return;
        }

        wp_enqueue_style(
            'lean-columnas-admin',
            LEAN_COLUMNAS_URL . 'assets/css/admin.css',
            [],
            LEAN_COLUMNAS_VERSION
        );
    }

    /**
     * Render the editorial queue page.
     */
    public function renderQueuePage(): void
    {
        if (!current_user_can('lc_review_columns')) {
            wp_die(esc_html__('No tienes permisos para acceder a esta pagina.', 'lean-columnas'));
        }

        $this->handleWorkflowAction();

        $current_tab = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'lc_submitted';
        $valid_tabs = ['lc_submitted', 'lc_in_review', 'lc_approved', 'lc_returned', 'lc_rejected'];

        if (!in_array($current_tab, $valid_tabs, true)) {
            $current_tab = 'lc_submitted';
        }

        $columns = get_posts([
            'post_type'      => PostType::SLUG,
            'post_status'    => $current_tab,
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ]);

        $status_counts = $this->getStatusCounts();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cola Editorial - Columnas de Opinion', 'lean-columnas'); ?></h1>

            <?php settings_errors('lean_columnas'); ?>

            <ul class="subsubsub">
                <?php
                $tabs = [
                    'lc_submitted' => __('Enviadas', 'lean-columnas'),
                    'lc_in_review' => __('En Revision', 'lean-columnas'),
                    'lc_approved'  => __('Aprobadas', 'lean-columnas'),
                    'lc_returned'  => __('Devueltas', 'lean-columnas'),
                    'lc_rejected'  => __('Rechazadas', 'lean-columnas'),
                ];

                $tab_links = [];
                foreach ($tabs as $status => $label) {
                    $count = $status_counts[$status] ?? 0;
                    $class = $current_tab === $status ? 'current' : '';
                    $url = add_query_arg(['page' => 'lean-columnas', 'status' => $status], admin_url('admin.php'));
                    $tab_links[] = sprintf(
                        '<li><a href="%s" class="%s">%s <span class="count">(%d)</span></a></li>',
                        esc_url($url),
                        esc_attr($class),
                        esc_html($label),
                        $count
                    );
                }
                echo implode(' | ', $tab_links); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Titulo', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Autor', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Palabras', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Fecha', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Acciones', 'lean-columnas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($columns)) : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No hay columnas en este estado.', 'lean-columnas'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($columns as $column) : ?>
                            <?php
                            $word_count = str_word_count(wp_strip_all_tags($column->post_content));
                            $author = get_userdata($column->post_author);
                            $author_name = $author ? $author->display_name : __('Desconocido', 'lean-columnas');
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(get_edit_post_link($column->ID)); ?>">
                                            <?php echo esc_html($column->post_title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($author_name); ?></td>
                                <td><?php echo esc_html((string) $word_count); ?></td>
                                <td><?php echo esc_html(get_the_date('', $column)); ?></td>
                                <td><?php $this->renderActionButtons($column); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the columnists management page.
     *
     * Uses WordPress user queries — no custom tables.
     */
    public function renderColumnistsPage(): void
    {
        if (!current_user_can('lc_manage_all_columnists')) {
            wp_die(esc_html__('No tienes permisos para acceder a esta pagina.', 'lean-columnas'));
        }

        $columnists = get_users([
            'role'    => 'lc_columnista',
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ]);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Columnistas', 'lean-columnas'); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Nombre', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Email', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Estado', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Agencia', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Columnas', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Registrado', 'lean-columnas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($columnists)) : ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No se encontraron columnistas.', 'lean-columnas'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($columnists as $user) :
                            $status = get_user_meta($user->ID, 'lc_columnist_status', true) ?: 'active';
                            $agency_id = (int) get_user_meta($user->ID, 'lc_agency_user_id', true);
                            $agency_name = '';
                            if ($agency_id > 0) {
                                $agency_user = get_userdata($agency_id);
                                $agency_name = $agency_user ? $agency_user->display_name : '';
                            }
                            $column_count = count_user_posts($user->ID, 'columna-opinion', true);
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">
                                            <?php echo esc_html($user->display_name); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td><?php echo esc_html(ucfirst($status)); ?></td>
                                <td><?php echo esc_html($agency_name ?: '—'); ?></td>
                                <td>
                                    <?php if ($column_count > 0) : ?>
                                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=columna-opinion&author=' . $user->ID)); ?>">
                                            <?php echo esc_html((string) $column_count); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html((string) $column_count); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($user->user_registered); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the agencies management page.
     *
     * Uses WordPress user queries — no custom tables.
     */
    public function renderAgenciesPage(): void
    {
        if (!current_user_can('lc_manage_agencies')) {
            wp_die(esc_html__('No tienes permisos para acceder a esta pagina.', 'lean-columnas'));
        }

        $agencies = get_users([
            'role'    => 'lc_agencia',
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ]);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Agencias', 'lean-columnas'); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Nombre', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Email', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Columnistas', 'lean-columnas'); ?></th>
                        <th scope="col"><?php esc_html_e('Registrada', 'lean-columnas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agencies)) : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('No se encontraron agencias.', 'lean-columnas'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($agencies as $agency) :
                            // Count columnists assigned to this agency.
                            $assigned = get_users([
                                'role'       => 'lc_columnista',
                                'meta_key'   => 'lc_agency_user_id',
                                'meta_value' => $agency->ID,
                                'fields'     => 'ID',
                            ]);
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(get_edit_user_link($agency->ID)); ?>">
                                            <?php echo esc_html($agency->display_name); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($agency->user_email); ?></td>
                                <td><?php echo esc_html((string) count($assigned)); ?></td>
                                <td><?php echo esc_html($agency->user_registered); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Handle workflow actions submitted from the queue page.
     */
    private function handleWorkflowAction(): void
    {
        if (!isset($_GET['lc_action'], $_GET['lc_post_id'], $_GET['_lcnonce'])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_GET['lc_action']));
        $post_id = absint($_GET['lc_post_id']);
        $nonce = sanitize_text_field(wp_unslash($_GET['_lcnonce']));

        if (!wp_verify_nonce($nonce, 'lc_workflow_' . $action . '_' . $post_id)) {
            add_settings_error('lean_columnas', 'nonce_failed', __('Verificacion de seguridad fallida.', 'lean-columnas'), 'error');
            return;
        }

        $workflow = new WorkflowManager();
        $user_id = get_current_user_id();
        $status_map = [
            'review'  => 'lc_in_review',
            'approve' => 'lc_approved',
            'publish' => 'publish',
        ];

        $target = $status_map[$action] ?? null;
        if ($target === null) {
            return;
        }

        $result = $workflow->transitionTo($post_id, $target, $user_id);

        if (is_wp_error($result)) {
            add_settings_error('lean_columnas', 'transition_failed', $result->get_error_message(), 'error');
        } else {
            add_settings_error('lean_columnas', 'transition_success', __('Estado actualizado correctamente.', 'lean-columnas'), 'success');
        }
    }

    /**
     * Render workflow action buttons for a column.
     *
     * @param \WP_Post $column The column post.
     */
    private function renderActionButtons(\WP_Post $column): void
    {
        $allowed = WorkflowManager::getAllowedTransitions($column->post_status);
        $actions = [
            'lc_in_review' => ['action' => 'review',  'label' => __('Tomar', 'lean-columnas'),   'class' => 'button'],
            'lc_approved'  => ['action' => 'approve', 'label' => __('Aprobar', 'lean-columnas'),  'class' => 'button button-primary'],
            'publish'      => ['action' => 'publish', 'label' => __('Publicar', 'lean-columnas'), 'class' => 'button button-primary'],
        ];

        foreach ($actions as $status => $config) {
            if (!in_array($status, $allowed, true)) {
                continue;
            }

            $url = add_query_arg([
                'page'       => 'lean-columnas',
                'lc_action'  => $config['action'],
                'lc_post_id' => $column->ID,
                '_lcnonce'   => wp_create_nonce('lc_workflow_' . $config['action'] . '_' . $column->ID),
            ], admin_url('admin.php'));

            printf(
                '<a href="%s" class="%s">%s</a> ',
                esc_url($url),
                esc_attr($config['class']),
                esc_html($config['label'])
            );
        }

        printf(
            '<a href="%s" class="button">%s</a>',
            esc_url(get_edit_post_link($column->ID) ?? ''),
            esc_html__('Editar', 'lean-columnas')
        );
    }

    /**
     * Get post counts by custom status.
     *
     * @return array<string, int>
     */
    private function getStatusCounts(): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_status, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_status",
                PostType::SLUG
            )
        );

        $counts = [];
        foreach ($results as $row) {
            $counts[$row->post_status] = (int) $row->count;
        }

        return $counts;
    }
}
