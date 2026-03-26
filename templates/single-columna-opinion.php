<?php
/**
 * Single column template.
 *
 * Displays a single opinion column with author info, content,
 * disclaimer, and related columns from the same author.
 *
 * Override this template by copying it to:
 * your-theme/lean-columnas/single-columna-opinion.php
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

    $categories = get_the_terms($post_id, 'columna-categoria');
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('lc-single-column'); ?>>

    <header class="lc-column-header">
        <?php if (is_array($categories) && !empty($categories)) : ?>
            <div class="lc-column-categories">
                <?php foreach ($categories as $cat) : ?>
                    <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="lc-category-badge">
                        <?php echo esc_html($cat->name); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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
                <?php
                printf(
                    /* translators: %d: estimated reading time in minutes */
                    esc_html__('%d min de lectura', 'lean-columnas'),
                    $read_time
                );
                ?>
            </span>
        </div>
    </header>

    <?php if ($columnist !== null) : ?>
    <aside class="lc-author-card">
        <?php if (!empty($columnist['photo_url'])) : ?>
            <img
                src="<?php echo esc_url($columnist['photo_url']); ?>"
                alt="<?php echo esc_attr($columnist['display_name']); ?>"
                class="lc-author-photo"
                width="80"
                height="80"
                loading="lazy"
            />
        <?php endif; ?>

        <div class="lc-author-info">
            <span class="lc-author-label"><?php esc_html_e('Columna de', 'lean-columnas'); ?></span>
            <h2 class="lc-author-name"><?php echo esc_html($columnist['display_name']); ?></h2>

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

    <?php if (has_post_thumbnail()) : ?>
    <figure class="lc-column-featured-image">
        <?php the_post_thumbnail('large'); ?>
        <?php
        $caption = get_the_post_thumbnail_caption();
        if (!empty($caption)) :
        ?>
            <figcaption><?php echo esc_html($caption); ?></figcaption>
        <?php endif; ?>
    </figure>
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
                     class="lc-author-photo" width="64" height="64" loading="lazy" />
            <?php endif; ?>
            <div class="lc-author-info">
                <h3 class="lc-author-name"><?php echo esc_html($columnist['display_name']); ?></h3>
                <?php if (!empty($columnist['bio'])) : ?>
                    <p class="lc-author-bio"><?php echo esc_html($columnist['bio']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </footer>

</article>

<?php
// Related columns from the same author.
$related_args = [
    'post_type'      => 'columna-opinion',
    'posts_per_page' => 3,
    'post_status'    => 'publish',
    'post__not_in'   => [$post_id],
    'author'         => $author_id,
    'orderby'        => 'date',
    'order'          => 'DESC',
];

$related_query = new WP_Query($related_args);

if ($related_query->have_posts()) :
?>
    <section class="lc-related-columns">
        <h2>
            <?php
            printf(
                /* translators: %s: columnist display name */
                esc_html__('Mas columnas de %s', 'lean-columnas'),
                esc_html(get_the_author())
            );
            ?>
        </h2>
        <div class="lc-columns-grid">
            <?php
            while ($related_query->have_posts()) :
                $related_query->the_post();
                $rel_author = UserProfile::getColumnistData((int) get_the_author_meta('ID'));
                include LEAN_COLUMNAS_PATH . 'templates/parts/column-card.php';
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
    </section>
<?php
endif;

endwhile;

get_footer();
