<?php
/**
 * Template loader.
 *
 * Handles the template hierarchy for the columna-opinion CPT.
 * Checks theme directory first for overrides, then falls back
 * to plugin templates.
 *
 * Theme override path: theme/lean-columnas/single-columna-opinion.php
 * Plugin fallback path: plugins/lean-columnas/templates/single-columna-opinion.php
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas\Templates;

use LeanColumnas\PostType;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateLoader
{
    /**
     * Theme subdirectory for template overrides.
     */
    private const THEME_DIR = 'lean-columnas';

    /**
     * Plugin templates directory.
     */
    private const PLUGIN_DIR = 'templates';

    /**
     * Filter the template to load for our CPT.
     *
     * Hooked to `template_include`.
     *
     * @param string $template The current template path.
     *
     * @return string The resolved template path.
     */
    public function loadTemplate(string $template): string
    {
        if (is_singular(PostType::SLUG)) {
            $custom = $this->locateTemplate('single-columna-opinion.php');
            if ($custom !== '') {
                return $custom;
            }
        }

        if (is_post_type_archive(PostType::SLUG)) {
            $custom = $this->locateTemplate('archive-columna-opinion.php');
            if ($custom !== '') {
                return $custom;
            }
        }

        if (is_tax(PostType::TAXONOMY)) {
            // Use archive template for taxonomy pages as well.
            $custom = $this->locateTemplate('archive-columna-opinion.php');
            if ($custom !== '') {
                return $custom;
            }
        }

        return $template;
    }

    /**
     * Locate a template in theme or plugin directories.
     *
     * Checks:
     * 1. Child theme: child-theme/lean-columnas/{template}
     * 2. Parent theme: parent-theme/lean-columnas/{template}
     * 3. Plugin: plugin/templates/{template}
     *
     * @param string $template_name Template file name.
     *
     * @return string Full path to the template, or empty string if not found.
     */
    public function locateTemplate(string $template_name): string
    {
        // Check child theme first, then parent theme.
        $theme_template = locate_template([
            self::THEME_DIR . '/' . $template_name,
        ]);

        if ($theme_template !== '') {
            return $theme_template;
        }

        // Fall back to plugin templates.
        $plugin_template = LEAN_COLUMNAS_PATH . self::PLUGIN_DIR . '/' . $template_name;

        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return '';
    }

    /**
     * Load a template part (for includes within templates).
     *
     * Usage: TemplateLoader::getPart('parts/column-card');
     *
     * @param string               $slug Template part slug (without .php).
     * @param array<string, mixed> $args Variables to pass to the template.
     */
    public static function getPart(string $slug, array $args = []): void
    {
        $loader = new self();
        $template = $loader->locateTemplate($slug . '.php');

        if ($template === '') {
            return;
        }

        // Extract args to make them available as variables in the template.
        if (!empty($args)) {
            // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            extract($args, EXTR_SKIP);
        }

        include $template;
    }

    /**
     * Get the URL for a plugin asset (CSS, JS, images).
     *
     * @param string $path Relative path from the plugin root.
     *
     * @return string Full URL to the asset.
     */
    public static function assetUrl(string $path): string
    {
        return LEAN_COLUMNAS_URL . ltrim($path, '/');
    }
}
