<?php
/**
 * Archive template for opinion columns.
 *
 * Displays a grid of column cards with category filter and pagination.
 *
 * Override this template by copying it to:
 * your-theme/lean-columnas/archive-columna-opinion.php
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use LeanColumnas\UserProfile;

get_header();
?>

<div class="lc-archive">

    <header class="lc-archive-header">
        <?php if (is_tax('columna-categoria')) : ?>
            <h1 class="entry-title">
                <?php
                printf(
                    /* translators: %s: category name */
                    esc_html__('Columnas: %s', 'lean-columnas'),
                    esc_html(single_term_title('', false))
                );
                ?>
            </h1>
            <?php
            $term_description = term_description();
            if (!empty($term_description)) :
            ?>
                <div class="lc-archive-description">
                    <?php echo wp_kses_post($term_description); ?>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <h1 class="entry-title">
                <?php esc_html_e('Columnas de Opinion', 'lean-columnas'); ?>
            </h1>
            <p class="lc-archive-description">
                <?php esc_html_e('Las voces y perspectivas de nuestros columnistas.', 'lean-columnas'); ?>
            </p>
        <?php endif; ?>
    </header>

    <?php
    // Category filter.
    $categories = get_terms([
        'taxonomy'   => 'columna-categoria',
        'hide_empty' => true,
    ]);

    if (is_array($categories) && !empty($categories)) :
    ?>
    <nav class="lc-category-filter" aria-label="<?php esc_attr_e('Filtrar por categoria', 'lean-columnas'); ?>">
        <a href="<?php echo esc_url(get_post_type_archive_link('columna-opinion')); ?>"
           class="lc-filter-link <?php echo !is_tax('columna-categoria') ? 'lc-filter-link--active' : ''; ?>">
            <?php esc_html_e('Todas', 'lean-columnas'); ?>
        </a>
        <?php foreach ($categories as $cat) : ?>
            <a href="<?php echo esc_url(get_term_link($cat)); ?>"
               class="lc-filter-link <?php echo is_tax('columna-categoria', $cat->slug) ? 'lc-filter-link--active' : ''; ?>">
                <?php echo esc_html($cat->name); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if (have_posts()) : ?>
        <div class="lc-columns-grid">
            <?php
            while (have_posts()) :
                the_post();
                $rel_author = UserProfile::getColumnistData((int) get_the_author_meta('ID'));
                include LEAN_COLUMNAS_PATH . 'templates/parts/column-card.php';
            endwhile;
            ?>
        </div>

        <nav class="lc-pagination" aria-label="<?php esc_attr_e('Paginacion', 'lean-columnas'); ?>">
            <?php
            the_posts_pagination([
                'mid_size'  => 2,
                'prev_text' => esc_html__('&laquo; Anterior', 'lean-columnas'),
                'next_text' => esc_html__('Siguiente &raquo;', 'lean-columnas'),
            ]);
            ?>
        </nav>

    <?php else : ?>
        <div class="lc-no-results">
            <p><?php esc_html_e('No se encontraron columnas.', 'lean-columnas'); ?></p>
        </div>
    <?php endif; ?>

</div>

<?php
get_footer();
