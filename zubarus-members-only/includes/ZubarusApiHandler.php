<?php

if (!defined('WPINC')) {
    die;
}

class ZubarusApiHandler
{
    /**
     * API username used for generating access token.
     *
     * @var string
     */
    protected $apiUsername;

    /**
     * API password used for generating access token.
     *
     * @var string
     */
    protected $apiPassword;

    /**
     * Base URL for the Zubarus V2 API.
     *
     * @var string
     */
    protected $baseUrl = 'https://zubarus.com/api/v2';

    /**
     * The key name used for referencing
     * the access token via Wordpress'
     * "Transient" API.
     *
     * https://developer.wordpress.org/apis/handbook/transients/
     *
     * @var string
     */
    protected $transientAccessToken = 'zubarus_api_access_token';

    /**
     * How long access tokens are valid for (in seconds).
     *
     * @var integer
     */
    protected $tokenLifetime = 600;

    /**
     * Initialize class for Zubarus V2 API.
     *
     * @param string $apiUsername
     * @param string $apiPassword
     * @param bool   $getToken Attempts to get/generate a token via `getToken()`.
     */
    public function __construct($apiUsername, $apiPassword, $getToken = true)
    {
        $this->apiUsername = $apiUsername;
        $this->apiPassword = $apiPassword;

        if ($getToken) {
            /**
             * The function will check if a token is cached
             * and generate one if necessary.
             */
            $this->getToken();
        }
    }

    /**
     * Retrieve the current active token.
     *
     * @return string
     */
    public function getToken()
    {
        $transient = get_transient($this->transientAccessToken);

        /**
         * Still a valid token.
         */
        if ($transient !== false) {
            return $transient;
        }

        /**
         * Generate a new access token
         */
        return $this->generateToken();
    }

    /**
     * (Re-)generate access token and set it.
     *
     * @return string|bool Token (string) on success, `false` (bool) if it fails.
     */
    public function generateToken()
    {
        $fields = [
            'userName' => $this->apiUsername,
            'password' => $this->apiPassword,
        ];

        $result = $this->post('/access-token', $fields);

        if ($result['success'] === true) {
            $token = $result['data']['data']['token'];
            set_transient($this->transientAccessToken, $token, $this->tokenLifetime);
            return $token;
        }

        return false;
    }

