<?php

if (!defined('WPINC')) {
    die;
}

/**
 * Helper function for options API to add
 * a post (via ID) to 'restricted pages'
 *
 * If a post that is already restricted
 * is provided, no changes occur.
 */
function zub_add_restricted_page($postId)
{
    $options = zub_get_option('pages');

    /**
     * Already restricted, no need to re-restrict.
     */
    if (in_array($postId, $options)) {
        return true;
    }

    $options[] = $postId;
    zub_update_option('pages', $options);
}

/**
 * Helper function for options API to remove
 * a post (via ID) from 'restricted pages'
 *
 * If a post that is not already restricted
 * is provided, no changes occur.
 */
function zub_del_restricted_page($postId)
{
    $options = zub_get_option('pages');

    /**
     * Not restricted, so there's nothing to remove.
     */
    if (!in_array($postId, $options)) {
        return true;
    }

    $key = array_search($postId, $options, true);
    if ($key !== false) {
        unset($options[$key]);
    }

    zub_update_option('pages', $options);
}
