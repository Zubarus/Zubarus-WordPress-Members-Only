<?php

if (!defined('WPINC')) {
    die;
}

/**
 * Render the actual options page HTML
 */
function zub_render_options_page()
{
    $optionNames = ZubarusOptions::getOptionNames();
?>
    <h2><?php _e('Zubarus - Members Only', 'zubarus-members-only'); ?></h2>
    <form action="options.php" method="post">
        <?php
        settings_fields($optionNames['pages_no_access']);
        do_settings_sections('zubarus_members_only');
        ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
    </form>
<?php
}

/**
 * Description text on options page.
 */
function zub_members_only_text()
{
    printf('<p>%s</p>', __('Options related to the Zubarus - Members Only plugin.', 'zubarus-members-only'));
}

/**
 * Retrieve option from options API and
 * print it into a textarea field
 */
function zub_members_only_option_restricted()
{
    $names = ZubarusOptions::getOptionNames();
    $option = zub_get_option('pages_no_access');
    echo sprintf('<textarea id="zubarus_members_only_restricted_message" class="large-text code" name="%s">%s</textarea>', $names['pages_no_access'], esc_textarea($option));
}

/**
 * Register options fields.
 */
function zub_register_options_page()
{
    $optionNames = ZubarusOptions::getOptionNames();
    register_setting($optionNames['pages_no_access'], $optionNames['pages_no_access'], [
        'type' => 'string',
    ]);

    add_settings_section('zubarus-members-only-message', __('Settings'), 'zub_members_only_text', 'zubarus_members_only');
    add_settings_field('zubarus_members_only_restricted_message', __('Message to display on restricted pages.', 'zubarus-members-only'), 'zub_members_only_option_restricted', 'zubarus_members_only', 'members-only-message');
}
add_action('admin_init', 'zub_register_options_page');

/**
 * Register options page.
 */
function zub_add_options_page()
{
    add_options_page('Zubarus Members Only Plugin', __('Zubarus - Members Only', 'zubarus-members-only'), 'manage_options', 'zubarus-members-only', 'zub_render_options_page');
}
add_action('admin_menu', 'zub_add_options_page');