    /**
     * Sends a HTTP request to the Zubarus V2 API.
     *
     * @param string $method What HTTP method should we send this as.
     * @param string $path API path suffix, without base URL
     * @param array $fields Query parameters (GET) or post data (POST).
     * @param array $headers Associate array of HTTP headers, e.g.: 'Content-Type' => 'application/json'
     *
     * @return mixed|bool|string Raw response on success, false on failure.
     */
    public function request($method = 'GET', $path = '', $fields = [], $headers = [])
    {
        $method = strtoupper($method);
        $ch = curl_init();

        /**
         * Append GET query parameters to URL
         * unless we're POSTing the request.
         */
        $query = null;
        if ($method === 'GET' && !empty($fields)) {
            $query = '?' . http_build_query($fields);
        }

        /**
         * Request methods that supply data for addition/updating.
         */
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            /**
             * Specify content-type header if it's not already specified.
             * Generally these requests should be sent as JSON, but headers
             * may differ depending on "type of JSON".
             */
            if (empty($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/json';
            }

            /**
             * Include data in the POST/PUT/etc body.
             */
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        }

        /**
         * Make sure the access token is sent with the request.
         *
         * We also make sure to only use this for requests NOT towards `/access-token`
         * as that's the endpoint used in the first place for generating the token.
         * Without this check, we'd be causing an infinite loop...
         */
        if (empty($headers['Authorization']) && $path !== '/access-token') {
            $accessToken = $this->getToken();

            if ($accessToken === false) {
                $errorMessage = '[Zubarus] Could not get a valid access token';
                error_log($errorMessage);
                throw new Exception($errorMessage);
            }

            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        /**
         * CURLOPT_HTTPHEADER does not accept associative arrays.
         *
         * Therefore we make sure to transform the associative array to a regular array
         * with headers mapped like a plaintext HTTP header string.
         */
        $headers = array_map(function($headerName) use ($headers) {
            $value = $headers[$headerName];
            return sprintf('%s: %s', $headerName, $value);
        },
            array_keys($headers)
        );

        $url = sprintf('%s%s%s', $this->baseUrl, $path, $query);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        $result = ['success' => true, 'data' => $response];

        /**
         * All 2xx HTTP codes are successful status codes, but
         * we still need to catch 4xx (client errors) and 5xx (server errors).
         *
         * 3xx are typically redirect codes, so we consider them "successful" too.
         */
        if ($response === false || $info['http_code'] >= 400) {
            $error = [];
            if ($response === false) {
                $error['message'] = sprintf('%d - %s', curl_errno($ch), curl_error($ch));
            }
            else {
                $error['message'] = sprintf('Expected status code 200, got: %d', $info['http_code']);
            }

            $result['success'] = false;
            $result['error'] = $error;
            $result['meta'] = $info;
        }
        /**
         * Just in case certain API endpoints don't return proper
         * HTTP status codes with their response.
         */
        else {
            $data = json_decode($response, true);

            if ($data['status'] !== 'success') {
                $result['success'] = false;
                $result['error'] = $data['data'];
                $result['meta'] = $info;
            }
        }

        curl_close($ch);
        return $result;
    }

    /**
     * Decode valid JSON responses.
     *
     * @param array $result Array returned from the HTTP request helpers.
     * @return array
     */
    private function decode($result)
    {
        /**
         * Meta array is only specified on
         * HTTP error codes (4xx, 5xx).
         */
        $meta = $result['meta'] ?? [];
        $contentType = '';
        if (!empty($meta) && isset($meta['content_type'])) {
            $contentType = $meta['content_type'];
        }

        /**
         * Assume response is valid JSON on success, but
         * also decode the JSON if the `Content-Type` header says
         * it is a JSON response.
         *
         * `data` may end up as NULL if the API doesn't return any value.
         */
        if ($result['success'] || strpos($contentType, 'json') !== false) {
            $result['data'] = json_decode($result['data'], true);
        }

        return $result;
    }

    /**
     * Send a GET request to Zubarus API, with optional query parameters.
     *
     * @param string $path
     * @param array $query
     * @return array
     */
    public function get($path = '', $query = [])
    {
        $result = $this->request('GET', $path, $query);
        $result = $this->decode($result);

        return $result;
    }

    /**
     * Send a POST request to the Zubarus API, with optional field data.
     *
     * @param string $path
     * @param array $fields
     * @return array
     */
    public function post($path = '', $fields = [])
    {
        $result = $this->request('POST', $path, $fields);
        $result = $this->decode($result);

        return $result;
    }

    /**
     * Send a PUT request to the Zubarus API, with optional field data.
     *
     * @param string $path
     * @param array $fields
     * @return array
     */
    public function put($path = '', $fields = [])
    {
        $result = $this->request('PUT', $path, $fields);
        $result = $this->decode($result);

        return $result;
    }

    /**
     * Sends an SMS via the API to verify the phone number.
     *
     * @param string $phoneNumber
     *
     * @return array Response from API, empty array on errors
     */
    public function verifyPhoneNumber($phoneNumber = '')
    {
        if (empty(trim($phoneNumber))) {
            /**
             * TODO: Return proper errors (Exception?)
             */
            return [];
        }

        $body = ['mobilePhone' => $phoneNumber];
        $result = $this->post('/verify-mobile-phone', $body);

        /**
         * On success, the `data` field should contain another `data` array
         * with the following fields:
         * - `mobilePhone`
         * - `pin`
         */
        return $result;
    }

    /**
     * Sends an SMS via the API to verify the phone number.
     *
     * @param string $phoneNumber
     * @param string $pin
     *
     * @return array Response from API, empty array on errors
     */
    public function verifyPin($phoneNumber = '', $pin = '')
    {
        $phoneNumber = trim($phoneNumber);
        $pin = trim($pin);

        $emptyNumber = empty($phoneNumber);
        $emptyPin = empty($pin);
        if ($emptyNumber || $emptyPin) {
            /**
             * TODO: Return proper errors (Exception?)
             */
            return [];
        }

        $body = ['mobilePhone' => $phoneNumber, 'pin' => $pin];
        $result = $this->post('/verify-pin', $body);

        /**
         * On success, the `data` field should contain another `data` array
         * with the following fields:
         * - `status`
         */
        return $result;
    }
}
