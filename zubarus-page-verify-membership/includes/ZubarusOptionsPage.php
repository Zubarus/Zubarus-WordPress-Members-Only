<?php

if (!defined('WPINC')) {
    die;
}

function zub_render_options_page()
{
?>
    <h2>Zubarus Members</h2>
    <form action="options.php" method="post">
        <?php
        settings_fields('zubarus_members_only_options');
        do_settings_sections('zubarus_members_only');
        ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
    </form>
<?php
}

function zub_members_only_text()
{
    echo '<p>Options related to the Zubarus Members Only integration.</p>';
}

function zub_members_only_option_restricted()
{
    $option = zub_get_option('pages_no_access');
    echo sprintf('<textarea id="zubarus_members_only_restricted_message" name="zub_members_only_optioons[restricted_message]">%s</textarea>', esc_textarea($option));
}

function zub_register_options_page()
{
    add_settings_section('members-only-message', 'Options', 'zub_members_only_text', 'zubarus_members_only');
    add_settings_field('zubarus_members_only_restricted_message', 'Message to display on restricted pages', 'zub_members_only_option_restricted', 'zubarus_members_only', 'members-only-message');
}
add_action('admin_init', 'zub_register_options_page');

function zub_add_options_page()
{
    add_options_page('Zubarus Members Only', 'Zubarus Plugin', 'manage_options', 'zubarus-members-only-options-page', 'zub_render_options_page');
}
add_action('admin_menu', 'zub_add_options_page');
