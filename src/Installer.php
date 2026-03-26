<?php
/**
 * Plugin installer.
 *
 * Handles activation tasks and version upgrades.
 * No custom tables — uses WordPress user meta for columnist/agency data.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas;

if (!defined('ABSPATH')) {
    exit;
}

class Installer
{
    /**
     * Run the full activation routine.
     */
    public function activate(): void
    {
        update_option('lean_columnas_version', LEAN_COLUMNAS_VERSION);
    }

    /**
     * Check if an upgrade is needed and run it.
     *
     * @return bool True if an upgrade was performed.
     */
    public function maybeUpgrade(): bool
    {
        $stored = get_option('lean_columnas_version', '0.0.0');

        if (version_compare($stored, LEAN_COLUMNAS_VERSION, '>=')) {
            return false;
        }

        update_option('lean_columnas_version', LEAN_COLUMNAS_VERSION);

        return true;
    }
}
