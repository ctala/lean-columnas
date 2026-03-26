<?php
/**
 * User profile fields for columnists and agencies.
 *
 * Adds social link fields and agency relationship to WordPress
 * user profile pages. Uses native WP user meta for all data.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas;

if (!defined('ABSPATH')) {
    exit;
}

class UserProfile
{
    /**
     * Custom user meta keys for columnists.
     */
    public const META_KEYS = [
        'lc_social_twitter',
        'lc_social_linkedin',
        'lc_social_instagram',
        'lc_agency_user_id',
        'lc_columnist_status',
    ];

    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_action('show_user_profile', [$this, 'renderFields']);
        add_action('edit_user_profile', [$this, 'renderFields']);
        add_action('personal_options_update', [$this, 'saveFields']);
        add_action('edit_user_profile_update', [$this, 'saveFields']);
    }

    /**
     * Render custom fields on user profile page.
     *
     * @param \WP_User $user The user being edited.
     */
    public function renderFields(\WP_User $user): void
    {
        $roles = $user->roles;
        $is_columnist = in_array('lc_columnista', $roles, true);
        $is_agency = in_array('lc_agencia', $roles, true);

        // Only show on columnists, agencies, or when admin is editing.
        if (!$is_columnist && !$is_agency && !current_user_can('lc_manage_all_columnists')) {
            return;
        }

        wp_nonce_field('lc_save_user_meta', 'lc_user_meta_nonce');

        ?>
        <h3><?php esc_html_e('Lean Columnas', 'lean-columnas'); ?></h3>
        <table class="form-table" role="presentation">
            <?php if ($is_columnist || current_user_can('lc_manage_all_columnists')) : ?>
            <tr>
                <th><label for="lc_social_twitter"><?php esc_html_e('Twitter/X URL', 'lean-columnas'); ?></label></th>
                <td>
                    <input type="url" name="lc_social_twitter" id="lc_social_twitter"
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'lc_social_twitter', true)); ?>"
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="lc_social_linkedin"><?php esc_html_e('LinkedIn URL', 'lean-columnas'); ?></label></th>
                <td>
                    <input type="url" name="lc_social_linkedin" id="lc_social_linkedin"
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'lc_social_linkedin', true)); ?>"
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="lc_social_instagram"><?php esc_html_e('Instagram URL', 'lean-columnas'); ?></label></th>
                <td>
                    <input type="url" name="lc_social_instagram" id="lc_social_instagram"
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'lc_social_instagram', true)); ?>"
                           class="regular-text" />
                </td>
            </tr>
            <?php endif; ?>

            <?php if ($is_columnist && current_user_can('lc_manage_all_columnists')) : ?>
            <tr>
                <th><label for="lc_agency_user_id"><?php esc_html_e('Agencia asignada', 'lean-columnas'); ?></label></th>
                <td>
                    <?php
                    $current_agency = (int) get_user_meta($user->ID, 'lc_agency_user_id', true);
                    $agencies = get_users(['role' => 'lc_agencia', 'orderby' => 'display_name']);
                    ?>
                    <select name="lc_agency_user_id" id="lc_agency_user_id">
                        <option value="0"><?php esc_html_e('— Independiente —', 'lean-columnas'); ?></option>
                        <?php foreach ($agencies as $agency) : ?>
                            <option value="<?php echo esc_attr((string) $agency->ID); ?>"
                                <?php selected($current_agency, $agency->ID); ?>>
                                <?php echo esc_html($agency->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Agencia que gestiona a este columnista.', 'lean-columnas'); ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>

            <?php if ($is_columnist) : ?>
            <tr>
                <th><label for="lc_columnist_status"><?php esc_html_e('Estado del columnista', 'lean-columnas'); ?></label></th>
                <td>
                    <?php $status = get_user_meta($user->ID, 'lc_columnist_status', true) ?: 'active'; ?>
                    <select name="lc_columnist_status" id="lc_columnist_status"
                        <?php disabled(!current_user_can('lc_manage_all_columnists')); ?>>
                        <option value="active" <?php selected($status, 'active'); ?>>
                            <?php esc_html_e('Activo', 'lean-columnas'); ?>
                        </option>
                        <option value="inactive" <?php selected($status, 'inactive'); ?>>
                            <?php esc_html_e('Inactivo', 'lean-columnas'); ?>
                        </option>
                        <option value="pending" <?php selected($status, 'pending'); ?>>
                            <?php esc_html_e('Pendiente', 'lean-columnas'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Save custom user meta fields.
     *
     * @param int $user_id The user ID being saved.
     */
    public function saveFields(int $user_id): void
    {
        if (!isset($_POST['lc_user_meta_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['lc_user_meta_nonce'])),
            'lc_save_user_meta'
        )) {
            return;
        }

        // Only admins/editors can edit other users' meta.
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        $url_fields = ['lc_social_twitter', 'lc_social_linkedin', 'lc_social_instagram'];
        foreach ($url_fields as $field) {
            if (isset($_POST[$field])) {
                update_user_meta($user_id, $field, esc_url_raw(wp_unslash($_POST[$field])));
            }
        }

        if (isset($_POST['lc_agency_user_id']) && current_user_can('lc_manage_all_columnists')) {
            $agency_id = absint($_POST['lc_agency_user_id']);
            if ($agency_id > 0) {
                update_user_meta($user_id, 'lc_agency_user_id', $agency_id);
            } else {
                delete_user_meta($user_id, 'lc_agency_user_id');
            }
        }

        if (isset($_POST['lc_columnist_status']) && current_user_can('lc_manage_all_columnists')) {
            $status = sanitize_text_field(wp_unslash($_POST['lc_columnist_status']));
            if (in_array($status, ['active', 'inactive', 'pending'], true)) {
                update_user_meta($user_id, 'lc_columnist_status', $status);
            }
        }
    }

    /**
     * Get columnist data from a WP user.
     *
     * @param int $user_id WordPress user ID.
     *
     * @return array{display_name: string, bio: string, photo_url: string, social_twitter: string, social_linkedin: string, social_instagram: string, website_url: string, agency_user_id: int}|null
     */
    public static function getColumnistData(int $user_id): ?array
    {
        $user = get_userdata($user_id);
        if ($user === false) {
            return null;
        }

        return [
            'display_name'    => $user->display_name,
            'bio'             => $user->description,
            'photo_url'       => get_avatar_url($user_id, ['size' => 200]),
            'social_twitter'  => get_user_meta($user_id, 'lc_social_twitter', true) ?: '',
            'social_linkedin' => get_user_meta($user_id, 'lc_social_linkedin', true) ?: '',
            'social_instagram' => get_user_meta($user_id, 'lc_social_instagram', true) ?: '',
            'website_url'     => $user->user_url,
            'agency_user_id'  => (int) get_user_meta($user_id, 'lc_agency_user_id', true),
        ];
    }
}
