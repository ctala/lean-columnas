<?php
/**
 * Custom post type and taxonomy registration.
 *
 * Registers the columna-opinion CPT, custom statuses,
 * and the columna-categoria taxonomy.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas;

if (!defined('ABSPATH')) {
    exit;
}

class PostType
{
    /**
     * CPT slug.
     */
    public const SLUG = 'columna-opinion';

    /**
     * Taxonomy slug.
     */
    public const TAXONOMY = 'columna-categoria';

    /**
     * Custom post statuses used in the editorial workflow.
     */
    public const CUSTOM_STATUSES = [
        'lc_submitted' => 'Submitted',
        'lc_in_review' => 'In Review',
        'lc_returned'  => 'Returned',
        'lc_approved'  => 'Approved',
        'lc_rejected'  => 'Rejected',
    ];

    /**
     * Register CPT, taxonomy, and custom statuses.
     *
     * Checks if the CPT is already registered (e.g., by esup-actores)
     * and only registers if not present. This avoids conflicts when
     * both plugins are active during migration.
     *
     * Hooked to `init`.
     */
    public function register(): void
    {
        if (!post_type_exists(self::SLUG)) {
            $this->registerPostType();
        }

        if (!taxonomy_exists(self::TAXONOMY)) {
            $this->registerTaxonomy();
        }

        $this->registerCustomStatuses();
    }

    /**
     * Register the columna-opinion custom post type.
     */
    private function registerPostType(): void
    {
        $labels = [
            'name'                  => __('Columnas de Opinion', 'lean-columnas'),
            'singular_name'         => __('Columna de Opinion', 'lean-columnas'),
            'menu_name'             => __('Columnas', 'lean-columnas'),
            'name_admin_bar'        => __('Columna', 'lean-columnas'),
            'add_new'               => __('Agregar Nueva', 'lean-columnas'),
            'add_new_item'          => __('Agregar Nueva Columna', 'lean-columnas'),
            'new_item'              => __('Nueva Columna', 'lean-columnas'),
            'edit_item'             => __('Editar Columna', 'lean-columnas'),
            'view_item'             => __('Ver Columna', 'lean-columnas'),
            'all_items'             => __('Todas las Columnas', 'lean-columnas'),
            'search_items'          => __('Buscar Columnas', 'lean-columnas'),
            'not_found'             => __('No se encontraron columnas.', 'lean-columnas'),
            'not_found_in_trash'    => __('No se encontraron columnas en la papelera.', 'lean-columnas'),
            'archives'              => __('Archivo de Columnas', 'lean-columnas'),
            'filter_items_list'     => __('Filtrar columnas', 'lean-columnas'),
            'items_list_navigation' => __('Navegacion de columnas', 'lean-columnas'),
            'items_list'            => __('Lista de columnas', 'lean-columnas'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'columna-opinion', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-welcome-widgets-menus',
            'show_in_rest'       => true,
            'rest_base'          => 'columnas-opinion',
            'supports'           => ['title', 'editor', 'author', 'thumbnail', 'excerpt'],
            'taxonomies'         => [self::TAXONOMY],
        ];

        register_post_type(self::SLUG, $args);
    }

    /**
     * Register the columna-categoria taxonomy.
     */
    private function registerTaxonomy(): void
    {
        $labels = [
            'name'              => __('Categorias de Columna', 'lean-columnas'),
            'singular_name'     => __('Categoria de Columna', 'lean-columnas'),
            'search_items'      => __('Buscar Categorias', 'lean-columnas'),
            'all_items'         => __('Todas las Categorias', 'lean-columnas'),
            'parent_item'       => __('Categoria Padre', 'lean-columnas'),
            'parent_item_colon' => __('Categoria Padre:', 'lean-columnas'),
            'edit_item'         => __('Editar Categoria', 'lean-columnas'),
            'update_item'       => __('Actualizar Categoria', 'lean-columnas'),
            'add_new_item'      => __('Agregar Nueva Categoria', 'lean-columnas'),
            'new_item_name'     => __('Nuevo Nombre de Categoria', 'lean-columnas'),
            'menu_name'         => __('Categorias', 'lean-columnas'),
        ];

        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'columna-categoria'],
            'show_in_rest'      => true,
            'rest_base'         => 'columna-categorias',
        ];

        register_taxonomy(self::TAXONOMY, [self::SLUG], $args);
    }

    /**
     * Register custom post statuses for the editorial workflow.
     */
    private function registerCustomStatuses(): void
    {
        foreach (self::CUSTOM_STATUSES as $status => $label) {
            register_post_status($status, [
                'label'                     => __($label, 'lean-columnas'), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                'public'                    => false,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                /* translators: %s: number of columns */
                'label_count'               => _n_noop(
                    $label . ' <span class="count">(%s)</span>',
                    $label . ' <span class="count">(%s)</span>',
                    'lean-columnas'
                ),
            ]);
        }
    }

    /**
     * Get all valid statuses including WordPress defaults.
     *
     * @return string[]
     */
    public static function getAllStatuses(): array
    {
        return array_merge(
            ['draft', 'publish', 'trash', 'pending'],
            array_keys(self::CUSTOM_STATUSES)
        );
    }
}
