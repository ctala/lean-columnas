<?php
/**
 * Editorial workflow manager.
 *
 * Handles status transitions for opinion columns, enforcing
 * quality gates and permission checks at each step.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas\Editorial;

use LeanColumnas\PostType;

if (!defined('ABSPATH')) {
    exit;
}

class WorkflowManager
{
    /**
     * Allowed status transitions.
     *
     * Maps current status => array of allowed next statuses.
     *
     * @var array<string, string[]>
     */
    private const TRANSITIONS = [
        'draft'         => ['lc_submitted'],
        'lc_submitted'  => ['lc_in_review', 'draft'],
        'lc_in_review'  => ['lc_approved', 'lc_returned', 'lc_rejected'],
        'lc_returned'   => ['lc_submitted', 'draft'],
        'lc_approved'   => ['publish', 'lc_in_review'],
        'lc_rejected'   => ['draft'],
        'publish'       => ['draft'],
    ];

    /**
     * Capabilities required for each target status.
     *
     * @var array<string, string>
     */
    private const REQUIRED_CAPS = [
        'lc_submitted' => 'lc_submit_column',
        'lc_in_review' => 'lc_review_columns',
        'lc_approved'  => 'lc_review_columns',
        'lc_returned'  => 'lc_review_columns',
        'lc_rejected'  => 'lc_review_columns',
        'publish'      => 'lc_publish_column',
    ];

    /**
     * Handle post status transitions.
     *
     * Hooked to `transition_post_status`.
     *
     * @param string   $new_status New post status.
     * @param string   $old_status Previous post status.
     * @param \WP_Post $post       The post object.
     */
    public function handleStatusTransition(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($post->post_type !== PostType::SLUG) {
            return;
        }

        if ($new_status === $old_status) {
            return;
        }

        // Quality gates are enforced pre-save via Plugin::enforceQualityGatesOnSave().
        // This hook only handles audit logging and extensibility actions.

        // Store the transition in post meta for audit trail.
        $this->logTransition($post->ID, $old_status, $new_status);

        // Fire custom action for extensibility.
        do_action('lean_columnas_status_transition', $new_status, $old_status, $post);
        do_action("lean_columnas_status_{$new_status}", $post, $old_status);
    }

    /**
     * Validate a status transition.
     *
     * @param string $from    Current status.
     * @param string $to      Desired status.
     * @param int    $user_id The user attempting the transition.
     *
     * @return true|\WP_Error True if valid, WP_Error if not.
     */
    public function validateTransition(string $from, string $to, int $user_id): true|\WP_Error
    {
        // Check if transition is allowed.
        $allowed = self::TRANSITIONS[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            return new \WP_Error(
                'lc_invalid_transition',
                sprintf(
                    /* translators: 1: current status, 2: target status */
                    __('Cannot transition from "%1$s" to "%2$s".', 'lean-columnas'),
                    $from,
                    $to
                ),
                ['status' => 400]
            );
        }

        // Check if user has required capability.
        $required_cap = self::REQUIRED_CAPS[$to] ?? null;
        if ($required_cap !== null && !user_can($user_id, $required_cap)) {
            return new \WP_Error(
                'lc_insufficient_permission',
                __('You do not have permission to perform this action.', 'lean-columnas'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Attempt to transition a column to a new status.
     *
     * Validates the transition, runs quality gates when needed, and updates the post.
     *
     * @param int    $post_id        The column post ID.
     * @param string $target_status  The desired status.
     * @param int    $user_id        The user performing the action.
     * @param string $notes          Optional editorial notes.
     *
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    public function transitionTo(int $post_id, string $target_status, int $user_id, string $notes = ''): true|\WP_Error
    {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post || $post->post_type !== PostType::SLUG) {
            return new \WP_Error(
                'lc_invalid_post',
                __('Column not found.', 'lean-columnas'),
                ['status' => 404]
            );
        }

        $current_status = $post->post_status;

        // Validate the transition.
        $valid = $this->validateTransition($current_status, $target_status, $user_id);
        if (is_wp_error($valid)) {
            return $valid;
        }

        // Run quality gates before submission.
        if ($target_status === 'lc_submitted') {
            $gates = new QualityGates();
            $result = $gates->validate($post);
            if (!$result['passed']) {
                return new \WP_Error(
                    'lc_quality_gates_failed',
                    __('Column does not meet quality requirements.', 'lean-columnas'),
                    [
                        'status'   => 422,
                        'failures' => $result['failures'],
                    ]
                );
            }
        }

        // Store editorial notes if provided.
        if ($notes !== '') {
            $this->addEditorialNote($post_id, $user_id, $target_status, $notes);
        }

        // Store reviewer/editor info.
        if (in_array($target_status, ['lc_in_review', 'lc_approved', 'lc_returned', 'lc_rejected'], true)) {
            update_post_meta($post_id, '_lc_reviewer_id', $user_id);
            update_post_meta($post_id, '_lc_reviewed_at', current_time('mysql'));
        }

        // Perform the transition.
        $result = wp_update_post([
            'ID'          => $post_id,
            'post_status' => $target_status,
        ], true);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Add an editorial note to a column.
     *
     * Notes are stored as a serialized array in post meta.
     *
     * @param int    $post_id The column post ID.
     * @param int    $user_id The user adding the note.
     * @param string $status  The status at time of note.
     * @param string $note    The note content.
     */
    public function addEditorialNote(int $post_id, int $user_id, string $status, string $note): void
    {
        $notes = get_post_meta($post_id, '_lc_editorial_notes', true);
        if (!is_array($notes)) {
            $notes = [];
        }

        $notes[] = [
            'user_id'    => $user_id,
            'status'     => $status,
            'note'       => sanitize_textarea_field($note),
            'created_at' => current_time('mysql'),
        ];

        update_post_meta($post_id, '_lc_editorial_notes', $notes);
    }

    /**
     * Get editorial notes for a column.
     *
     * @param int $post_id The column post ID.
     *
     * @return array<int, array{user_id: int, status: string, note: string, created_at: string}>
     */
    public function getEditorialNotes(int $post_id): array
    {
        $notes = get_post_meta($post_id, '_lc_editorial_notes', true);
        return is_array($notes) ? $notes : [];
    }

    /**
     * Log a status transition for audit trail.
     *
     * @param int    $post_id    The column post ID.
     * @param string $old_status Previous status.
     * @param string $new_status New status.
     */
    private function logTransition(int $post_id, string $old_status, string $new_status): void
    {
        $log = get_post_meta($post_id, '_lc_status_log', true);
        if (!is_array($log)) {
            $log = [];
        }

        $log[] = [
            'from'       => $old_status,
            'to'         => $new_status,
            'user_id'    => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ];

        update_post_meta($post_id, '_lc_status_log', $log);
    }

    /**
     * Get the status transition log for a column.
     *
     * @param int $post_id The column post ID.
     *
     * @return array<int, array{from: string, to: string, user_id: int, created_at: string}>
     */
    public function getStatusLog(int $post_id): array
    {
        $log = get_post_meta($post_id, '_lc_status_log', true);
        return is_array($log) ? $log : [];
    }

    /**
     * Get allowed transitions for a given status.
     *
     * @param string $status Current status.
     *
     * @return string[]
     */
    public static function getAllowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }
}
