<?php

if (!defined('WPINC')) {
    die;
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
     * navigating the page, we can extend it so it lasts $currentTime + $inactivityLimit.
     *
     * Default: 1 hour (3600 seconds)
     *
     * This will allow the member to navigate pages actively practically forever,
     * but once they stop for $inactivityLimit or more, they will have to re-authenticate.
     *
     * * Please note that this is not entirely accurate if the
     * * PHP configuration on the system has a shorter session lifetime.
     *
     * TODO: Allow admins to control this by themselves (options API).
     */
    $inactivityLimit = zub_get_option('session_inactivity');
    $currentTime = time();
    if ($_SESSION[$sessionName] > $currentTime) {
        $_SESSION[$sessionName] = $currentTime + $inactivityLimit;
        return;
    }

    /**
     * If it's in the past, unset it so it's no longer valid.
     */
    unset($_SESSION[$sessionName]);
}

/**
 * Start sessions if they haven't already been started.
 */
function zub_register_session()
{
    if (!session_id()) {
        session_start();
    }

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
    $_SESSION[zub_phone_sms_sent_name()] = $phoneResult;
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
     * Clear the session variable for the API response
     * as we no longer need it when the pin has been validated.
     */
    unset($_SESSION[zub_phone_sms_sent_name()]);

    /**
     * Make session valid up until the inactivity limit (default 1 hour).
     *
     * The function `zub_check_if_valid_session()` will extend
     * the session for up to $inactivityLimit (seconds) on each page load
     * if the current session is still valid.
     */
    $sessionName = zub_member_session_name();
    $inactivityLimit = zub_get_option('session_inactivity');
    $_SESSION[$sessionName] = time() + $inactivityLimit;
}
add_action('init', 'zub_check_pin_verify_post', 1000);
