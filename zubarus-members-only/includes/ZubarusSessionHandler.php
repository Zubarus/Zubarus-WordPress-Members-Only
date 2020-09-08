<?php

if (!defined('WPINC')) {
    die;
}

/**
 * DEBUGGING
 * Testing sessions.
 */
function zub_check_debug_session()
{
    if (!defined('WP_DEBUG') || WP_DEBUG !== true) {
        return;
    }

    if (empty($_GET['zub_set_session'])) {
        return;
    }

    /**
     * Set short session time and set another session
     * variable for debugging.
     */
    if (!isset($_SESSION['zubarus_member'])) {
        $_SESSION['zubarus_member'] = time() + 30;
        $_SESSION['zubarus_member_debug'] = true;
    }
}

/**
 * Returns the name of the "zubarus_member" (authenticated)
 * session variable for consistency's sake.
 *
 * @return string
 */
function zub_member_session_name()
{
    return 'zubarus_member';
}

/**
 * Returns the name of the "SMS sent" session variable
 *
 * @return string
 */
function zub_phone_sms_sent_name()
{
    return 'zubarus_phone_sms_sent';
}

/**
 * Verify if the session is still valid
 * and refresh it if it is.
 */
function zub_check_if_valid_session()
{
    $sessionName = zub_member_session_name();
    if (empty($_SESSION[$sessionName]) || isset($_SESSION['zubarus_member_debug'])) {
        return;
    }

    /**
     * If the session is in the future and the member is currently
     * navigating the page, we can extend it so it lasts one hour from $currentTime.
     *
     * This will allow the member to navigate pages actively practically forever,
     * but once they stop for one hour or more, they will have to re-authenticate.
     *
     * * Please note that this is not entirely accurate if the
     * * PHP configuration on the system has a shorter session lifetime.
     *
     * TODO: Allow admins to control this by themselves (options API).
     */
    if ($_SESSION[$sessionName] > time()) {
        $_SESSION[$sessionName] = time() + 3600;
        return;
    }

    /**
     * If it's in the past, unset it so it's no longer valid.
     */
    unset($_SESSION[$sessionName]);
}

/**
 * Start sessions
 */
function zub_register_session()
{
    if (!session_id()) {
        session_start();
    }

    zub_check_debug_session();
    zub_check_if_valid_session();
}

add_action('init', 'zub_register_session', 1);

/**
 * Checks for the POST request for the initial phone number prompt.
 */
function zub_check_phone_number_post()
{
    if (empty($_POST['zubarus_phone_number'])) {
        return;
    }

    $phoneNumber = $_POST['zubarus_phone_number'];
    $phoneResult = zub_verify_phone($phoneNumber);
    $_SESSION[zub_phone_sms_sent_name()] = $phoneResult['success'];
}
add_action('init', 'zub_check_phone_number_post', 1000);

/**
 * Checks the POST request for the pin verification.
 * Does nothing if `zub_verify_phone()` hasn't been called in the last 10 minutes.
 */
function zub_check_pin_verify_post()
{
    if (empty($_POST['zubarus_pin_verify'])) {
        return;
    }

    $pin = $_POST['zubarus_pin_verify'];
    $validPin = zub_verify_pin($pin);

    /**
     * Pin was invalid/expired.
     */
    if (!$validPin) {
        return;
    }

    /**
     * Make session valid for 1 hour.
     *
     * The function `zub_check_if_valid_session()` will extend
     * the session for up to an hour on each page load
     * if the current session is still valid.
     */
    unset($_SESSION[zub_phone_sms_sent_name()]);
    $sessionName = zub_member_session_name();
    $_SESSION[$sessionName] = time() . 3600;
}
add_action('init', 'zub_check_pin_verify_post', 1000);
