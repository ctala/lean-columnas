<?php
/**
 * Dashboard widgets for columnists and editors.
 *
 * Provides at-a-glance statistics on the WordPress admin dashboard.
 * Columnists see their own columns; editors/admins see the editorial queue.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas\Admin;

use LeanColumnas\PostType;

if (!defined('ABSPATH')) {
    exit;
}

class DashboardWidget
{
    /**
     * Status labels in Spanish for display.
     */
    private const STATUS_LABELS = [
        'draft'        => 'Borrador',
        'lc_submitted' => 'Enviada',
        'lc_in_review' => 'En Revision',
        'lc_approved'  => 'Aprobada',
        'lc_returned'  => 'Devuelta',
        'lc_rejected'  => 'Rechazada',
        'publish'      => 'Publicada',
    ];

    /**
     * Register dashboard widgets.
     *
     * Hooked to `wp_dashboard_setup`.
     */
    public function register(): void
    {
        if (current_user_can('lc_write_columns')) {
            wp_add_dashboard_widget(
                'lc_mis_columnas',
                __('Mis Columnas', 'lean-columnas'),
                [$this, 'renderColumnistWidget']
            );
        }

        if (current_user_can('lc_review_columns')) {
            wp_add_dashboard_widget(
                'lc_cola_editorial',
                __('Cola Editorial', 'lean-columnas'),
                [$this, 'renderEditorWidget']
            );
        }
    }

    /**
     * Render the columnist dashboard widget.
     *
     * Shows personal column statistics and recent columns.
     */
    public function renderColumnistWidget(): void
    {
        $user_id = get_current_user_id();
        $status_counts = $this->getUserStatusCounts($user_id);
        $total = array_sum($status_counts);
        $recent = $this->getRecentColumns($user_id, 5);

        ?>
        <style>
            .lc-widget-stats { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
            .lc-widget-stat { background: #f0f0f1; padding: 6px 10px; border-radius: 4px; font-size: 13px; }
            .lc-widget-stat strong { display: block; font-size: 18px; line-height: 1.3; }
            .lc-widget-total { background: #2271b1; color: #fff; }
            .lc-widget-table { width: 100%; border-spacing: 0; }
            .lc-widget-table th,
            .lc-widget-table td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; }
            .lc-widget-table th { font-weight: 600; color: #50575e; }
            .lc-widget-status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; background: #f0f0f1; }
            .lc-widget-footer { margin-top: 12px; padding-top: 12px; border-top: 1px solid #f0f0f1; }
        </style>

        <div class="lc-widget-stats">
            <div class="lc-widget-stat lc-widget-total">
                <strong><?php echo esc_html((string) $total); ?></strong>
                <?php esc_html_e('Total', 'lean-columnas'); ?>
            </div>
            <?php foreach (self::STATUS_LABELS as $status => $label) :
                $count = $status_counts[$status] ?? 0;
                if ($count === 0) {
                    continue;
                }
                ?>
                <div class="lc-widget-stat">
                    <strong><?php echo esc_html((string) $count); ?></strong>
                    <?php echo esc_html(__($label, 'lean-columnas')); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($recent)) : ?>
            <table class="lc-widget-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Titulo', 'lean-columnas'); ?></th>
                        <th><?php esc_html_e('Estado', 'lean-columnas'); ?></th>
                        <th><?php esc_html_e('Fecha', 'lean-columnas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $column) :
                        $status_label = self::STATUS_LABELS[$column->post_status] ?? $column->post_status;
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($column->ID) ?? ''); ?>">
                                    <?php echo esc_html($column->post_title ?: __('(sin titulo)', 'lean-columnas')); ?>
                                </a>
                            </td>
                            <td>
                                <span class="lc-widget-status-badge">
                                    <?php echo esc_html(__($status_label, 'lean-columnas')); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(get_the_date('d M Y', $column)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('Aun no tienes columnas. Crea tu primera columna de opinion.', 'lean-columnas'); ?></p>
        <?php endif; ?>

        <div class="lc-widget-footer">
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . PostType::SLUG)); ?>" class="button button-primary">
                <?php esc_html_e('Crear nueva columna', 'lean-columnas'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . PostType::SLUG)); ?>" class="button" style="margin-left: 6px;">
                <?php esc_html_e('Ver todas', 'lean-columnas'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Render the editor/admin dashboard widget.
     *
     * Shows editorial queue overview and quick stats.
     */
    public function renderEditorWidget(): void
    {
        $status_counts = $this->getGlobalStatusCounts();
        $submitted_count = $status_counts['lc_submitted'] ?? 0;
        $in_review_count = $status_counts['lc_in_review'] ?? 0;
        $approved_count = $status_counts['lc_approved'] ?? 0;
        $pending_total = $submitted_count + $in_review_count + $approved_count;

        $today_count = $this->getSubmittedSince('today');
        $week_count = $this->getSubmittedSince('monday this week');

        ?>
        <style>
            .lc-editor-stats { display: flex; gap: 8px; margin-bottom: 12px; }
            .lc-editor-stat { flex: 1; text-align: center; background: #f0f0f1; padding: 10px 8px; border-radius: 4px; }
            .lc-editor-stat strong { display: block; font-size: 22px; line-height: 1.3; }
            .lc-editor-stat.lc-pending { background: #dba617; color: #fff; }
            .lc-editor-detail { margin: 12px 0; }
            .lc-editor-detail dt { font-weight: 600; float: left; clear: left; width: 180px; padding: 4px 0; font-size: 13px; }
            .lc-editor-detail dd { margin-left: 190px; padding: 4px 0; font-size: 13px; }
            .lc-editor-footer { margin-top: 12px; padding-top: 12px; border-top: 1px solid #f0f0f1; }
        </style>

        <div class="lc-editor-stats">
            <div class="lc-editor-stat <?php echo $pending_total > 0 ? 'lc-pending' : ''; ?>">
                <strong><?php echo esc_html((string) $pending_total); ?></strong>
                <?php esc_html_e('Pendientes', 'lean-columnas'); ?>
            </div>
            <div class="lc-editor-stat">
                <strong><?php echo esc_html((string) $submitted_count); ?></strong>
                <?php esc_html_e('Enviadas', 'lean-columnas'); ?>
            </div>
            <div class="lc-editor-stat">
                <strong><?php echo esc_html((string) $in_review_count); ?></strong>
                <?php esc_html_e('En Revision', 'lean-columnas'); ?>
            </div>
            <div class="lc-editor-stat">
                <strong><?php echo esc_html((string) $approved_count); ?></strong>
                <?php esc_html_e('Aprobadas', 'lean-columnas'); ?>
            </div>
        </div>

        <dl class="lc-editor-detail">
            <dt><?php esc_html_e('Enviadas hoy', 'lean-columnas'); ?></dt>
            <dd><?php echo esc_html((string) $today_count); ?></dd>

            <dt><?php esc_html_e('Enviadas esta semana', 'lean-columnas'); ?></dt>
            <dd><?php echo esc_html((string) $week_count); ?></dd>
        </dl>

        <div class="lc-editor-footer">
            <a href="<?php echo esc_url(admin_url('admin.php?page=lean-columnas')); ?>" class="button button-primary">
                <?php esc_html_e('Ver Cola Editorial', 'lean-columnas'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Get column counts by status for a specific user.
     *
     * @param int $user_id The user ID.
     * @return array<string, int>
     */
    private function getUserStatusCounts(int $user_id): array
    {
        $counts = [];

        foreach (array_keys(self::STATUS_LABELS) as $status) {
            $query = new \WP_Query([
                'post_type'      => PostType::SLUG,
                'post_status'    => $status,
                'author'         => $user_id,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
            ]);

            $counts[$status] = $query->found_posts;
        }

        return $counts;
    }

    /**
     * Get recent columns for a specific user.
     *
     * @param int $user_id The user ID.
     * @param int $limit   Number of columns to retrieve.
     * @return \WP_Post[]
     */
    private function getRecentColumns(int $user_id, int $limit): array
    {
        return get_posts([
            'post_type'      => PostType::SLUG,
            'post_status'    => array_keys(self::STATUS_LABELS),
            'author'         => $user_id,
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
    }

    /**
     * Get global column counts by status (all authors).
     *
     * @return array<string, int>
     */
    private function getGlobalStatusCounts(): array
    {
        $counts = [];

        foreach (array_keys(self::STATUS_LABELS) as $status) {
            $query = new \WP_Query([
                'post_type'      => PostType::SLUG,
                'post_status'    => $status,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
            ]);

            $counts[$status] = $query->found_posts;
        }

        return $counts;
    }

    /**
     * Count columns submitted since a given date string.
     *
     * @param string $since A strtotime-compatible date string.
     * @return int
     */
    private function getSubmittedSince(string $since): int
    {
        $date = gmdate('Y-m-d H:i:s', strtotime($since));

        $query = new \WP_Query([
            'post_type'      => PostType::SLUG,
            'post_status'    => 'lc_submitted',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
            'date_query'     => [
                [
                    'after'     => $date,
                    'inclusive' => true,
                ],
            ],
        ]);

        return $query->found_posts;
    }
}
