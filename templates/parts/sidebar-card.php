<?php
/**
 * Sidebar column card — compact version for the sidebar.
 *
 * Override by copying to: your-theme/lean-columnas/parts/sidebar-card.php
 *
 * @package LeanColumnas
 * @var array|null $rel_author Columnist data from UserProfile::getColumnistData()
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<article class="lc-sidebar-card">
    <h3 class="lc-sidebar-card-title">
        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
    </h3>
    <div class="lc-sidebar-card-meta">
        <?php if (isset($rel_author) && $rel_author !== null && !empty($rel_author['photo_url'])) : ?>
            <img src="<?php echo esc_url($rel_author['photo_url']); ?>"
                 alt="<?php echo esc_attr($rel_author['display_name']); ?>"
                 class="lc-sidebar-card-photo" width="24" height="24" loading="lazy" />
        <?php endif; ?>
        <span class="lc-sidebar-card-author"><?php the_author(); ?></span>
        <span class="lc-sidebar-card-date"><?php echo esc_html(get_the_date()); ?></span>
    </div>
</article>
