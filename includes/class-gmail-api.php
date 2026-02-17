<?php
/**
 * Gmail API client for OAuth, token management, and message retrieval.
 *
 * @package Gmail_For_FluentCRM
 */

if (! defined('ABSPATH')) {
    exit;
}

class Gmail_For_FluentCRM_API
{
    const OPTION_ACCOUNTS       = 'gfcrm_accounts';
    const OPTION_CACHE_DURATION = 'gfcrm_cache_duration';
    const OPTION_EMAIL_LIMIT    = 'gfcrm_email_limit';

    const GOOGLE_AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
    const GOOGLE_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    const GMAIL_API_BASE        = 'https://gmail.googleapis.com/gmail/v1/users/me';
    const SCOPE                 = 'https://www.googleapis.com/auth/gmail.readonly';

    /**
     * Get OAuth callback URL.
     *
     * @return string
     */
    public function get_callback_url()
    {
        return admin_url('admin-ajax.php?action=gfcrm_oauth_callback');
    }

    /**
     * Get configured cache duration in minutes.
     *
     * @return int
     */
    public function get_cache_duration_minutes()
    {
        $value = absint(get_option(self::OPTION_CACHE_DURATION, 15));

        return in_array($value, array(5, 15, 30, 60), true) ? $value : 15;
    }

    /**
     * Get configured email limit per contact.
     *
     * @return int
     */
    public function get_email_limit()
    {
        $value = absint(get_option(self::OPTION_EMAIL_LIMIT, 10));

        return in_array($value, array(5, 10, 20, 50), true) ? $value : 10;
    }

    /**
     * Sanitize cache duration option.
     *
     * @param mixed $value Option value.
     * @return int
     */
    public function sanitize_cache_duration($value)
    {
        $minutes = absint($value);

        return in_array($minutes, array(5, 15, 30, 60), true) ? $minutes : 15;
    }

    /**
     * Sanitize email limit option.
     *
     * @param mixed $value Option value.
     * @return int
     */
    public function sanitize_email_limit($value)
    {
        $limit = absint($value);

        return in_array($limit, array(5, 10, 20, 50), true) ? $limit : 10;
    }

    /**
     * Get all configured Gmail accounts.
     *
     * @return array
     */
    public function get_accounts()
    {
        $accounts = get_option(self::OPTION_ACCOUNTS, array());

        if (! is_array($accounts)) {
            return array();
        }

        $normalized = array();

        foreach ($accounts as $account_id => $account) {
            if (! is_array($account)) {
                continue;
            }

            $normalized_id = sanitize_key((string) $account_id);
            if ('' === $normalized_id) {
                continue;
            }

            $normalized[$normalized_id] = array(
                'label'         => sanitize_text_field($account['label'] ?? ''),
                'client_id'     => sanitize_text_field($account['client_id'] ?? ''),
                'client_secret' => sanitize_text_field($account['client_secret'] ?? ''),
                'tokens'        => is_string($account['tokens'] ?? '') ? $account['tokens'] : '',
            );
        }

        return $normalized;
    }

    /**
     * Sanitize account option payload.
     *
     * @param mixed $value Raw option value.
     * @return array
     */
    public function sanitize_accounts_option($value)
    {
        if (! is_array($value)) {
            return array();
        }

        $accounts = array();

        foreach ($value as $account_id => $account) {
            if (! is_array($account)) {
                continue;
            }

            $remove = ! empty($account['remove']);
            if ($remove) {
                continue;
            }

            $normalized_id = sanitize_key((string) $account_id);
            if ('' === $normalized_id) {
                $normalized_id = 'account_' . wp_generate_password(8, false, false);
            }

            $label         = sanitize_text_field($account['label'] ?? '');
            $client_id     = sanitize_text_field($account['client_id'] ?? '');
            $client_secret = sanitize_text_field($account['client_secret'] ?? '');
            $tokens        = is_string($account['tokens'] ?? '') ? $account['tokens'] : '';

            if ('' === $label && '' === $client_id && '' === $client_secret && '' === $tokens) {
                continue;
            }

            $accounts[$normalized_id] = array(
                'label'         => $label,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'tokens'        => $tokens,
            );
        }

        return $accounts;
    }

    /**
     * Check if a specific account is authorized.
     *
     * @param string $account_id Account ID.
     * @return bool
     */
    public function is_account_authorized($account_id)
    {
        $tokens = $this->get_account_tokens($account_id);

        return is_array($tokens) && ! empty($tokens['refresh_token']);
    }

