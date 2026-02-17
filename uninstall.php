<?php
/**
 * Uninstall Gmail for FluentCRM.
 *
 * @package Gmail_For_FluentCRM
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('gfcrm_accounts');
delete_option('gfcrm_cache_duration');
delete_option('gfcrm_email_limit');

delete_option('gd_fcrm_gmail_client_id');
delete_option('gd_fcrm_gmail_client_secret');
delete_option('gd_fcrm_gmail_tokens');

global $wpdb;

if (isset($wpdb) && isset($wpdb->options)) {
    $sql = "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s";
    $wpdb->query(
        $wpdb->prepare(
            $sql,
            '_transient_gfcrm_%',
            '_transient_timeout_gfcrm_%',
            '_transient_gd_fcrm_gmail_%',
            '_transient_timeout_gd_fcrm_gmail_%'
        )
    ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}
