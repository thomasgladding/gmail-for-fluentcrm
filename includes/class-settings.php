<?php
/**
 * Admin settings and OAuth flow handling.
 *
 * @package Gmail_For_FluentCRM
 */

if (! defined('ABSPATH')) {
    exit;
}

class Gmail_For_FluentCRM_Settings
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

        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_gfcrm_oauth_callback', array($this, 'handle_oauth_callback'));
        add_action('admin_post_gfcrm_disconnect', array($this, 'handle_disconnect'));
    }

    /**
     * Register settings page under FluentCRM menu.
     *
     * @return void
     */
    public function register_menu()
    {
        $parent_slug = 'fluentcrm-admin';

        global $menu;
        $has_fluentcrm_menu = false;

        if (is_array($menu)) {
            foreach ($menu as $menu_item) {
                if (! empty($menu_item[2]) && 'fluentcrm-admin' === $menu_item[2]) {
                    $has_fluentcrm_menu = true;
                    break;
                }
            }
        }

        if (! $has_fluentcrm_menu) {
            $parent_slug = 'options-general.php';
        }

        add_submenu_page(
            $parent_slug,
            __('Gmail for FluentCRM', 'gmail-for-fluentcrm'),
            __('Gmail Integration', 'gmail-for-fluentcrm'),
            'manage_options',
            'gmail-for-fluentcrm',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public function register_settings()
    {
        register_setting(
            'gfcrm_settings',
            Gmail_For_FluentCRM_API::OPTION_ACCOUNTS,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this->gmail_api, 'sanitize_accounts_option'),
                'default'           => array(),
            )
        );

        register_setting(
            'gfcrm_settings',
            Gmail_For_FluentCRM_API::OPTION_CACHE_DURATION,
            array(
                'type'              => 'integer',
                'sanitize_callback' => array($this->gmail_api, 'sanitize_cache_duration'),
                'default'           => 15,
            )
        );

        register_setting(
            'gfcrm_settings',
            Gmail_For_FluentCRM_API::OPTION_EMAIL_LIMIT,
            array(
                'type'              => 'integer',
                'sanitize_callback' => array($this->gmail_api, 'sanitize_email_limit'),
                'default'           => 10,
            )
        );

        add_settings_section(
            'gfcrm_accounts_section',
            __('Gmail Accounts', 'gmail-for-fluentcrm'),
            array($this, 'render_accounts_section_intro'),
            'gfcrm_settings'
        );

        add_settings_field(
            Gmail_For_FluentCRM_API::OPTION_ACCOUNTS,
            __('Accounts', 'gmail-for-fluentcrm'),
            array($this, 'render_accounts_field'),
            'gfcrm_settings',
            'gfcrm_accounts_section'
        );

        add_settings_section(
            'gfcrm_display_section',
            __('Display and Cache', 'gmail-for-fluentcrm'),
            '__return_false',
            'gfcrm_settings'
        );

        add_settings_field(
            Gmail_For_FluentCRM_API::OPTION_CACHE_DURATION,
            __('Cache duration', 'gmail-for-fluentcrm'),
            array($this, 'render_cache_duration_field'),
            'gfcrm_settings',
            'gfcrm_display_section'
        );

        add_settings_field(
            Gmail_For_FluentCRM_API::OPTION_EMAIL_LIMIT,
            __('Emails per contact', 'gmail-for-fluentcrm'),
            array($this, 'render_email_limit_field'),
            'gfcrm_settings',
            'gfcrm_display_section'
        );
    }

    /**
     * Render section intro.
     *
     * @return void
     */
    public function render_accounts_section_intro()
    {
        echo '<p>' . esc_html__('Create one OAuth client in Google Cloud for each Gmail account you want to connect. Use this callback URL for each account:', 'gmail-for-fluentcrm') . '</p>';
        echo '<code>' . esc_html($this->gmail_api->get_callback_url()) . '</code>';
    }

    /**
     * Render accounts field.
     *
     * @return void
     */
    public function render_accounts_field()
    {
        $accounts = $this->gmail_api->get_accounts();

        $add_blank = isset($_GET['gfcrm_add_account']) ? absint($_GET['gfcrm_add_account']) : 0;
        if ($add_blank > 0) {
            for ($i = 0; $i < $add_blank; $i++) {
                $accounts['account_' . wp_generate_password(8, false, false)] = array(
                    'label'         => '',
                    'client_id'     => '',
                    'client_secret' => '',
                    'tokens'        => '',
                );
            }
        }

        if (empty($accounts)) {
            $accounts['account_' . wp_generate_password(8, false, false)] = array(
                'label'         => '',
                'client_id'     => '',
                'client_secret' => '',
                'tokens'        => '',
            );
        }

        echo '<table class="widefat striped" style="max-width: 1100px">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Label', 'gmail-for-fluentcrm') . '</th>';
        echo '<th>' . esc_html__('Client ID', 'gmail-for-fluentcrm') . '</th>';
        echo '<th>' . esc_html__('Client Secret', 'gmail-for-fluentcrm') . '</th>';
        echo '<th>' . esc_html__('Status', 'gmail-for-fluentcrm') . '</th>';
        echo '<th>' . esc_html__('Actions', 'gmail-for-fluentcrm') . '</th>';
        echo '<th>' . esc_html__('Remove', 'gmail-for-fluentcrm') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($accounts as $account_id => $account) {
            $label         = $account['label'] ?? '';
            $client_id     = $account['client_id'] ?? '';
            $client_secret = $account['client_secret'] ?? '';
            $tokens        = $account['tokens'] ?? '';

            $is_authorized = $this->gmail_api->is_account_authorized($account_id);
            $can_authorize = ! empty($client_id) && ! empty($client_secret);

            echo '<tr>';
            printf(
                '<td><input type="text" class="regular-text" name="%1$s[%2$s][label]" value="%3$s" placeholder="%4$s"/></td>',
                esc_attr(Gmail_For_FluentCRM_API::OPTION_ACCOUNTS),
                esc_attr($account_id),
                esc_attr($label),
                esc_attr__('sales@company.com', 'gmail-for-fluentcrm')
            );
            printf(
                '<td><input type="text" class="regular-text" name="%1$s[%2$s][client_id]" value="%3$s" autocomplete="off"/></td>',
                esc_attr(Gmail_For_FluentCRM_API::OPTION_ACCOUNTS),
                esc_attr($account_id),
                esc_attr($client_id)
            );
            printf(
                '<td><input type="password" class="regular-text" name="%1$s[%2$s][client_secret]" value="%3$s" autocomplete="new-password"/></td>',
                esc_attr(Gmail_For_FluentCRM_API::OPTION_ACCOUNTS),
                esc_attr($account_id),
                esc_attr($client_secret)
            );
            printf(
                '<input type="hidden" name="%1$s[%2$s][tokens]" value="%3$s"/>',
                esc_attr(Gmail_For_FluentCRM_API::OPTION_ACCOUNTS),
                esc_attr($account_id),
                esc_attr($tokens)
            );

            echo '<td>' . esc_html($is_authorized ? __('Connected', 'gmail-for-fluentcrm') : __('Not connected', 'gmail-for-fluentcrm')) . '</td>';
            echo '<td>';

            if ($can_authorize) {
                $auth_url = $this->build_authorize_url($account_id);
                if (! empty($auth_url)) {
                    printf(
                        '<a class="button button-secondary" href="%1$s">%2$s</a> ',
                        esc_url($auth_url),
                        esc_html($is_authorized ? __('Reconnect', 'gmail-for-fluentcrm') : __('Authorize', 'gmail-for-fluentcrm'))
                    );
                }
            } else {
                echo '<span class="description">' . esc_html__('Save credentials first', 'gmail-for-fluentcrm') . '</span> ';
            }

            if ($is_authorized) {
                $disconnect_url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'action'     => 'gfcrm_disconnect',
                            'account_id' => rawurlencode($account_id),
                        ),
                        admin_url('admin-post.php')
                    ),
                    'gfcrm_disconnect_' . $account_id
                );

                printf(
                    '<a class="button" href="%1$s">%2$s</a>',
                    esc_url($disconnect_url),
                    esc_html__('Disconnect', 'gmail-for-fluentcrm')
                );
            }

            echo '</td>';

            printf(
                '<td><label><input type="checkbox" name="%1$s[%2$s][remove]" value="1"/> %3$s</label></td>',
                esc_attr(Gmail_For_FluentCRM_API::OPTION_ACCOUNTS),
                esc_attr($account_id),
                esc_html__('Remove account', 'gmail-for-fluentcrm')
            );
            echo '</tr>';
        }

        echo '</tbody></table>';

        $add_url = add_query_arg(
            array(
                'page'              => 'gmail-for-fluentcrm',
                'gfcrm_add_account' => 1,
            ),
            admin_url('admin.php')
        );

        printf(
            '<p><a class="button" href="%1$s">%2$s</a></p>',
            esc_url($add_url),
            esc_html__('Add account row', 'gmail-for-fluentcrm')
        );
    }

    /**
     * Render cache duration field.
     *
     * @return void
     */
    public function render_cache_duration_field()
    {
        $value = $this->gmail_api->get_cache_duration_minutes();
        $name  = Gmail_For_FluentCRM_API::OPTION_CACHE_DURATION;

        foreach (array(5, 15, 30, 60) as $minutes) {
            printf(
                '<label style="margin-right: 15px;"><input type="radio" name="%1$s" value="%2$d" %3$s/> %4$s</label>',
                esc_attr($name),
                absint($minutes),
                checked($value, $minutes, false),
                esc_html(sprintf(__(' %d minutes', 'gmail-for-fluentcrm'), $minutes))
            );
        }
    }

    /**
     * Render email limit field.
     *
     * @return void
     */
    public function render_email_limit_field()
    {
        $value = $this->gmail_api->get_email_limit();
        $name  = Gmail_For_FluentCRM_API::OPTION_EMAIL_LIMIT;

        foreach (array(5, 10, 20, 50) as $limit) {
            printf(
                '<label style="margin-right: 15px;"><input type="radio" name="%1$s" value="%2$d" %3$s/> %4$s</label>',
                esc_attr($name),
                absint($limit),
                checked($value, $limit, false),
                esc_html(sprintf(_n('%d email', '%d emails', $limit, 'gmail-for-fluentcrm'), $limit))
            );
        }
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $status = isset($_GET['gfcrm_status']) ? sanitize_text_field(wp_unslash($_GET['gfcrm_status'])) : '';
        if (! empty($status)) {
            $this->render_status_notice($status);
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gmail for FluentCRM', 'gmail-for-fluentcrm') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('gfcrm_settings');
        do_settings_sections('gfcrm_settings');
        submit_button(__('Save Settings', 'gmail-for-fluentcrm'));
        echo '</form>';
        echo '</div>';
    }

    /**
     * Build OAuth authorization URL for account.
     *
     * @param string $account_id Account ID.
     * @return string
     */
    private function build_authorize_url($account_id)
    {
        $state = wp_generate_password(32, false);

        set_transient(
            'gfcrm_oauth_state_' . get_current_user_id() . '_' . $state,
            $account_id,
            10 * MINUTE_IN_SECONDS
        );

        return $this->gmail_api->get_authorize_url($account_id, $state);
    }

    /**
     * Handle OAuth callback.
     *
     * @return void
     */
    public function handle_oauth_callback()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'gmail-for-fluentcrm'));
        }

        $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
        if (! empty($error)) {
            $this->redirect_with_status('oauth_error');
        }

        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $code  = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';

        if (empty($state)) {
            $this->redirect_with_status('invalid_state');
        }

        $transient_key = 'gfcrm_oauth_state_' . get_current_user_id() . '_' . $state;
        $account_id    = get_transient($transient_key);

        if (empty($account_id)) {
            $this->redirect_with_status('invalid_state');
        }

        delete_transient($transient_key);

        if (empty($code)) {
            $this->redirect_with_status('missing_code');
        }

        $result = $this->gmail_api->exchange_code_for_tokens((string) $account_id, $code);

        if (is_wp_error($result)) {
            $this->redirect_with_status('token_failed');
        }

        $this->redirect_with_status('connected');
    }

    /**
     * Disconnect account and clear token.
     *
     * @return void
     */
    public function handle_disconnect()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'gmail-for-fluentcrm'));
        }

        $account_id = isset($_GET['account_id']) ? sanitize_key(wp_unslash($_GET['account_id'])) : '';

        if (empty($account_id)) {
            $this->redirect_with_status('disconnect_failed');
        }

        check_admin_referer('gfcrm_disconnect_' . $account_id);

        $this->gmail_api->disconnect_account($account_id);

        $this->redirect_with_status('disconnected');
    }

    /**
     * Render admin notice by status key.
     *
     * @param string $status Status.
     * @return void
     */
    private function render_status_notice($status)
    {
        $messages = array(
            'connected'       => array('success', __('Google account connected successfully.', 'gmail-for-fluentcrm')),
            'disconnected'    => array('success', __('Google account disconnected.', 'gmail-for-fluentcrm')),
            'disconnect_failed' => array('error', __('Unable to disconnect the selected account.', 'gmail-for-fluentcrm')),
            'oauth_error'     => array('error', __('Google authorization was cancelled or failed.', 'gmail-for-fluentcrm')),
            'invalid_state'   => array('error', __('Invalid OAuth state. Please try again.', 'gmail-for-fluentcrm')),
            'missing_code'    => array('error', __('Authorization code not found in callback.', 'gmail-for-fluentcrm')),
            'token_failed'    => array('error', __('Failed to save OAuth tokens. Please reconnect.', 'gmail-for-fluentcrm')),
        );

        if (empty($messages[$status])) {
            return;
        }

        list($type, $message) = $messages[$status];

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    /**
     * Redirect back to settings page with status.
     *
     * @param string $status Status query arg.
     * @return void
     */
    private function redirect_with_status($status)
    {
        wp_safe_redirect(
            add_query_arg(
                'gfcrm_status',
                rawurlencode($status),
                admin_url('admin.php?page=gmail-for-fluentcrm')
            )
        );
        exit;
    }
}
