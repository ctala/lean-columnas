<?php
/**
 * OpinionNewsArticle schema markup.
 *
 * Outputs JSON-LD structured data on single column pages
 * for SEO and E-E-A-T compliance.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas\Schema;

use LeanColumnas\UserProfile;

if (!defined('ABSPATH')) {
    exit;
}

class OpinionArticleSchema
{
    /**
     * Output JSON-LD schema on single column pages.
     *
     * Hooked to `wp_head`.
     */
    public function output(): void
    {
        if (!is_singular('columna-opinion')) {
            return;
        }

        $post = get_post();
        if (!$post instanceof \WP_Post) {
            return;
        }

        $author_id = (int) $post->post_author;
        $columnist = UserProfile::getColumnistData($author_id);

        if ($columnist === null) {
            return;
        }

        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'OpinionNewsArticle',
            'headline'      => $post->post_title,
            'description'   => $post->post_excerpt ?: wp_trim_words($post->post_content, 30, '...'),
            'datePublished' => get_the_date('c', $post),
            'dateModified'  => get_the_modified_date('c', $post),
            'author'        => [
                '@type' => 'Person',
                'name'  => $columnist['display_name'],
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'url'   => home_url('/'),
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => get_permalink($post),
            ],
        ];

        // Author details.
        if (!empty($columnist['photo_url'])) {
            $schema['author']['image'] = $columnist['photo_url'];
        }

        if (!empty($columnist['bio'])) {
            $schema['author']['description'] = $columnist['bio'];
        }

        if (!empty($columnist['website_url'])) {
            $schema['author']['url'] = $columnist['website_url'];
        }

        $same_as = [];
        if (!empty($columnist['social_twitter'])) {
            $same_as[] = $columnist['social_twitter'];
        }
        if (!empty($columnist['social_linkedin'])) {
            $same_as[] = $columnist['social_linkedin'];
        }
        if (!empty($columnist['social_instagram'])) {
            $same_as[] = $columnist['social_instagram'];
        }
        if (!empty($same_as)) {
            $schema['author']['sameAs'] = $same_as;
        }

        // Featured image.
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $image_url = wp_get_attachment_url($thumbnail_id);
            if ($image_url) {
                $schema['image'] = $image_url;
            }
        }

        // Publisher logo.
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_url($custom_logo_id);
            if ($logo_url) {
                $schema['publisher']['logo'] = [
                    '@type' => 'ImageObject',
                    'url'   => $logo_url,
                ];
            }
        }

        echo '<script type="application/ld+json">';
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo '</script>' . "\n";
    }
}
