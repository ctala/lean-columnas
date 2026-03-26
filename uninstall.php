<?php
/**
 * Lean Columnas uninstall handler.
 *
 * Removes all plugin data: roles, capabilities, user meta, and options.
 * This file is called by WordPress when the plugin is deleted via the admin UI.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Remove custom roles.
remove_role('lc_columnista');
remove_role('lc_agencia');

// Remove capabilities added to existing roles.
$editor_caps = [
    'lc_review_columns',
    'lc_edit_all_columns',
    'lc_manage_all_columnists',
    'lc_manage_agencies',
    'lc_publish_column',
    'lc_manage_settings',
];

$all_caps = array_merge($editor_caps, [
    'lc_create_column',
    'lc_edit_own_columns',
    'lc_delete_own_draft',
    'lc_submit_column',
    'lc_view_own_stats',
    'lc_upload_images',
    'lc_assign_columnists',
    'lc_submit_on_behalf',
    'lc_view_agency_stats',
    'lc_edit_agency_columns',
    'lc_view_agency_dashboard',
]);

$editor = get_role('editor');
$admin = get_role('administrator');

if ($editor instanceof WP_Role) {
    foreach ($editor_caps as $cap) {
        $editor->remove_cap($cap);
    }
}

if ($admin instanceof WP_Role) {
    foreach ($all_caps as $cap) {
        $admin->remove_cap($cap);
    }
}

// Remove plugin options.
delete_option('lean_columnas_version');
delete_option('lean_columnas_settings');

// Clean up custom user meta.
$meta_keys = [
    'lc_social_twitter',
    'lc_social_linkedin',
    'lc_social_instagram',
    'lc_agency_user_id',
    'lc_columnist_status',
];

foreach ($meta_keys as $key) {
    $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $key)
    );
}

// Clean up post meta for our CPT.
$wpdb->query(
    $wpdb->prepare(
        "DELETE pm FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE p.post_type = %s",
        'columna-opinion'
    )
);

// Delete all posts of our CPT.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->posts} WHERE post_type = %s",
        'columna-opinion'
    )
);

// Delete taxonomy terms.
$terms = get_terms([
    'taxonomy'   => 'columna-categoria',
    'hide_empty' => false,
    'fields'     => 'ids',
]);

if (is_array($terms)) {
    foreach ($terms as $term_id) {
        wp_delete_term((int) $term_id, 'columna-categoria');
    }
}

flush_rewrite_rules();
