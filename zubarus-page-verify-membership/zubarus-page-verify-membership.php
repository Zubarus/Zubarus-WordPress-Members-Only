<?php
/**
 * Plugin Name: Zubarus - Verify Membership for Pages
 * Description: Verify that the visitor is a valid member before allowing access to a page.
 * Version: 1.0.0
 * Requires PHP: 7.2
 * Author: Zubarus AS
 * Author URI: https://zubarus.com/
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
    if (!empty($_SESSION['zubarus_member']) || isset($_GET['zubarus_override'])) {
        return true;
    }

    return current_user_can('edit_posts');
}

function zub_should_replace_text()
{
    $post = get_post();
    $postId = $post->ID;

    $restrictedPages = zub_get_option('pages');

    /**
     * Page is restricted.
     */
    if (in_array($postId, $restrictedPages)) {
        return true;
    }
    
    return false;
}

/**
 * Replaces the page information with a "Members only" string
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
add_filter('the_title', 'zub_replace_text');