<?php
/**
 * FluentCRM profile section integration.
 *
 * @package Gmail_For_FluentCRM
 */

if (! defined('ABSPATH')) {
    exit;
}

class Gmail_For_FluentCRM_Profile_Section
{
    /**
     * Gmail API instance.
     *
     * @var Gmail_For_FluentCRM_API
     */
    private $gmail_api;

    /**
     * Constructor.
     *
     * @param Gmail_For_FluentCRM_API $gmail_api Gmail API service.
     */
    public function __construct(Gmail_For_FluentCRM_API $gmail_api)
    {
        $this->gmail_api = $gmail_api;

        add_action('fluent_crm/after_init', array($this, 'register_profile_section'));
    }

    /**
     * Register custom FluentCRM profile section.
     *
     * @return void
     */
    public function register_profile_section()
    {
        FluentCrmApi('extender')->addProfileSection(
            'gfcrm_gmail',
            __('Gmail Emails', 'gmail-for-fluentcrm'),
            array($this, 'render_profile_section')
        );
    }

    /**
     * Build section payload expected by FluentCRM.
     *
     * @param array  $content_arr Existing section payload.
     * @param object $subscriber FluentCRM subscriber model.
     * @return array
     */
    public function render_profile_section($content_arr, $subscriber)
    {
        $content_arr['heading'] = __('Recent Gmail Correspondence', 'gmail-for-fluentcrm');

        $email = '';
        if (is_object($subscriber) && ! empty($subscriber->email)) {
            $email = sanitize_email($subscriber->email);
        }

        if (empty($email)) {
            $content_arr['content_html'] = $this->render_message(
                __('No contact email is available for this profile.', 'gmail-for-fluentcrm'),
                'warning'
            );

            return $content_arr;
        }

        if (! $this->gmail_api->is_authorized()) {
            $settings_url = admin_url('admin.php?page=gmail-for-fluentcrm');
            $content_arr['content_html'] = $this->render_message(
                sprintf(
                    /* translators: %s: settings page URL */
                    __('Google is not authorized. <a href="%s">Connect your account</a> to load emails.', 'gmail-for-fluentcrm'),
                    esc_url($settings_url)
                ),
                'warning',
                true
            );

            return $content_arr;
        }

        $emails = $this->gmail_api->get_recent_emails_for_contact($email, $this->gmail_api->get_email_limit());

        if (is_wp_error($emails)) {
            $content_arr['content_html'] = $this->render_message(
                __('Unable to load Gmail emails right now. Please verify authorization and try again later.', 'gmail-for-fluentcrm'),
                'error'
            );

            return $content_arr;
        }

        if (empty($emails)) {
            $content_arr['content_html'] = $this->render_message(
                __('No Gmail emails found for this contact.', 'gmail-for-fluentcrm'),
                'info'
            );

            return $content_arr;
        }

        $content_arr['content_html'] = $this->render_timeline($emails);

        return $content_arr;
    }

    /**
     * Render timeline HTML for Gmail messages.
     *
     * @param array $emails Email data list.
     * @return string
     */
    private function render_timeline(array $emails)
    {
        ob_start();
        ?>
        <style>
            .gfcrm-gmail-timeline {
                border-left: 2px solid #dcdcde;
                margin: 10px 0;
                padding: 0 0 0 18px;
            }
            .gfcrm-gmail-item {
                background: #fff;
                border: 1px solid #e2e4e7;
                border-radius: 6px;
                margin: 0 0 12px;
                padding: 12px;
                position: relative;
            }
            .gfcrm-gmail-item::before {
                background: #2271b1;
                border-radius: 50%;
                content: '';
                height: 10px;
                left: -24px;
                position: absolute;
                top: 16px;
                width: 10px;
            }
            .gfcrm-gmail-item.gfcrm-gmail-outgoing::before {
                background: #00a32a;
            }
            .gfcrm-gmail-date,
            .gfcrm-gmail-direction,
            .gfcrm-gmail-account {
                color: #646970;
                display: inline-block;
                font-size: 12px;
                margin-right: 8px;
            }
            .gfcrm-gmail-subject {
                display: block;
                font-size: 14px;
                margin: 8px 0 6px;
                text-decoration: none;
            }
            .gfcrm-gmail-meta {
                color: #50575e;
                font-size: 12px;
                margin: 0 0 6px;
            }
            .gfcrm-gmail-snippet {
                color: #1d2327;
                font-size: 13px;
                margin: 0;
            }
            .gfcrm-gmail-note {
                border-left: 4px solid #72aee6;
                margin: 0;
                padding: 10px 12px;
                background: #f0f6fc;
            }
            .gfcrm-gmail-note.error {
                border-color: #d63638;
                background: #fcf0f1;
            }
            .gfcrm-gmail-note.warning {
                border-color: #dba617;
                background: #fcf9e8;
            }
        </style>
        <div class="gfcrm-gmail-timeline">
            <?php foreach ($emails as $email) : ?>
                <?php
                $direction_class = (! empty($email['direction']) && 'outgoing' === $email['direction']) ? 'gfcrm-gmail-outgoing' : 'gfcrm-gmail-incoming';
                $direction_label = ('gfcrm-gmail-outgoing' === $direction_class)
                    ? __('→ To contact', 'gmail-for-fluentcrm')
                    : __('← From contact', 'gmail-for-fluentcrm');
                $formatted_date = ! empty($email['date'])
                    ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $email['date'])
                    : (! empty($email['date_raw']) ? $email['date_raw'] : __('Unknown date', 'gmail-for-fluentcrm'));
                ?>
                <div class="gfcrm-gmail-item <?php echo esc_attr($direction_class); ?>">
                    <span class="gfcrm-gmail-date"><?php echo esc_html($formatted_date); ?></span>
                    <span class="gfcrm-gmail-direction"><?php echo esc_html($direction_label); ?></span>
                    <?php if (! empty($email['account_label'])) : ?>
                        <span class="gfcrm-gmail-account"><?php echo esc_html(sprintf(__('Account: %s', 'gmail-for-fluentcrm'), (string) $email['account_label'])); ?></span>
                    <?php endif; ?>
                    <a class="gfcrm-gmail-subject" href="<?php echo esc_url($email['gmail_url']); ?>" target="_blank" rel="noopener noreferrer">
                        <strong><?php echo esc_html($email['subject']); ?></strong>
                    </a>
                    <p class="gfcrm-gmail-meta">
                        <?php echo esc_html(sprintf(__('From: %1$s | To: %2$s', 'gmail-for-fluentcrm'), (string) ($email['from'] ?? ''), (string) ($email['to'] ?? ''))); ?>
                    </p>
                    <p class="gfcrm-gmail-snippet"><?php echo esc_html($email['snippet'] ?? ''); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Render status message block.
     *
     * @param string $message Message.
     * @param string $type Type class.
     * @param bool   $allow_html Whether message contains safe HTML.
     * @return string
     */
    private function render_message($message, $type = 'info', $allow_html = false)
    {
        $message_markup = $allow_html
            ? wp_kses($message, array('a' => array('href' => array())))
            : esc_html($message);

        return sprintf(
            '<p class="gfcrm-gmail-note %1$s">%2$s</p>',
            esc_attr($type),
            $message_markup
        );
    }
}
