<?php
/**
 * Plugin Name: Gmail for FluentCRM
 * Plugin URI: https://github.com/thomasgladding/gmail-for-fluentcrm
 * Description: Display Gmail email history directly inside FluentCRM contact profiles. See every conversation with a contact without leaving WordPress.
 * Version: 1.0.0
 * Author: Gladding Digital
 * Author URI: https://gladdingdigital.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gmail-for-fluentcrm
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: fluent-crm
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('GFCRM_FILE')) {
    define('GFCRM_FILE', __FILE__);
}

if (! defined('GFCRM_PATH')) {
    define('GFCRM_PATH', plugin_dir_path(__FILE__));
}

if (! defined('GFCRM_URL')) {
    define('GFCRM_URL', plugin_dir_url(__FILE__));
}

require_once GFCRM_PATH . 'includes/class-gmail-api.php';
require_once GFCRM_PATH . 'includes/class-settings.php';
require_once GFCRM_PATH . 'includes/class-profile-section.php';

/**
 * Bootstrap plugin services.
 *
 * @return void
 */
function gfcrm_bootstrap_plugin()
{
    if (! function_exists('FluentCrmApi')) {
        return;
    }

    $gmail_api = new Gmail_For_FluentCRM_API();

    new Gmail_For_FluentCRM_Settings($gmail_api);
    new Gmail_For_FluentCRM_Profile_Section($gmail_api);
}
add_action('plugins_loaded', 'gfcrm_bootstrap_plugin');
