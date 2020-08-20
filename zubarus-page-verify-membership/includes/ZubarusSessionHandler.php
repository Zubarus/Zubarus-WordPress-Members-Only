<?php

if (!defined('WPINC')) {
    die;
}

/**
 * Make sure we can access session variables.
 */
function zub_check_debug_session()
{
    if (empty($_GET['zub_set_session'])) {
        return;
    }

    /**
     * Check if session exists at all, if not, set to current time
     * plus expiry time. As of right now, each session is valid for one hour,
     * but will be automatically extended if the member is actively navigating the page.
     */
    if (!isset($_SESSION['zubarus_member'])) {
        $_SESSION['zubarus_member'] = time() + 3600;
    }
}

function zub_check_if_valid_session()
{
    if (empty($_SESSION['zubarus_member'])) {
        return;
    }

    /**
     * If the session is in the future and the member is currently
     * navigating the page, we can extend it so it lasts one hour from $currentTime.
     * 
     * This will allow the member to navigate pages actively practically forever,
     * but once they stop for one hour or more, they will have to re-authenticate.
     * 
     * TODO: Allow customers to control this by themselves (wp_options).
     */
    if ($_SESSION['zubarus_member'] > time()) {
        $_SESSION['zubarus_member'] = time() + 3600;
    }

    /**
     * If it's in the past, unset it so it's no longer valid.
     */
    unset($_SESSION['zubarus_member']);
}

function zub_register_session()
{
    if (!session_id()) {
        session_start();
        zub_check_debug_session();
    }

    zub_check_if_valid_session();
    
    session_write_close();
}

add_action('init', 'zub_register_session', 1);