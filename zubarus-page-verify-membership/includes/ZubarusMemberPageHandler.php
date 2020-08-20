<?php

if (!defined('WPINC')) {
    die;
}

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