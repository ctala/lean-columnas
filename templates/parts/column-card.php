<?php
/**
 * Column card partial template.
 *
 * Used in archive pages and related columns sections.
 * Expects $rel_author to be set from the parent template.
 *
 * Override by copying to: your-theme/lean-columnas/parts/column-card.php
 *
 * @package LeanColumnas
 * @var array|null $rel_author Columnist data from UserProfile::getColumnistData()
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$card_categories = get_the_terms(get_the_ID(), 'columna-categoria');
?>
<article class="lc-card">
    <?php if (has_post_thumbnail()) : ?>
        <a href="<?php the_permalink(); ?>" class="lc-card-image-link">
            <?php the_post_thumbnail('medium_large', ['class' => 'lc-card-image']); ?>
        </a>
    <?php endif; ?>

    <div class="lc-card-body">
        <?php if (is_array($card_categories) && !empty($card_categories)) : ?>
            <div class="lc-card-categories">
                <?php foreach ($card_categories as $cat) : ?>
                    <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="lc-category-badge lc-category-badge--small">
                        <?php echo esc_html($cat->name); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h3 class="lc-card-title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <?php if (has_excerpt()) : ?>
            <p class="lc-card-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
        <?php endif; ?>

        <div class="lc-card-meta">
            <div class="lc-card-author">
                <?php if (isset($rel_author) && $rel_author !== null && !empty($rel_author['photo_url'])) : ?>
                    <img src="<?php echo esc_url($rel_author['photo_url']); ?>"
                         alt="<?php echo esc_attr($rel_author['display_name']); ?>"
                         class="lc-card-author-photo" width="28" height="28" loading="lazy" />
                <?php endif; ?>
                <span class="lc-card-author-name"><?php the_author(); ?></span>
            </div>
            <span class="lc-card-date"><?php echo esc_html(get_the_date()); ?></span>
        </div>
    </div>
</article>
