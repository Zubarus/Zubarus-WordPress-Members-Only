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
 * Returns a "consistent" key stored in the PHP session
 * that can be used with `transient` functions for the
 * phone/pin verification.
 *
 * "Consistent" means that the key is stored in the session of the user
 * and returned if it exists.
 * If it does not exist a
 *
 * @return string
 */
function zub_phone_cache_key()
{
    $sessionName = 'zubarus_phone_pin_id';

    /**
     * Return the session value if it exists.
     */
    if (!empty($_SESSION[$sessionName])) {
        return $_SESSION[$sessionName];
    }

    /**
     * Generate a 'random' ID.
     * We don't really need this to be super secure
     * but we definitely need it to be unique.
     *
     * `random_bytes(16)` combined with `bin2hex()` should generate
     * a 32-character long ID that is unique (at least for this usecase).
     */
    $newId = bin2hex(random_bytes(16));
    $_SESSION[$sessionName] = $newId;
    return $newId;
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
    set_transient(zub_phone_cache_key(), $pin, 600);

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
 * @param string $pin
 * @param bool   $deleteCacheOnSuccess Delete the cached pin if the specified pin is valid AND matches.
 * @return bool `true` if pin exists and matches the input value, `false` if not.
 */
function zub_verify_pin($pin, $deleteCacheOnSuccess = true)
{
    $cacheKey = zub_phone_cache_key();
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
