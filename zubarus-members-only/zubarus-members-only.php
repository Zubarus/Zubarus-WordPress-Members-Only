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

require __DIR__ . '/includes/ZubarusOptions.php';
require __DIR__ . '/includes/ZubarusSessionHandler.php';
require __DIR__ . '/includes/ZubarusMemberPageHandler.php';
require __DIR__ . '/includes/ZubarusOptionsPage.php';

/**
 * Checks if the current visitor has verified their phone number
 * or they're a logged in user with Wordpress-editor access.
 *
 * @return bool
 */
function zub_can_see_post()
{
    // Allow debugging when `WP_DEBUG` is enabled.
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

/**
 * Load translations
 */
function zub_members_only_load_plugin_textdomain()
{
    load_plugin_textdomain('zubarus-members-only', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

add_action('plugins_loaded', 'zub_members_only_load_plugin_textdomain');

add_filter('the_content', 'zub_replace_text');
add_filter('the_excerpt', 'zub_replace_text');
add_filter('the_title', 'zub_replace_text');