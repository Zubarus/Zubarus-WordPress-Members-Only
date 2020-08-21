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
 * Verify if the session is still valid
 * and refresh it if it is.
 */
function zub_check_if_valid_session()
{
    if (empty($_SESSION['zubarus_member']) || isset($_SESSION['zubarus_member_debug'])) {
        return;
    }

    /**
     * If the session is in the future and the member is currently
     * navigating the page, we can extend it so it lasts one hour from $currentTime.
     *
     * This will allow the member to navigate pages actively practically forever,
     * but once they stop for one hour or more, they will have to re-authenticate.
     *
     * TODO: Allow admins to control this by themselves (options API).
     */
    if ($_SESSION['zubarus_member'] > time()) {
        $_SESSION['zubarus_member'] = time() + 3600;
        return;
    }

    /**
     * If it's in the past, unset it so it's no longer valid.
     */
    unset($_SESSION['zubarus_member']);
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

    session_write_close();
}

add_action('init', 'zub_register_session', 1);
