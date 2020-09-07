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

/**
 * Returns a "consistent" key based on phone number
 * that can be used with `transient` functions.
 *
 * @return string
 */
function zub_phone_cache_key($phone)
{
    return 'zubarus_member_phone_pin_' . sha1($phone);
}

/**
 * Sends an SMS to the specified phone number via
 * the Zubarus API.
 *
 * The SMS contains a pin that the user is supposed to use to
 * verify that it's their number.
 *
 * Keep in mind that the number also needs to be tied to a member of the organization.
 *
 * @param string $phone User's phone number
 * @return array `success` field (boolean) indicates if the request was successful or not.
 */
function zub_verify_phone($phone)
{
    $apiUser = zub_get_option('api_username');
    $apiPass = zub_get_option('api_password');

    $result = ['success' => false];
    if (empty($apiUser) || empty($apiPass)) {
        $result['error'] = 'API Username/Password not specified';
        return $result;
    }

    $api = new ZubarusApiHandler($apiUser, $apiPass);

    $response = $api->verifyPhoneNumber($phone);
    $data = $response['data'] ?? [];
    if ($response['success'] === false) {
        /**
         * `message` field usually contains an error message.
         */
        if (isset($data['message'])) {
            $result['message'] = $data['message'];
        }

        return $result;
    }

    /**
     * Empty data, unknown error.
     */
    if (empty($data)) {
        return $result;
    }

    $data = $data['data'];

    /**
     * Make sure we cache pin.
     *
     * Pins are valid for 10 minutes (600 seconds)
     * so we cache them for the same length.
     */
    $pin = $data['pin'];
    set_transient(zub_phone_cache_key($phone), $pin, 600);

    $result['pin'] = $pin;
    $result['success'] = true;
    return $result;
}

/**
 * Checks the Wordpress cache (transient API) for
 * a pin tied to the specified phone number.
 * If one exists, compare it to the specified `$pin` value.
 *
 * The third parameter `$deleteCacheOnSuccess` will delete the
 * cached pin after a successful verification of the input pin.
 *
 * This function assumes that `zub_verify_phone()`
 * has been used first, since it sends the SMS and caches the pin.
 *
 * @param string $phone
 * @param string $pin
 * @param bool   $deleteCacheOnSuccess Delete the cached pin if the specified pin is valid AND matches.
 * @return bool `true` if pin exists and matches the input value, `false` if not.
 */
function zub_verify_pin($phone, $pin, $deleteCacheOnSuccess = true)
{
    $cacheKey = zub_phone_cache_key($phone);
    $cachedPin = get_transient($cacheKey);

    /**
     * Pin expired or does not exist.
     */
    if ($cachedPin === false) {
        return false;
    }

    /**
     * Check if pin matches.
     */
    $validPin = $cachedPin === $pin;
    if (!$validPin) {
        return false;
    }

    /**
     * Delete the cached pin.
     */
    if ($deleteCacheOnSuccess) {
        delete_transient($cacheKey);
    }

    return true;
}
