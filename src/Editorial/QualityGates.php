<?php
/**
 * Content quality gates for opinion columns.
 *
 * Validates column content against editorial requirements
 * before allowing submission to the review workflow.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas\Editorial;

if (!defined('ABSPATH')) {
    exit;
}

class QualityGates
{
    /**
     * Minimum word count for a column.
     */
    public const MIN_WORDS = 600;

    /**
     * Maximum word count for a column.
     */
    public const MAX_WORDS = 3000;

    /**
     * Minimum title length in characters.
     */
    public const MIN_TITLE_LENGTH = 10;

    /**
     * Maximum title length in characters.
     */
    public const MAX_TITLE_LENGTH = 70;

    /**
     * Minimum number of subheadings (h2/h3) required.
     */
    public const MIN_SUBHEADINGS = 2;

    /**
     * Validate a column against all quality gates.
     *
     * @param \WP_Post $post The column post to validate.
     *
     * @return array{passed: bool, checks: array<string, array{passed: bool, message: string}>, failures: string[]}
     */
    /**
     * Gates that block submission if failed.
     */
    private const BLOCKING_GATES = ['word_count', 'title', 'sanitization'];

    /**
     * Gates that generate warnings but don't block submission.
     */
    private const WARNING_GATES = ['excerpt', 'subheadings'];

    /**
     * Validate a column against all quality gates.
     *
     * @param \WP_Post $post The column post to validate.
     *
     * @return array{passed: bool, checks: array<string, array{passed: bool, message: string}>, failures: string[], warnings: string[]}
     */
    public function validate(\WP_Post $post): array
    {
        $checks = [
            'word_count'     => $this->checkWordCount($post),
            'title'          => $this->checkTitle($post),
            'excerpt'        => $this->checkExcerpt($post),
            'subheadings'    => $this->checkSubheadings($post),
            'sanitization'   => $this->checkSanitization($post),
        ];

        $failures = [];
        $warnings = [];
        $passed = true;

        foreach ($checks as $key => $check) {
            if (!$check['passed']) {
                if (in_array($key, self::WARNING_GATES, true)) {
                    $warnings[] = $check['message'];
                } else {
                    $passed = false;
                    $failures[] = $check['message'];
                }
            }
        }

        return [
            'passed'   => $passed,
            'checks'   => $checks,
            'failures' => $failures,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check that the word count is within acceptable range.
     *
     * @param \WP_Post $post The column post.
     *
     * @return array{passed: bool, message: string, value: int}
     */
    public function checkWordCount(\WP_Post $post): array
    {
        $content = wp_strip_all_tags($post->post_content);
        $word_count = str_word_count($content);

        if ($word_count < self::MIN_WORDS) {
            return [
                'passed'  => false,
                'message' => sprintf(
                    /* translators: 1: current word count, 2: minimum required */
                    __('Column has %1$d words. Minimum required: %2$d.', 'lean-columnas'),
                    $word_count,
                    self::MIN_WORDS
                ),
                'value'   => $word_count,
            ];
        }

        if ($word_count > self::MAX_WORDS) {
            return [
                'passed'  => false,
                'message' => sprintf(
                    /* translators: 1: current word count, 2: maximum allowed */
                    __('Column has %1$d words. Maximum allowed: %2$d.', 'lean-columnas'),
                    $word_count,
                    self::MAX_WORDS
                ),
                'value'   => $word_count,
            ];
        }

        return [
            'passed'  => true,
            'message' => sprintf(
                /* translators: %d: word count */
                __('Word count: %d. OK.', 'lean-columnas'),
                $word_count
            ),
            'value'   => $word_count,
        ];
    }

    /**
     * Check that the title exists and meets length requirements.
     *
     * @param \WP_Post $post The column post.
     *
     * @return array{passed: bool, message: string}
     */
    public function checkTitle(\WP_Post $post): array
    {
        $title = trim($post->post_title);
        $length = mb_strlen($title);

        if ($length === 0) {
            return [
                'passed'  => false,
                'message' => __('Title is required.', 'lean-columnas'),
            ];
        }

        if ($length < self::MIN_TITLE_LENGTH) {
            return [
                'passed'  => false,
                'message' => sprintf(
                    /* translators: 1: current length, 2: minimum required */
                    __('Title is %1$d characters. Minimum required: %2$d.', 'lean-columnas'),
                    $length,
                    self::MIN_TITLE_LENGTH
                ),
            ];
        }

        if ($length > self::MAX_TITLE_LENGTH) {
            return [
                'passed'  => false,
                'message' => sprintf(
                    /* translators: 1: current length, 2: maximum allowed */
                    __('Title is %1$d characters. Maximum allowed: %2$d.', 'lean-columnas'),
                    $length,
                    self::MAX_TITLE_LENGTH
                ),
            ];
        }

        return [
            'passed'  => true,
            'message' => __('Title length is OK.', 'lean-columnas'),
        ];
    }

    /**
     * Check that an excerpt is provided.
     *
     * @param \WP_Post $post The column post.
     *
     * @return array{passed: bool, message: string}
     */
    public function checkExcerpt(\WP_Post $post): array
    {
        $excerpt = trim($post->post_excerpt);

        if ($excerpt === '') {
            return [
                'passed'  => false,
                'message' => __('Excerpt is required. Provide a brief summary of the column.', 'lean-columnas'),
            ];
        }

        return [
            'passed'  => true,
            'message' => __('Excerpt provided. OK.', 'lean-columnas'),
        ];
    }

    /**
     * Check that the content has at least the minimum required subheadings.
     *
     * Counts h2 and h3 tags in the content.
     *
     * @param \WP_Post $post The column post.
     *
     * @return array{passed: bool, message: string, value: int}
     */
    public function checkSubheadings(\WP_Post $post): array
    {
        $content = $post->post_content;

        // Count h2 and h3 tags.
        $count = preg_match_all('/<h[23][^>]*>/i', $content);

        if ($count === false) {
            $count = 0;
        }

        if ($count < self::MIN_SUBHEADINGS) {
            return [
                'passed'  => false,
                'message' => sprintf(
                    /* translators: 1: current count, 2: minimum required */
                    __('Column has %1$d subheadings (h2/h3). Minimum required: %2$d.', 'lean-columnas'),
                    $count,
                    self::MIN_SUBHEADINGS
                ),
                'value'   => $count,
            ];
        }

        return [
            'passed'  => true,
            'message' => sprintf(
                /* translators: %d: subheading count */
                __('Subheadings: %d. OK.', 'lean-columnas'),
                $count
            ),
            'value'   => $count,
        ];
    }

    /**
     * Check that the content does not contain disallowed HTML tags.
     *
     * Blocks script and iframe tags for security.
     *
     * @param \WP_Post $post The column post.
     *
     * @return array{passed: bool, message: string}
     */
    public function checkSanitization(\WP_Post $post): array
    {
        $content = $post->post_content;

        $disallowed_patterns = [
            '/<script[^>]*>/i'  => 'script',
            '/<iframe[^>]*>/i'  => 'iframe',
            '/<object[^>]*>/i'  => 'object',
            '/<embed[^>]*>/i'   => 'embed',
            '/<applet[^>]*>/i'  => 'applet',
        ];

        $found = [];
        foreach ($disallowed_patterns as $pattern => $tag) {
            if (preg_match($pattern, $content)) {
                $found[] = $tag;
            }
        }

        if (!empty($found)) {
            return [
                'passed'  => false,
                'message' => sprintf(
                    /* translators: %s: comma-separated list of disallowed tags */
                    __('Content contains disallowed HTML tags: %s.', 'lean-columnas'),
                    implode(', ', $found)
                ),
            ];
        }

        return [
            'passed'  => true,
            'message' => __('Content sanitization check passed.', 'lean-columnas'),
        ];
    }
}
