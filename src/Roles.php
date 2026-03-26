<?php
/**
 * Custom roles and capabilities manager.
 *
 * Registers the Columnista and Agencia roles and adds
 * Column Editor capabilities to existing editor/admin roles.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas;

if (!defined('ABSPATH')) {
    exit;
}

class Roles
{
    /**
     * Capabilities for the Columnista role.
     *
     * @var array<string, bool>
     */
    private const COLUMNISTA_CAPS = [
        // WordPress core caps needed for basic functionality.
        'read'         => true,
        'upload_files' => true,

        // Plugin-specific caps.
        'lc_create_column'    => true,
        'lc_edit_own_columns' => true,
        'lc_delete_own_draft' => true,
        'lc_submit_column'    => true,
        'lc_view_own_stats'   => true,
        'lc_upload_images'    => true,
    ];

    /**
     * Additional capabilities for the Agencia role (on top of Columnista caps).
     *
     * @var array<string, bool>
     */
    private const AGENCIA_EXTRA_CAPS = [
        'lc_assign_columnists'     => true,
        'lc_submit_on_behalf'      => true,
        'lc_view_agency_stats'     => true,
        'lc_edit_agency_columns'   => true,
        'lc_view_agency_dashboard' => true,
    ];

    /**
     * Capabilities for the Column Editor (added to editor and admin roles).
     *
     * @var array<string, bool>
     */
    private const EDITOR_CAPS = [
        'lc_review_columns'        => true,
        'lc_edit_all_columns'      => true,
        'lc_manage_all_columnists' => true,
        'lc_manage_agencies'       => true,
        'lc_publish_column'        => true,
        'lc_manage_settings'       => true,
    ];

    /**
     * Register all custom roles and capabilities.
     */
    public function register(): void
    {
        $this->registerColumnistaRole();
        $this->registerAgenciaRole();
        $this->addEditorCapabilities();
        $this->addAdminCapabilities();
    }

    /**
     * Remove all custom roles and capabilities.
     *
     * Used during uninstall.
     */
    public function remove(): void
    {
        remove_role('lc_columnista');
        remove_role('lc_agencia');
        $this->removeEditorCapabilities();
        $this->removeAdminCapabilities();
    }

    /**
     * Register the Columnista role.
     */
    private function registerColumnistaRole(): void
    {
        // Remove first to ensure clean state on re-activation.
        remove_role('lc_columnista');

        add_role(
            'lc_columnista',
            __('Columnista', 'lean-columnas'),
            self::COLUMNISTA_CAPS
        );
    }

    /**
     * Register the Agencia role.
     *
     * Includes all Columnista caps plus agency-specific caps.
     */
    private function registerAgenciaRole(): void
    {
        remove_role('lc_agencia');

        $caps = array_merge(self::COLUMNISTA_CAPS, self::AGENCIA_EXTRA_CAPS);

        add_role(
            'lc_agencia',
            __('Agencia', 'lean-columnas'),
            $caps
        );
    }

    /**
     * Add Column Editor capabilities to the editor role.
     */
    private function addEditorCapabilities(): void
    {
        $editor = get_role('editor');
        if (!$editor instanceof \WP_Role) {
            return;
        }

        foreach (self::EDITOR_CAPS as $cap => $grant) {
            $editor->add_cap($cap, $grant);
        }
    }

    /**
     * Add all plugin capabilities to the administrator role.
     */
    private function addAdminCapabilities(): void
    {
        $admin = get_role('administrator');
        if (!$admin instanceof \WP_Role) {
            return;
        }

        $all_caps = array_merge(
            self::COLUMNISTA_CAPS,
            self::AGENCIA_EXTRA_CAPS,
            self::EDITOR_CAPS
        );

        foreach ($all_caps as $cap => $grant) {
            $admin->add_cap($cap, $grant);
        }
    }

    /**
     * Remove Column Editor capabilities from the editor role.
     */
    private function removeEditorCapabilities(): void
    {
        $editor = get_role('editor');
        if (!$editor instanceof \WP_Role) {
            return;
        }

        foreach (array_keys(self::EDITOR_CAPS) as $cap) {
            $editor->remove_cap($cap);
        }
    }

    /**
     * Remove all plugin capabilities from the administrator role.
     */
    private function removeAdminCapabilities(): void
    {
        $admin = get_role('administrator');
        if (!$admin instanceof \WP_Role) {
            return;
        }

        $all_caps = array_merge(
            self::COLUMNISTA_CAPS,
            self::AGENCIA_EXTRA_CAPS,
            self::EDITOR_CAPS
        );

        foreach (array_keys($all_caps) as $cap) {
            $admin->remove_cap($cap);
        }
    }

    /**
     * Get all plugin capabilities as a flat array.
     *
     * @return string[]
     */
    public static function getAllCapabilities(): array
    {
        return array_keys(array_merge(
            self::COLUMNISTA_CAPS,
            self::AGENCIA_EXTRA_CAPS,
            self::EDITOR_CAPS
        ));
    }
}
