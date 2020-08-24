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
        do_settings_sections('zubarus_members_only_text');
        ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
    </form>
    <hr style="margin-top: 2em;" />
    <form action="options.php" method="post">
        <?php
        settings_fields($optionNames['pages']);
        do_settings_sections('zubarus_members_only_add_restricted_page');
        ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Add'); ?>" />
    </form>
    <hr style="margin-top: 2em;" />
    <form action="options.php" method="post">
        <?php
        settings_fields($optionNames['pages']);
        do_settings_sections('zubarus_members_only_del_restricted_page');
        ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Remove'); ?>" />
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
 * Short description for adding a restricted page
 */
function zub_members_only_add_restricted_page_text()
{
    printf('<p>%s</p>', __('Add a new page that should be restricted to verified members only.', 'zubarus-members-only'));
}

/**
 * Retrieves restricted pages via the options API
 * Then gets all the Wordpress posts that aren't
 * restricted yet.
 *
 * Unrestricted pages will be listed, so that they can be added
 * as restricted pages (if that's wanted).
 */
function zub_members_only_add_restricted_page()
{
    $options = ZubarusOptions::getDefaultOptions();
    $restrictedPages = zub_get_option('pages');

    printf('<select id="zubarus_members_only_add_restricted_page" name="%s">', $options['pages']['name']);
    printf('<option>%s</option>', __('&mdash; Select &mdash;'));

    $getPosts = get_posts([
        'numberposts' => -1,
        'exclude' => $restrictedPages,
    ]);

    foreach ($getPosts as $post) {
        printf('<option value="%s">%s</option>', $post->ID, $post->post_title);
    }

    print('</select>');
}

/**
 * Short description for unrestricting a page
 */
function zub_members_only_del_restricted_page_text()
{
    printf('<p>%s</p>', __('Remove a page from the list of restricted pages and make it available to the public.', 'zubarus-members-only'));
}

/**
 * Retrieves restricted pages via the options API
 * Then makes sure those posts exist before listing them.
 */
function zub_members_only_del_restricted_page()
{
    $options = ZubarusOptions::getDefaultOptions();
    $restrictedPages = zub_get_option('pages');

    printf('<select id="zubarus_members_only_add_restricted_page" name="%s">', $options['pages']['name']);
    printf('<option>%s</option>', __('&mdash; Select &mdash;'));

    $getPosts = get_posts([
        'numberposts' => -1,
        'include' => $restrictedPages,
    ]);

    foreach ($getPosts as $post) {
        printf('<option value="%s">%s</option>', $post->ID, $post->post_title);
    }

    print('</select>');
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

    register_setting($optionNames['pages'], $optionNames['pages'], [

    ]);

    /**
     * Fields for updating "members-only" replacement text.
     */
    add_settings_section('zubarus-members-only-message', __('Settings'), 'zub_members_only_text', 'zubarus_members_only_text');
    add_settings_field('zubarus_members_only_restricted_message', __('Message to display on restricted pages.', 'zubarus-members-only'), 'zub_members_only_option_restricted', 'zubarus_members_only_text', 'zubarus-members-only-message');

    /**
     * Fields for adding a new restricted page.
     */
    add_settings_section('zubarus-members-only-add-restricted-page', __('Restricted pages &mdash; Add restricted page', 'zubarus-members-only'), 'zub_members_only_add_restricted_page_text', 'zubarus_members_only_add_restricted_page');
    add_settings_field('zubarus_members_only_add_restricted_page', __('Page that should be restricted to members', 'zubarus-members-only'), 'zub_members_only_add_restricted_page', 'zubarus_members_only_add_restricted_page', 'zubarus-members-only-add-restricted-page');

    /**
     * Fields for removing a restricted page.
     */
    add_settings_section('zubarus-members-only-del-restricted-page', __('Restricted pages &mdash; Remove restricted page', 'zubarus-members-only'), 'zub_members_only_del_restricted_page_text', 'zubarus_members_only_del_restricted_page');
    add_settings_field('zubarus_members_only_del_restricted_page', __('Page that should be unrestricted and made public', 'zubarus-members-only'), 'zub_members_only_del_restricted_page', 'zubarus_members_only_del_restricted_page', 'zubarus-members-only-del-restricted-page');
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
