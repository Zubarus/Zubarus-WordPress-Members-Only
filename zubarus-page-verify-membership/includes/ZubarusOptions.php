<?php

if (!defined('WPINC')) {
    die;
}

class ZubarusOptions
{
    /**
     * Mapping of alias to wp_options ID/name.
     *
     * @var array
     */
    private static $optionNames = [
        'pages' => 'zubarus_members_only_pages',
        'pages_no_access' => 'zubarus_no_access'
    ];

    /**
     * Default options
     *
     * @var array
     */
    private static $defaultOptions = [
        'pages' => [
            // Post/page IDs
            'default' => [],
        ],
        'pages_no_access' => [
            'default' => '[Members-Only] You need to verify your membership to access this page.',
        ],
    ];

    /**
     * Return default options
     *
     * @return array
     */
    public static function getDefaultOptions()
    {
        $options = static::$defaultOptions;
        $names = static::$optionNames;

        /**
         * Include `option_name` fields in DB as `id` and `name`.
         */
        array_map(function($alias) use ($names, &$options) {
            $options[$alias]['id'] = $names[$alias];
            $options[$alias]['name'] = $names[$alias];
        }, array_keys($options));

        return $options;
    }

    public static function getOptionNames()
    {
        return static::$optionNames;
    }
}

/**
 * Register options with default values if they don't exist.
 */
function zub_register_options()
{
    $zub_options = ZubarusOptions::getDefaultOptions();

    error_log(print_r($zub_options, true));

    foreach ($zub_options as $values) {
        $option = get_option($values['id'], false);

        /**
         * Do not add option if it already exists.
         * In case the user has deactivated/reactivated the plugin.
         */
        if ($option !== false) {
            continue;
        }

        $added = add_option($values['id'], $values['default']);

        if ($added === false) {
            error_log(sprintf('Unable to add option %s', $values['id']));
        }
    }
}

function zub_get_option($name)
{
    $options = ZubarusOptions::getDefaultOptions();
    $option = $options[$name];
    return get_option($option['id'], $option['default']);
}

function zub_update_option($name, $newOptions)
{
    $oldOptions = zub_get_option($name);
    $optionNames = ZubarusOptions::getOptionNames();

    if (gettype($oldOptions) !== gettype($newOptions)) {

        error_log(sprintf('Type mismatch when updating option %s', $optionNames[$name]));
        return false;
    }

    return update_option($optionNames[$name], $newOptions);
}

add_action('activated_plugin', 'zub_register_options');