    /**
     * Check whether any account is authorized.
     *
     * @return bool
     */
    public function is_authorized()
    {
        foreach ($this->get_accounts() as $account_id => $account) {
            unset($account);
            if ($this->is_account_authorized($account_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build Google OAuth authorize URL for an account.
     *
     * @param string $account_id Account ID.
     * @param string $state OAuth state parameter.
     * @return string
     */
    public function get_authorize_url($account_id, $state)
    {
        $account = $this->get_account($account_id);

        if (! is_array($account) || empty($account['client_id']) || empty($account['client_secret'])) {
            return '';
        }

        $params = array(
            'client_id'              => $account['client_id'],
            'redirect_uri'           => $this->get_callback_url(),
            'response_type'          => 'code',
            'scope'                  => self::SCOPE,
            'access_type'            => 'offline',
            'prompt'                 => 'consent',
            'include_granted_scopes' => 'true',
            'state'                  => $state,
        );

        return add_query_arg($params, self::GOOGLE_AUTH_ENDPOINT);
    }

    /**
     * Exchange OAuth code for tokens.
     *
     * @param string $account_id Account ID.
     * @param string $code Authorization code.
     * @return true|WP_Error
     */
    public function exchange_code_for_tokens($account_id, $code)
    {
        $account = $this->get_account($account_id);

        if (! is_array($account)) {
            return new WP_Error('gfcrm_account_not_found', __('Selected Gmail account was not found.', 'gmail-for-fluentcrm'));
        }

        $response = wp_remote_post(
            self::GOOGLE_TOKEN_ENDPOINT,
            array(
                'timeout' => 20,
                'body'    => array(
                    'code'          => $code,
                    'client_id'     => $account['client_id'],
                    'client_secret' => $account['client_secret'],
                    'redirect_uri'  => $this->get_callback_url(),
                    'grant_type'    => 'authorization_code',
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) >= 400 || empty($body['access_token'])) {
            $message = ! empty($body['error_description']) ? $body['error_description'] : __('Token exchange failed.', 'gmail-for-fluentcrm');

            return new WP_Error('gfcrm_token_exchange_failed', $message, $body);
        }

        $tokens = array(
            'access_token'  => sanitize_text_field($body['access_token']),
            'refresh_token' => ! empty($body['refresh_token']) ? sanitize_text_field($body['refresh_token']) : '',
            'expires_at'    => time() + max(60, absint($body['expires_in'] ?? 3600)) - 30,
            'scope'         => sanitize_text_field($body['scope'] ?? ''),
            'token_type'    => sanitize_text_field($body['token_type'] ?? 'Bearer'),
        );

        if (empty($tokens['refresh_token'])) {
            $existing = $this->get_account_tokens($account_id);
            if (is_array($existing) && ! empty($existing['refresh_token'])) {
                $tokens['refresh_token'] = $existing['refresh_token'];
            }
        }

        if (empty($tokens['refresh_token'])) {
            return new WP_Error('gfcrm_missing_refresh_token', __('Missing refresh token. Reconnect and grant offline access.', 'gmail-for-fluentcrm'));
        }

        return $this->save_tokens($account_id, $tokens);
    }

    /**
     * Search and fetch recent correspondence with a contact across all authorized accounts.
     *
     * @param string   $email Contact email.
     * @param int|null $limit Max number of messages.
     * @return array|WP_Error
     */
    public function get_recent_emails_for_contact($email, $limit = null)
    {
        $email = sanitize_email($email);

        if (empty($email)) {
            return new WP_Error('gfcrm_invalid_email', __('Contact email is invalid.', 'gmail-for-fluentcrm'));
        }

        $limit = (null === $limit) ? $this->get_email_limit() : max(1, absint($limit));

        $authorized_accounts = array();
        foreach ($this->get_accounts() as $account_id => $account) {
            if ($this->is_account_authorized($account_id)) {
                $authorized_accounts[$account_id] = $account;
            }
        }

        if (empty($authorized_accounts)) {
            return new WP_Error('gfcrm_not_authorized', __('No Gmail accounts are authorized yet.', 'gmail-for-fluentcrm'));
        }

        $cache_key = $this->get_contact_cache_key($email, $limit, array_keys($authorized_accounts));
        $cached    = get_transient($cache_key);

        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        $results_by_id = array();

        foreach ($authorized_accounts as $account_id => $account) {
            $account_results = $this->get_recent_emails_for_contact_for_account($account_id, $account, $email, $limit);

            if (is_wp_error($account_results)) {
                continue;
            }

            foreach ($account_results as $message) {
                if (empty($message['id'])) {
                    continue;
                }

                $message_id = (string) $message['id'];

                if (! isset($results_by_id[$message_id])) {
                    $results_by_id[$message_id] = $message;
                    continue;
                }

                $existing_date = (int) ($results_by_id[$message_id]['date'] ?? 0);
                $current_date  = (int) ($message['date'] ?? 0);

                if ($current_date > $existing_date) {
                    $results_by_id[$message_id] = $message;
                }
            }
        }

        $results = array_values($results_by_id);

        usort(
            $results,
            static function ($a, $b) {
                return (int) ($b['date'] ?? 0) <=> (int) ($a['date'] ?? 0);
            }
        );

        $results = array_slice($results, 0, $limit);

        set_transient($cache_key, $results, $this->get_cache_duration_minutes() * MINUTE_IN_SECONDS);

        return $results;
    }

    /**
     * Clear all cached message entries.
     *
     * @return void
     */
    public function clear_cache()
    {
        global $wpdb;

        $sql = "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s";
        $wpdb->query($wpdb->prepare($sql, '_transient_gfcrm_%', '_transient_timeout_gfcrm_%')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Disconnect a specific account and clear cache.
     *
     * @param string $account_id Account ID.
     * @return void
     */
    public function disconnect_account($account_id)
    {
        $accounts = $this->get_accounts();

        if (! isset($accounts[$account_id])) {
            return;
        }

        $accounts[$account_id]['tokens'] = '';
        update_option(self::OPTION_ACCOUNTS, $accounts, false);

        $this->clear_cache();
    }

    /**
     * Perform Gmail GET request.
     *
     * @param string $path API path.
     * @param array  $query Query parameters.
     * @param string $token Access token.
     * @return array|WP_Error
     */
    private function gmail_get($path, array $query, $token)
    {
        $url = $this->build_gmail_url($path, $query);

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $message = ! empty($body['error']['message']) ? $body['error']['message'] : __('Gmail API request failed.', 'gmail-for-fluentcrm');

            return new WP_Error('gfcrm_api_error', $message, $body);
        }

        return is_array($body) ? $body : array();
    }

    /**
     * Build Gmail API URL with query args.
     *
     * Ensures metadataHeaders can be passed as repeated query keys.
     *
     * @param string $path API path.
     * @param array  $query Query params.
     * @return string
     */
    private function build_gmail_url($path, array $query)
    {
        $base_url = self::GMAIL_API_BASE . $path;

        $metadata_headers = array();
        if (isset($query['metadataHeaders']) && is_array($query['metadataHeaders'])) {
            $metadata_headers = $query['metadataHeaders'];
            unset($query['metadataHeaders']);
        }

        $url = add_query_arg($query, $base_url);

        if (! empty($metadata_headers)) {
            $separator = (false === strpos($url, '?')) ? '?' : '&';
            foreach ($metadata_headers as $header) {
                $url      .= $separator . 'metadataHeaders=' . rawurlencode((string) $header);
                $separator = '&';
            }
        }

        return $url;
    }

    /**
     * Parse Gmail message headers.
     *
     * @param array $headers Header list.
     * @return array
     */
    private function parse_headers(array $headers)
    {
        $parsed = array();

        foreach ($headers as $header) {
            if (empty($header['name'])) {
                continue;
            }

            $name          = strtolower((string) $header['name']);
            $parsed[$name] = (string) ($header['value'] ?? '');
        }

        return $parsed;
    }

    /**
     * Get a valid access token for an account and refresh if needed.
     *
     * @param string $account_id Account ID.
     * @return string|WP_Error
     */
    private function get_valid_access_token($account_id)
    {
        $tokens = $this->get_account_tokens($account_id);

        if (! is_array($tokens) || empty($tokens['access_token']) || empty($tokens['refresh_token'])) {
            return new WP_Error('gfcrm_not_authorized', __('Google account is not authorized yet.', 'gmail-for-fluentcrm'));
        }

        if (! empty($tokens['expires_at']) && time() < absint($tokens['expires_at'])) {
            return (string) $tokens['access_token'];
        }

        $refreshed = $this->refresh_access_token($account_id, (string) $tokens['refresh_token']);

        if (is_wp_error($refreshed)) {
            return $refreshed;
        }

        return (string) $refreshed;
    }

    /**
     * Fetch contact emails for one account.
     *
     * @param string $account_id Account ID.
     * @param array  $account Account config.
     * @param string $email Contact email.
     * @param int    $limit Message limit.
     * @return array|WP_Error
     */
    private function get_recent_emails_for_contact_for_account($account_id, array $account, $email, $limit)
    {
        $token = $this->get_valid_access_token($account_id);
        if (is_wp_error($token)) {
            return $token;
        }

        $query = sprintf('from:%1$s OR to:%1$s', $email);

        $list_response = $this->gmail_get(
            '/messages',
            array(
                'q'          => $query,
                'maxResults' => max(1, absint($limit)),
            ),
            $token
        );

        if (is_wp_error($list_response)) {
            return $list_response;
        }

        $messages = ! empty($list_response['messages']) && is_array($list_response['messages']) ? $list_response['messages'] : array();

        if (empty($messages)) {
            return array();
        }

        $results = array();
        foreach ($messages as $message) {
            if (empty($message['id'])) {
                continue;
            }

            $detail = $this->gmail_get(
                '/messages/' . rawurlencode($message['id']),
                array(
                    'format'          => 'metadata',
                    'metadataHeaders' => array('From', 'To', 'Subject', 'Date'),
                ),
                $token
            );

            if (is_wp_error($detail)) {
                continue;
            }

            $headers = $this->parse_headers($detail['payload']['headers'] ?? array());

            $from_header = $headers['from'] ?? '';
            $to_header   = $headers['to'] ?? '';
            $subject     = $headers['subject'] ?? __('(No Subject)', 'gmail-for-fluentcrm');
            $date_header = $headers['date'] ?? '';
            $timestamp   = ! empty($date_header) ? strtotime($date_header) : 0;
            $is_outgoing = (false === stripos($from_header, $email));

            $results[] = array(
                'id'            => sanitize_text_field($detail['id'] ?? ''),
                'thread_id'     => sanitize_text_field($detail['threadId'] ?? ''),
                'subject'       => wp_strip_all_tags($subject),
                'from'          => wp_strip_all_tags($from_header),
                'to'            => wp_strip_all_tags($to_header),
                'date'          => $timestamp,
                'date_raw'      => wp_strip_all_tags($date_header),
                'snippet'       => wp_strip_all_tags($detail['snippet'] ?? ''),
                'direction'     => $is_outgoing ? 'outgoing' : 'incoming',
                'gmail_url'     => 'https://mail.google.com/mail/#all/' . rawurlencode((string) (! empty($detail['threadId']) ? $detail['threadId'] : ($detail['id'] ?? ''))),
                'account_label' => wp_strip_all_tags($account['label'] ?? ''),
            );
        }

        return $results;
    }

    /**
     * Refresh access token from refresh token.
     *
     * @param string $account_id Account ID.
     * @param string $refresh_token Refresh token.
     * @return string|WP_Error
     */
    private function refresh_access_token($account_id, $refresh_token)
    {
        $account = $this->get_account($account_id);

        if (! is_array($account)) {
            return new WP_Error('gfcrm_account_not_found', __('Selected Gmail account was not found.', 'gmail-for-fluentcrm'));
        }

        $response = wp_remote_post(
            self::GOOGLE_TOKEN_ENDPOINT,
            array(
                'timeout' => 20,
                'body'    => array(
                    'client_id'     => $account['client_id'],
                    'client_secret' => $account['client_secret'],
                    'refresh_token' => $refresh_token,
                    'grant_type'    => 'refresh_token',
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) >= 400 || empty($body['access_token'])) {
            $message = ! empty($body['error_description']) ? $body['error_description'] : __('Unable to refresh access token.', 'gmail-for-fluentcrm');

            return new WP_Error('gfcrm_refresh_failed', $message, $body);
        }

        $tokens = $this->get_account_tokens($account_id);
        if (! is_array($tokens)) {
            $tokens = array();
        }

        $tokens['access_token'] = sanitize_text_field($body['access_token']);
        $tokens['expires_at']   = time() + max(60, absint($body['expires_in'] ?? 3600)) - 30;

        if (! empty($body['refresh_token'])) {
            $tokens['refresh_token'] = sanitize_text_field($body['refresh_token']);
        }

        $saved = $this->save_tokens($account_id, $tokens);

        if (is_wp_error($saved)) {
            return $saved;
        }

        return (string) $tokens['access_token'];
    }

    /**
     * Save encrypted token payload for an account.
     *
     * @param string $account_id Account ID.
     * @param array  $tokens Tokens.
     * @return true|WP_Error
     */
    public function save_tokens($account_id, array $tokens)
    {
        $accounts = $this->get_accounts();

        if (! isset($accounts[$account_id])) {
            return new WP_Error('gfcrm_account_not_found', __('Selected Gmail account was not found.', 'gmail-for-fluentcrm'));
        }

        $encrypted = $this->encrypt_payload($tokens);

        if (is_wp_error($encrypted)) {
            return $encrypted;
        }

        $accounts[$account_id]['tokens'] = $encrypted;

        update_option(self::OPTION_ACCOUNTS, $accounts, false);
        $this->clear_cache();

        return true;
    }

    /**
     * Get one account by ID.
     *
     * @param string $account_id Account ID.
     * @return array|null
     */
    private function get_account($account_id)
    {
        $accounts = $this->get_accounts();

        return $accounts[$account_id] ?? null;
    }

    /**
     * Get decrypted token payload for an account.
     *
     * @param string $account_id Account ID.
     * @return array|null
     */
    private function get_account_tokens($account_id)
    {
        $account = $this->get_account($account_id);

        if (! is_array($account) || empty($account['tokens']) || ! is_string($account['tokens'])) {
            return null;
        }

        $decrypted = $this->decrypt_payload($account['tokens']);

        return is_array($decrypted) ? $decrypted : null;
    }

    /**
     * Build transient cache key for contact lookup.
     *
     * @param string $email Contact email.
     * @param int    $limit Message limit.
     * @param array  $account_ids Account IDs.
     * @return string
     */
    private function get_contact_cache_key($email, $limit, array $account_ids)
    {
        sort($account_ids);

        $key_source = strtolower($email) . '|' . absint($limit) . '|' . implode(',', $account_ids);

        return 'gfcrm_' . md5($key_source);
    }

    /**
     * Encrypt an array payload with wp_salt.
     *
     * @param array $payload Data to encrypt.
     * @return string|WP_Error
     */
    private function encrypt_payload(array $payload)
    {
        if (! function_exists('openssl_encrypt')) {
            return new WP_Error('gfcrm_no_openssl', __('OpenSSL extension is required to encrypt OAuth tokens.', 'gmail-for-fluentcrm'));
        }

        $json = wp_json_encode($payload);
        if (false === $json) {
            return new WP_Error('gfcrm_encode_failed', __('Failed to encode token payload.', 'gmail-for-fluentcrm'));
        }

        $method = 'aes-256-cbc';
        $iv_len = openssl_cipher_iv_length($method);
        $iv     = random_bytes($iv_len);
        $key    = hash('sha256', wp_salt('auth'), true);

        $ciphertext = openssl_encrypt($json, $method, $key, OPENSSL_RAW_DATA, $iv);
        if (false === $ciphertext) {
            return new WP_Error('gfcrm_encrypt_failed', __('Failed to encrypt token payload.', 'gmail-for-fluentcrm'));
        }

        return base64_encode(
            wp_json_encode(
                array(
                    'iv'   => base64_encode($iv),
                    'data' => base64_encode($ciphertext),
                )
            )
        );
    }

    /**
     * Decrypt token payload.
     *
     * @param string $encrypted Encrypted payload.
     * @return array|WP_Error
     */
    private function decrypt_payload($encrypted)
    {
        if (! function_exists('openssl_decrypt')) {
            return new WP_Error('gfcrm_no_openssl', __('OpenSSL extension is required to decrypt OAuth tokens.', 'gmail-for-fluentcrm'));
        }

        $decoded = json_decode(base64_decode($encrypted), true);

        if (! is_array($decoded) || empty($decoded['iv']) || empty($decoded['data'])) {
            return new WP_Error('gfcrm_decrypt_invalid', __('Stored token payload is invalid.', 'gmail-for-fluentcrm'));
        }

        $iv         = base64_decode((string) $decoded['iv']);
        $ciphertext = base64_decode((string) $decoded['data']);
        $key        = hash('sha256', wp_salt('auth'), true);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if (false === $plaintext) {
            return new WP_Error('gfcrm_decrypt_failed', __('Failed to decrypt token payload.', 'gmail-for-fluentcrm'));
        }

        $payload = json_decode($plaintext, true);

        return is_array($payload) ? $payload : new WP_Error('gfcrm_decrypt_json', __('Token payload JSON is invalid.', 'gmail-for-fluentcrm'));
    }
}
