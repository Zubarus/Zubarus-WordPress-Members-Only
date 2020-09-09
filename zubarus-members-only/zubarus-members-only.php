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
require __ZUBINC . '/ZubarusReplacements.php';

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

        /**
         * Add phone number verify form
         */
        $phoneForm = zub_form_text_verify_phone();

        /**
         * Phone number has already been submitted and SMS should have been sent
         * so we display the "Verify pin" form instead.
         *
         * See `ZubarusSessionHandler` for the phone number/pin checking behavior.
         *
         * TODO: Checking
         */
        $smsSessionName = zub_phone_sms_sent_name();
        $smsAttempt = isset($_SESSION[$smsSessionName]);

        if ($smsAttempt) {
            $smsResult = $_SESSION[$smsSessionName];
            $smsSent = $smsResult['success'];

            /**
             * SMS was successfully sent so we
             * display the verify pin form.
             */
            if ($smsSent) {
                $phoneNumber = $smsResult['phone'] ?? '';

                /**
                 * Translators:
                 * - '%s' is the user's phone number.
                 */
                $pinTranslation = sprintf(__('SMS with pin has been sent to phone number: %s', 'zubarus-members-only'), esc_html($phoneNumber));
                $smsValidTranslation = __('Keep in mind that the verification pin is only valid for 10 minutes.', 'zubarus-members-only');
                $phoneForm = sprintf('<p><strong>%s</strong><br />%s</p>', $pinTranslation, $smsValidTranslation);
                $phoneForm .= zub_form_text_verify_pin();
            }
            /**
             * SMS was attempted, but did not succeed.
             */
            else {
                $phoneForm = sprintf('<p><strong>%s</strong></p>%s', __('Unable to send SMS to the specified phone number. Make sure you typed the phone number correctly or try again later.', 'zubarus-members-only'), $phoneForm);
            }
        }

        /**
         * Replace the variable with the resulting form
         */
        $restricted = str_replace('{verify_phone_form}', $phoneForm, $restricted);

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
