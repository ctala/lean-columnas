<?php
/**
 * Email notification handler for editorial workflow.
 *
 * Sends HTML email notifications when opinion columns transition
 * between editorial statuses. All notifications are filterable
 * via the `lean_columnas_send_notification` filter.
 *
 * @package LeanColumnas
 */

declare(strict_types=1);

namespace LeanColumnas\Editorial;

if (!defined('ABSPATH')) {
    exit;
}

class EmailNotifier
{
    /**
     * Human-readable status labels.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'draft'        => 'Draft',
        'lc_submitted' => 'Submitted',
        'lc_in_review' => 'In Review',
        'lc_approved'  => 'Approved',
        'lc_returned'  => 'Returned',
        'lc_rejected'  => 'Rejected',
        'publish'      => 'Published',
    ];

    /**
     * Register hooks for each status transition notification.
     */
    public function register(): void
    {
        add_action('lean_columnas_status_lc_submitted', [$this, 'onSubmitted'], 10, 2);
        add_action('lean_columnas_status_lc_in_review', [$this, 'onInReview'], 10, 2);
        add_action('lean_columnas_status_lc_approved', [$this, 'onApproved'], 10, 2);
        add_action('lean_columnas_status_lc_returned', [$this, 'onReturned'], 10, 2);
        add_action('lean_columnas_status_lc_rejected', [$this, 'onRejected'], 10, 2);
        add_action('lean_columnas_status_publish', [$this, 'onPublished'], 10, 2);
    }

    /**
     * Column submitted: notify editors who can review columns.
     *
     * @param \WP_Post $post       The column post.
     * @param string   $old_status Previous status.
     */
    public function onSubmitted(\WP_Post $post, string $old_status): void
    {
        $author = get_userdata((int) $post->post_author);
        if (!$author) {
            return;
        }

        $recipients = $this->getEditorEmails();
        if (empty($recipients)) {
            return;
        }

        $subject = sprintf(
            /* translators: 1: column title, 2: author display name */
            __('[Editorial] New column submitted: "%1$s" by %2$s', 'lean-columnas'),
            $post->post_title,
            $author->display_name
        );

        $body = $this->buildEmailBody($post, [
            'heading' => __('New Column Submitted for Review', 'lean-columnas'),
            'message' => sprintf(
                /* translators: 1: author display name, 2: column title */
                __('%1$s has submitted the column "%2$s" for editorial review.', 'lean-columnas'),
                $author->display_name,
                $post->post_title
            ),
            'status'  => 'lc_submitted',
        ]);

        $this->send('lc_submitted', $recipients, $subject, $body, $post);
    }

    /**
     * Column taken for review: notify the columnist author.
     *
     * @param \WP_Post $post       The column post.
     * @param string   $old_status Previous status.
     */
    public function onInReview(\WP_Post $post, string $old_status): void
    {
        $author_email = $this->getAuthorEmail($post);
        if ($author_email === null) {
            return;
        }

        $reviewer_name = $this->getReviewerName($post);

        $subject = sprintf(
            /* translators: %s: column title */
            __('[Editorial] Your column "%s" is being reviewed', 'lean-columnas'),
            $post->post_title
        );

        $body = $this->buildEmailBody($post, [
            'heading' => __('Your Column Is Being Reviewed', 'lean-columnas'),
            'message' => sprintf(
                /* translators: 1: column title, 2: reviewer display name */
                __('Your column "%1$s" has been taken for review by %2$s.', 'lean-columnas'),
                $post->post_title,
                $reviewer_name
            ),
            'status'  => 'lc_in_review',
        ]);

        $this->send('lc_in_review', [$author_email], $subject, $body, $post);
    }

    /**
     * Column approved: notify the columnist author.
     *
     * @param \WP_Post $post       The column post.
     * @param string   $old_status Previous status.
     */
    public function onApproved(\WP_Post $post, string $old_status): void
    {
        $author_email = $this->getAuthorEmail($post);
        if ($author_email === null) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: column title */
            __('[Editorial] Your column "%s" has been approved', 'lean-columnas'),
            $post->post_title
        );

