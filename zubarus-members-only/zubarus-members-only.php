<?php
/**
 * Plugin Name: Zubarus - Members Only
 * Description: Restrict access to pages & posts to members registered via Zubarus.
 * Version: 1.0.0
 * Requires PHP: 7.2
 * Author: Zubarus AS
 * Author URI: https://zubarus.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Zubarus Includes
 */
define('__ZUBINC', __DIR__ . '/includes');
require __ZUBINC . '/ZubarusOptions.php';
require __ZUBINC . '/ZubarusSessionHandler.php';
require __ZUBINC . '/ZubarusMemberPageHandler.php';
require __ZUBINC . '/ZubarusOptionsPage.php';
require __ZUBINC . '/ZubarusApiHandler.php';

/**
 * Debugging-only
 */
function zub_debug_check_restrict()
{
    if (!defined('WP_DEBUG') || WP_DEBUG !== true || !current_user_can('edit_posts')) {
        return;
    }

    if (empty($_GET['zub_phone'])) {
        return;
    }

    $phone = '47634677';
    if (empty($_GET['zub_pin'])) {
        zub_verify_phone($phone);
    }
    else {
        if (zub_verify_pin($phone, $_GET['zub_pin'])) {
            echo '<p style="color: green;">Hooray!</p>';
        }
        else {
            echo '<p style="color: red;">Invalid pin!</p>';
        }
    }
}

add_action('init', 'zub_debug_check_restrict', 1000);

/**
 * Checks if the current visitor has verified their phone number
 * or they're a logged in user with Wordpress-editor access.
 *
 * @return bool
 */
function zub_can_see_post()
{
    // Allow debugging when `WP_DEBUG` is enabled, but only then.
    $allowOverride = defined('WP_DEBUG') && WP_DEBUG === true;
    $shouldOverride = $allowOverride && isset($_GET['zubarus_override']);

    if (!empty($_SESSION['zubarus_member']) || $shouldOverride) {
        return true;
    }

    return current_user_can('edit_posts');
}

/**
 * Returns whether or not a page is restricted
 * and if the text should be replaced for guests.
 *
 * @return bool
 */
function zub_should_replace_text()
{
    $post = get_post();
    $postId = $post->ID;

    $restrictedPages = zub_get_option('pages');

    if (in_array($postId, $restrictedPages)) {
        return true;
    }

    return false;
}

/**
 * Replaces the page information with a "Members only" string
 * for guests that haven't verified their membership.
 *
 * @return string
 */
function zub_replace_text($input)
{
    if (!zub_should_replace_text()) {
        return $input;
    }

    if (!zub_can_see_post()) {
        $restricted = zub_get_option('pages_no_access');
        return $restricted;
    }

    return $input;
}
add_filter('the_content', 'zub_replace_text');
add_filter('the_excerpt', 'zub_replace_text');

/**
 * Load translations
 */
function zub_members_only_load_plugin_textdomain()
{
    load_plugin_textdomain('zubarus-members-only', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'zub_members_only_load_plugin_textdomain');

/**
 * Plugin uninstallation
 * Delete all options from database
 *
 * This only happens when the plugin is DELETED
 * not when it's just deactivated.
 */
function zub_delete_plugin_handler()
{
    $options = ZubarusOptions::getDefaultOptions();

    foreach ($options as $option)
    {
        $name = $option['name'];
        delete_option($name);
    }
}
register_uninstall_hook(__FILE__, 'zub_delete_plugin_handler');
