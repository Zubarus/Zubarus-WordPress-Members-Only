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

/**
 * Used in the Wordpress Options API.
 */
$zub_options = [
    'pages' => [
        'id' => 'zubarus_members_only_pages',
        // Post/page IDs
        'default' => [],
    ],
    'pages_no_access' => [
        'id' => 'zubarus_no_access',
        'default' => '[Members-Only] You need to verify your membership to access this page.',
    ],
];

/**
 * Register options with default values if they don't exist.
 */
function zub_register_options()
{
    global $zub_options;
    
    foreach ($zub_options as $values)
    {
        $option = get_option($values['id'], false);

        if ($option !== false) {
            continue;
        }
        
        add_option($values['id'], $values['default']);
    }
}

/**
 * Make sure we can access session variables.
 */
function zub_register_session()
{
    if (!session_id()) {
        return;
    }

    session_start();
}

add_action('init', 'zub_register_session');

/**
 * Checks if the current visitor has verified their phone number
 * or they're a logged in user with Wordpress-editor access.
 *
 * @return bool
 */
function zub_can_see_post()
{
    if (!empty($_SESSION['zubarus_member'])) {
        return true;
    }

    return current_user_can('edit_posts');
}

/**
 * Replaces the page information with a "Members only" string
 * 
 * @return string
 */
function zub_can_see_page($input)
{
    if (!zub_can_see_post()) {
        return '[Members-Only] You are not allowed to access this page.';
    }
    
    return $input;
}

add_filter('the_content', 'zub_can_see_page');
add_filter('the_excerpt', 'zub_can_see_page');
add_filter('the_title', 'zub_can_see_page');