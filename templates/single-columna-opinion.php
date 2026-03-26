<?php
/**
 * Single column template.
 *
 * Two-column layout: article left, related columns sidebar right.
 * No featured image — author photo comes from user profile.
 *
 * Override by copying to: your-theme/lean-columnas/single-columna-opinion.php
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use LeanColumnas\UserProfile;

get_header();

while (have_posts()) :
    the_post();

    $post_id   = get_the_ID();
    $author_id = (int) get_the_author_meta('ID');
    $columnist = UserProfile::getColumnistData($author_id);
?>

<div class="lc-single-layout">

    <article id="post-<?php the_ID(); ?>" <?php post_class('lc-single-column'); ?>>

        <nav class="lc-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'lean-columnas'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Inicio', 'lean-columnas'); ?></a>
            <span class="lc-breadcrumb-sep" aria-hidden="true">/</span>
            <a href="<?php echo esc_url(get_post_type_archive_link('columna-opinion')); ?>"><?php esc_html_e('Columnas', 'lean-columnas'); ?></a>
            <span class="lc-breadcrumb-sep" aria-hidden="true">/</span>
            <span class="lc-breadcrumb-current" aria-current="page"><?php the_title(); ?></span>
        </nav>

        <header class="lc-column-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>
            <div class="entry-meta lc-column-meta">
                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                    <?php echo esc_html(get_the_date()); ?>
                </time>
                <?php
                $word_count = str_word_count(wp_strip_all_tags(get_the_content()));
                $read_time = max(1, (int) ceil($word_count / 200));
                ?>
                <span class="lc-read-time">
                    <?php printf(esc_html__('%d min de lectura', 'lean-columnas'), $read_time); ?>
                </span>
            </div>
        </header>

        <?php if ($columnist !== null) : ?>
        <aside class="lc-author-card">
            <?php if (!empty($columnist['photo_url'])) : ?>
                <img src="<?php echo esc_url($columnist['photo_url']); ?>"
                     alt="<?php echo esc_attr($columnist['display_name']); ?>"
                     class="lc-author-photo" width="64" height="64" loading="lazy" />
            <?php endif; ?>
            <div class="lc-author-info">
                <span class="lc-author-label"><?php esc_html_e('Columna de', 'lean-columnas'); ?></span>
                <h2 class="lc-author-name">
                    <a href="<?php echo esc_url(get_author_posts_url($author_id)); ?>">
                        <?php echo esc_html($columnist['display_name']); ?>
                    </a>
                </h2>
                <?php if (!empty($columnist['bio'])) : ?>
                    <p class="lc-author-bio"><?php echo esc_html($columnist['bio']); ?></p>
                <?php endif; ?>
                <div class="lc-author-social">
                    <?php if (!empty($columnist['social_twitter'])) : ?>
                        <a href="<?php echo esc_url($columnist['social_twitter']); ?>" target="_blank" rel="noopener noreferrer">Twitter/X</a>
                    <?php endif; ?>
                    <?php if (!empty($columnist['social_linkedin'])) : ?>
                        <a href="<?php echo esc_url($columnist['social_linkedin']); ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a>
                    <?php endif; ?>
                    <?php if (!empty($columnist['social_instagram'])) : ?>
                        <a href="<?php echo esc_url($columnist['social_instagram']); ?>" target="_blank" rel="noopener noreferrer">Instagram</a>
                    <?php endif; ?>
                    <?php if (!empty($columnist['website_url'])) : ?>
                        <a href="<?php echo esc_url($columnist['website_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Web', 'lean-columnas'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
        <?php endif; ?>

        <div class="entry-content">
            <?php the_content(); ?>
        </div>

        <footer class="lc-column-footer">
            <div class="lc-disclaimer">
                <p>
                    <?php esc_html_e(
                        'Las opiniones expresadas en esta columna son responsabilidad exclusiva del autor y no representan necesariamente la posicion editorial de este medio.',
                        'lean-columnas'
                    ); ?>
                </p>
            </div>

            <?php if ($columnist !== null) : ?>
            <div class="lc-author-card lc-author-card--footer">
                <?php if (!empty($columnist['photo_url'])) : ?>
                    <img src="<?php echo esc_url($columnist['photo_url']); ?>"
                         alt="<?php echo esc_attr($columnist['display_name']); ?>"
                         class="lc-author-photo" width="48" height="48" loading="lazy" />
                <?php endif; ?>
                <div class="lc-author-info">
                    <h3 class="lc-author-name">
                        <a href="<?php echo esc_url(get_author_posts_url($author_id)); ?>">
                            <?php echo esc_html($columnist['display_name']); ?>
                        </a>
                    </h3>
                    <?php if (!empty($columnist['bio'])) : ?>
                        <p class="lc-author-bio"><?php echo esc_html($columnist['bio']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </footer>

    </article>

    <?php
    // Sidebar: author's columns (if any) + always latest columns.
    $author_max = 3;
    $latest_max = 5;
    $exclude_ids = [$post_id];

    // 1. Author's other columns.
    $author_columns = new WP_Query([
        'post_type'      => 'columna-opinion',
        'posts_per_page' => $author_max,
        'post_status'    => 'publish',
        'post__not_in'   => $exclude_ids,
        'author'         => $author_id,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    // Collect shown IDs to exclude from latest.
    if ($author_columns->have_posts()) {
        while ($author_columns->have_posts()) {
            $author_columns->the_post();
            $exclude_ids[] = get_the_ID();
        }
        $author_columns->rewind_posts();
    }

    // 2. Latest columns (always shown, excluding current + author's shown above).
    $latest_columns = new WP_Query([
        'post_type'      => 'columna-opinion',
        'posts_per_page' => $latest_max,
        'post_status'    => 'publish',
        'post__not_in'   => $exclude_ids,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $has_sidebar = $author_columns->have_posts() || $latest_columns->have_posts();

    if ($has_sidebar) :
    ?>
    <aside class="lc-sidebar">
        <?php if ($author_columns->have_posts()) : ?>
            <div class="lc-sidebar-section">
                <h2 class="lc-sidebar-title">
                    <?php printf(esc_html__('Mas de %s', 'lean-columnas'), esc_html(get_the_author())); ?>
                </h2>
                <?php
                while ($author_columns->have_posts()) :
                    $author_columns->the_post();
                    $rel_author = $columnist;
                    include LEAN_COLUMNAS_PATH . 'templates/parts/sidebar-card.php';
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        <?php endif; ?>

        <?php if ($latest_columns->have_posts()) : ?>
            <div class="lc-sidebar-section">
                <h2 class="lc-sidebar-title">
                    <?php esc_html_e('Ultimas columnas', 'lean-columnas'); ?>
                </h2>
                <?php
                while ($latest_columns->have_posts()) :
                    $latest_columns->the_post();
                    $rel_author = UserProfile::getColumnistData((int) get_the_author_meta('ID'));
                    include LEAN_COLUMNAS_PATH . 'templates/parts/sidebar-card.php';
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        <?php endif; ?>
    </aside>
    <?php endif; ?>

</div>

<?php
endwhile;

get_footer();