        $body = $this->buildEmailBody($post, [
            'heading' => __('Your Column Has Been Approved', 'lean-columnas'),
            'message' => sprintf(
                /* translators: %s: column title */
                __('Congratulations! Your column "%s" has been approved and is ready for publication.', 'lean-columnas'),
                $post->post_title
            ),
            'status'       => 'lc_approved',
            'show_notes'   => true,
        ]);

        $this->send('lc_approved', [$author_email], $subject, $body, $post);
    }

    /**
     * Column returned: notify the columnist author with editorial notes.
     *
     * @param \WP_Post $post       The column post.
     * @param string   $old_status Previous status.
     */
    public function onReturned(\WP_Post $post, string $old_status): void
    {
        $author_email = $this->getAuthorEmail($post);
        if ($author_email === null) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: column title */
            __('[Editorial] Your column "%s" needs revisions', 'lean-columnas'),
            $post->post_title
        );

        $body = $this->buildEmailBody($post, [
            'heading' => __('Your Column Needs Revisions', 'lean-columnas'),
            'message' => sprintf(
                /* translators: %s: column title */
                __('Your column "%s" has been returned with editorial feedback. Please review the notes below and resubmit.', 'lean-columnas'),
                $post->post_title
            ),
            'status'     => 'lc_returned',
            'show_notes' => true,
        ]);

        $this->send('lc_returned', [$author_email], $subject, $body, $post);
    }

    /**
     * Column rejected: notify the columnist author with reason.
     *
     * @param \WP_Post $post       The column post.
     * @param string   $old_status Previous status.
     */
    public function onRejected(\WP_Post $post, string $old_status): void
    {
        $author_email = $this->getAuthorEmail($post);
        if ($author_email === null) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: column title */
            __('[Editorial] Your column "%s" was not accepted', 'lean-columnas'),
            $post->post_title
        );

        $body = $this->buildEmailBody($post, [
            'heading' => __('Your Column Was Not Accepted', 'lean-columnas'),
            'message' => sprintf(
                /* translators: %s: column title */
                __('Unfortunately, your column "%s" has not been accepted for publication. Please see the editorial notes below for details.', 'lean-columnas'),
                $post->post_title
            ),
            'status'     => 'lc_rejected',
            'show_notes' => true,
        ]);

        $this->send('lc_rejected', [$author_email], $subject, $body, $post);
    }

    /**
     * Column published: notify the columnist author with permalink.
     *
     * @param \WP_Post $post       The column post.
     * @param string   $old_status Previous status.
     */
    public function onPublished(\WP_Post $post, string $old_status): void
    {
        $author_email = $this->getAuthorEmail($post);
        if ($author_email === null) {
            return;
        }

        $permalink = get_permalink($post);

        $subject = sprintf(
            /* translators: %s: column title */
            __('[Editorial] Your column "%s" is now live!', 'lean-columnas'),
            $post->post_title
        );

        $extra_html = '';
        if ($permalink) {
            $extra_html = sprintf(
                '<p style="margin: 20px 0;"><a href="%s" style="display: inline-block; padding: 12px 24px; background-color: #2271b1; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: 600;">%s</a></p>',
                esc_url($permalink),
                esc_html__('View Published Column', 'lean-columnas')
            );
        }

        $body = $this->buildEmailBody($post, [
            'heading'    => __('Your Column Is Now Live!', 'lean-columnas'),
            'message'    => sprintf(
                /* translators: %s: column title */
                __('Your column "%s" has been published and is now available for readers.', 'lean-columnas'),
                $post->post_title
            ),
            'status'     => 'publish',
            'extra_html' => $extra_html,
        ]);

        $this->send('publish', [$author_email], $subject, $body, $post);
    }

    /**
     * Send an email notification after checking the allow filter.
     *
     * @param string   $status     The transition status triggering this notification.
     * @param string[] $recipients Array of email addresses.
     * @param string   $subject    Email subject line.
     * @param string   $body       HTML email body.
     * @param \WP_Post $post       The column post.
     */
    private function send(string $status, array $recipients, string $subject, string $body, \WP_Post $post): void
    {
        /**
         * Filter whether to send a specific notification.
         *
         * Return false to prevent the email from being sent.
         *
         * @param bool     $send       Whether to send the notification. Default true.
         * @param string   $status     The status that triggered this notification.
         * @param \WP_Post $post       The column post object.
         * @param string[] $recipients The email recipients.
         */
        $should_send = apply_filters('lean_columnas_send_notification', true, $status, $post, $recipients);

        if (!$should_send) {
            return;
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($recipients, $subject, $body, $headers);
    }

    /**
     * Build the HTML email body.
     *
     * @param \WP_Post             $post    The column post.
     * @param array<string, mixed> $args    Email content arguments.
     *
     * @return string HTML email body.
     */
    private function buildEmailBody(\WP_Post $post, array $args): string
    {
        $heading    = $args['heading'] ?? '';
        $message    = $args['message'] ?? '';
        $status     = $args['status'] ?? '';
        $show_notes = $args['show_notes'] ?? false;
        $extra_html = $args['extra_html'] ?? '';

        $status_label = self::STATUS_LABELS[$status] ?? $status;
        $edit_url     = get_edit_post_link($post->ID, 'raw');

        // Build editorial notes section.
        $notes_html = '';
        if ($show_notes) {
            $notes_html = $this->buildNotesHtml($post->ID);
        }

        // Status badge color.
        $badge_color = $this->getStatusColor($status);

        $site_name = get_bloginfo('name');

        $html = '<!DOCTYPE html>';
        $html .= '<html lang="' . esc_attr(get_locale()) . '">';
        $html .= '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
        $html .= '<body style="margin: 0; padding: 0; background-color: #f0f0f1; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, sans-serif;">';

        // Container.
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f0f1; padding: 40px 20px;">';
        $html .= '<tr><td align="center">';

        // Inner card.
        $html .= '<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';

        // Header.
        $html .= '<tr><td style="background-color: #1d2327; padding: 24px 32px;">';
        $html .= '<h1 style="margin: 0; color: #ffffff; font-size: 18px; font-weight: 600;">' . esc_html($site_name) . '</h1>';
        $html .= '<p style="margin: 4px 0 0; color: #a7aaad; font-size: 13px;">' . esc_html__('Editorial Workflow', 'lean-columnas') . '</p>';
        $html .= '</td></tr>';

        // Body.
        $html .= '<tr><td style="padding: 32px;">';

        // Heading.
        $html .= '<h2 style="margin: 0 0 16px; color: #1d2327; font-size: 20px; font-weight: 600;">' . esc_html($heading) . '</h2>';

        // Message.
        $html .= '<p style="margin: 0 0 24px; color: #50575e; font-size: 14px; line-height: 1.6;">' . esc_html($message) . '</p>';

        // Column info card.
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f6f7f7; border-radius: 6px; border: 1px solid #dcdcde;">';
        $html .= '<tr><td style="padding: 20px;">';

        // Title.
        $html .= '<p style="margin: 0 0 8px; color: #787c82; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">' . esc_html__('Column', 'lean-columnas') . '</p>';
        $html .= '<p style="margin: 0 0 16px; color: #1d2327; font-size: 16px; font-weight: 600;">' . esc_html($post->post_title) . '</p>';

        // Status badge.
        $html .= '<p style="margin: 0 0 8px; color: #787c82; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">' . esc_html__('Status', 'lean-columnas') . '</p>';
        $html .= '<span style="display: inline-block; padding: 4px 12px; background-color: ' . esc_attr($badge_color) . '; color: #ffffff; font-size: 12px; font-weight: 600; border-radius: 12px;">' . esc_html($status_label) . '</span>';

        $html .= '</td></tr>';
        $html .= '</table>';

        // Editorial notes.
        if ($notes_html !== '') {
            $html .= $notes_html;
        }

        // Extra HTML (e.g., view published link).
        if ($extra_html !== '') {
            $html .= $extra_html;
        }

        // Edit link button.
        if ($edit_url) {
            $html .= '<p style="margin: 24px 0 0;">';
            $html .= sprintf(
                '<a href="%s" style="display: inline-block; padding: 10px 20px; background-color: #2271b1; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 500;">%s</a>',
                esc_url($edit_url),
                esc_html__('Edit Column', 'lean-columnas')
            );
            $html .= '</p>';
        }

        $html .= '</td></tr>';

        // Footer.
        $html .= '<tr><td style="padding: 20px 32px; border-top: 1px solid #dcdcde; background-color: #f6f7f7;">';
        $html .= '<p style="margin: 0; color: #787c82; font-size: 12px; text-align: center;">';
        $html .= sprintf(
            /* translators: %s: site name */
            esc_html__('This is an automated notification from %s.', 'lean-columnas'),
            esc_html($site_name)
        );
        $html .= '</p>';
        $html .= '</td></tr>';

        $html .= '</table>';
        $html .= '</td></tr></table>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Build HTML for editorial notes section.
     *
     * @param int $post_id The column post ID.
     *
     * @return string HTML for notes section, or empty string if no notes.
     */
    private function buildNotesHtml(int $post_id): string
    {
        $notes = get_post_meta($post_id, '_lc_editorial_notes', true);
        if (!is_array($notes) || empty($notes)) {
            return '';
        }

        // Show only the most recent note.
        $latest = end($notes);
        $user   = get_userdata((int) ($latest['user_id'] ?? 0));
        $name   = $user ? $user->display_name : __('Editor', 'lean-columnas');
        $note   = $latest['note'] ?? '';
        $date   = $latest['created_at'] ?? '';

        if ($note === '') {
            return '';
        }

        $html  = '<div style="margin-top: 24px; padding: 16px 20px; background-color: #fcf9e8; border: 1px solid #dba617; border-radius: 6px;">';
        $html .= '<p style="margin: 0 0 8px; color: #787c82; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">' . esc_html__('Editorial Notes', 'lean-columnas') . '</p>';
        $html .= '<p style="margin: 0 0 8px; color: #1d2327; font-size: 14px; line-height: 1.6;">' . nl2br(esc_html($note)) . '</p>';
        $html .= '<p style="margin: 0; color: #787c82; font-size: 12px;">';
        $html .= esc_html($name);
        if ($date !== '') {
            $html .= ' &mdash; ' . esc_html($date);
        }
        $html .= '</p>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get the author's email address for a post.
     *
     * @param \WP_Post $post The post object.
     *
     * @return string|null Email address or null if not found.
     */
    private function getAuthorEmail(\WP_Post $post): ?string
    {
        $author = get_userdata((int) $post->post_author);
        return $author ? $author->user_email : null;
    }

    /**
     * Get display name of the reviewer assigned to a column.
     *
     * @param \WP_Post $post The column post.
     *
     * @return string Reviewer display name or generic fallback.
     */
    private function getReviewerName(\WP_Post $post): string
    {
        $reviewer_id = (int) get_post_meta($post->ID, '_lc_reviewer_id', true);
        if ($reviewer_id > 0) {
            $reviewer = get_userdata($reviewer_id);
            if ($reviewer) {
                return $reviewer->display_name;
            }
        }

        return __('an editor', 'lean-columnas');
    }

    /**
     * Get email addresses of all users who can review columns.
     *
     * @return string[] Array of email addresses.
     */
    private function getEditorEmails(): array
    {
        $users = get_users([
            'capability' => 'lc_review_columns',
            'fields'     => ['user_email'],
        ]);

        $emails = [];
        foreach ($users as $user) {
            if (!empty($user->user_email)) {
                $emails[] = $user->user_email;
            }
        }

        return $emails;
    }

    /**
     * Get the badge color for a given status.
     *
     * @param string $status The editorial status.
     *
     * @return string Hex color code.
     */
    private function getStatusColor(string $status): string
    {
        $colors = [
            'lc_submitted' => '#996800',
            'lc_in_review' => '#2271b1',
            'lc_approved'  => '#00a32a',
            'lc_returned'  => '#dba617',
            'lc_rejected'  => '#d63638',
            'publish'      => '#00a32a',
        ];

        return $colors[$status] ?? '#787c82';
    }
}
