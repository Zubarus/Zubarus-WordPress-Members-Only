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

    <hr style="margin-top: 2em;">
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
        settings_fields('api_credentials');
        do_settings_sections('zubarus_members_only_api_credentials');
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
 * Sanitizes *both* REMOVAL and ADDITION of restricted pages.
 * The input value for the function is the post ID, based
 * on the `value` attributes of the generated options in the selects.
 *
 * It works by:
 * 1. Retrieving the options from the database
 * 2. Checks if the post ID is already a restricted page, if it is, then:
 *  - Remove the post ID from the options
 *  - Re-index the options array
 *  - Return the array so Wordpress can serialize the array.
 * 3. If NOT #2, then it adds the post ID to the restricted page array and returns it.
 *
 * This has a minor flaw; If some user decides to be clever and changing one of the "add" option
 * elements with an existing value, it will implicitly remove it because it's already restricted.
 * The opposite goes for "removal", setting a value that's NOT restricted in the "removal" section will
 * implicitly add it because it's not already restricted.
 *
 * I consider this a non-issue, since the changes are not devestating.
 * The main reason it's separated is to show the Wordpress admin(s)
 * what pages are restricted or not restricted UI-wise.
 * The "inner workings" are less important.
 *
 * @param string $postId
 *
 * @return array
 */
function zub_members_only_sanitize_restricted_pages($postId)
{
    $options = zub_get_option('pages');

    /**
     * REMOVAL: Remove post from "restricted posts"
     * and return the new valid options.
     */
    if (in_array($postId, $options)) {
        $optionKey = array_search($postId, $options);

        /**
         * Maybe redundant check, but just in
         * case of race conditions occurring...
         */
        if ($optionKey !== false) {
            unset($options[$optionKey]);

            /**
             * Re-index the numeric keys
             */
            $options = array_values($options);
        }

        return $options;
    }

    /**
     * ADDITION: Add post to "restricted posts"
     */
    $options[] = $postId;
    return $options;
}

/**
 * PRINTS formatted `<option>` HTML values for the specified posts.
 * Intended use: `<select>` fields on the Zubarus Members Only options
 * page for adding/removing restricted pages.
 *
 * Expected format: `<option value="$PostId">$PostTitle [$PostStatus]</option>`
 *
 * @param WP_Post[] $getPosts
 *
 * @return void
 */
function zub_print_posts_as_options($getPosts)
{
    $postStatuses = get_post_statuses();
    foreach ($getPosts as $post) {
        $status = $post->post_status;
        $statusTrans = $postStatuses[$status] ?? $status;

        /**
         * For some reason this translation is not included
         * via `get_post_statuses()`, so we grab it ourselves.
         */
        if ($status === 'future') {
            /**
             * Normally we'd use `__()` here and be done.
             *
             * But without the `post status` context
             * it would retrieve the wrong translation for
             * "Scheduled" - Plural instead of singular
             *
             * This is only a problem on the admin page.
             */
            $statusTrans = _x('Scheduled', 'post status');
        }

        printf('<option value="%s">%s [%s]</option>', $post->ID, $post->post_title, $statusTrans);
    }
}

/**
 * Helper function for `get_posts()` with relevant pre-filled
 * values for restricted pages.
 *
 * @param array $override Override values for `get_posts()`. Fields such as `include` or `exclude` are expected.
 *
 * @return WP_Post[]
 */
function zub_get_posts($override = [])
{
    $args = [
        /**
         * No post limit
         */
        'numberposts' => -1,

        /**
         * Allow posts to be restricted before they're "published".
         */
        'post_status' => [
            'publish',
            // Scheduled
            'future',
            'draft',
            'pending',
        ],

        /**
         * Make sure to include both normal WordPress posts and pages.
         */
        'post_type' => [
            'post',
            'page',
        ],
    ];

    $args = array_merge($args, $override);

    return get_posts($args);
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

    $getPosts = zub_get_posts([
        'exclude' => $restrictedPages,
    ]);

    zub_print_posts_as_options($getPosts);

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

    /**
     * Only print options if there restricted posts
     * If this isn't specified, `include` will be empty
     * and it lists _ALL_ WordPress posts/pages (which we don't want).
     */
    if (count($restrictedPages) > 0) {
        $getPosts = zub_get_posts([
            'include' => $restrictedPages,
        ]);

        zub_print_posts_as_options($getPosts);
    }

    print('</select>');
}

/**
 * Prints description for API credentials
 */
function zub_members_only_api_credentials_text()
{
    printf('<p>%s</p>', __('API credentials used to communicate with the Zubarus API. Required to use phone verification via Zubarus.', 'zubarus-members-only'));
}

/**
 * Print API username field with pre-filled value.
 */
function zub_members_only_api_username()
{
    $names = ZubarusOptions::getOptionNames();
    $apiUsername = zub_get_option('api_username');

    echo sprintf('<input type="text" placeholder="api@example.com" id="zubarus_members_only_api_username" name="%s" value="%s" />', $names['api_username'], esc_attr($apiUsername));
}

/**
 * Print API password field with a fake pre-filled value (for security reasons).
 * The value is validated in `zub_members_only_api_password_sanitize()` before being saved.
 */
function zub_members_only_api_password()
{
    $names = ZubarusOptions::getOptionNames();

    echo sprintf('<input type="password" placeholder="********" id="zubarus_members_only_api_password" name="%s" value="        " />', $names['api_password']);
}

/**
 * Checks if the new password is non-empty (e.g. bunch of spaces...)
 * If it isn't, we save it as a new password.
 *
 * If it's "empty", then the old password stays.
 *
 * @param string $newPassword
 *
 * @return string
 */
function zub_members_only_api_password_sanitize($newPassword)
{
    if (empty(trim($newPassword))) {
        /**
         * If the "new password" is empty (after trimming spaces)
         * we return the old API password.
         */
        return zub_get_option('api_password');
    }

    return $newPassword;
}

/**
 * Register options fields.
 */
function zub_register_options_page()
{
    $optionNames = ZubarusOptions::getOptionNames();
    /**
     * "Members-only" message
     */
    register_setting($optionNames['pages_no_access'], $optionNames['pages_no_access']);

    /**
     * Restricted pages
     */
    register_setting($optionNames['pages'], $optionNames['pages'], [
        'default' => $optionNames['pages'],
        'sanitize_callback' => 'zub_members_only_sanitize_restricted_pages',
    ]);

    /**
     * API username
     */
    register_setting('api_credentials', $optionNames['api_username'], [
        'default' => null,
    ]);

    /**
     * API password
     */
    register_setting('api_credentials', $optionNames['api_password'], [
        'default' => null,
        'sanitize_callback' => 'zub_members_only_api_password_sanitize',
    ]);

    /**
     * Fields for updating "members-only" replacement text.
     */
    add_settings_section(
        // ID
        'zubarus-members-only-message',
        // Title
        __('Settings'),
        // Callback
        'zub_members_only_text',
        // Page
        'zubarus_members_only_text'
    );

    add_settings_field(
        // ID
        'zubarus_members_only_restricted_message',
        // Title
        __('Message to display on restricted pages.', 'zubarus-members-only'),
        // Callback
        'zub_members_only_option_restricted',
        // Page
        'zubarus_members_only_text',
        // Section
        'zubarus-members-only-message'
    );

    /**
     * Fields for adding a new restricted page.
     */
    add_settings_section(
        // ID
        'zubarus-members-only-add-restricted-page',
        // Title
        __('Restricted pages &mdash; Add restricted page', 'zubarus-members-only'),
        // Callback
        'zub_members_only_add_restricted_page_text',
        // Page
        'zubarus_members_only_add_restricted_page'
    );

    add_settings_field(
        // ID
        'zubarus_members_only_add_restricted_page',
        // Title
        __('Page that should be restricted to members', 'zubarus-members-only'),
        // Callback
        'zub_members_only_add_restricted_page',
        // Page
        'zubarus_members_only_add_restricted_page',
        // Section
        'zubarus-members-only-add-restricted-page'
    );

    /**
     * Fields for removing a restricted page.
     */
    add_settings_section(
        // ID
        'zubarus-members-only-del-restricted-page',
        // Title
        __('Restricted pages &mdash; Remove restricted page', 'zubarus-members-only'),
        // Callback
        'zub_members_only_del_restricted_page_text',
        // Page
        'zubarus_members_only_del_restricted_page'
    );

    add_settings_field(
        // ID
        'zubarus_members_only_del_restricted_page',
        // Title
        __('Page that should be unrestricted and made public', 'zubarus-members-only'),
        // Callback
        'zub_members_only_del_restricted_page',
        // Page
        'zubarus_members_only_del_restricted_page',
        // Section
        'zubarus-members-only-del-restricted-page'
    );

    /**
     * API username/password fields
     */
    add_settings_section(
        // ID
        'zubarus-members-only-api-credentials',
        // Title
        __('Zubarus API:', 'zubarus-members-only'),
        // Callback
        'zub_members_only_api_credentials_text',
        // Page
        'zubarus_members_only_api_credentials'
    );

    add_settings_field(
        // ID
        'zubarus_members_only_api_username',
        // Title
        __('API Username:', 'zubarus-members-only'),
        // Callback
        'zub_members_only_api_username',
        // Page
        'zubarus_members_only_api_credentials',
        // Section
        'zubarus-members-only-api-credentials'
    );

    add_settings_field(
        // ID
        'zubarus_members_only_api_password',
        // Title
        __('API Password:', 'zubarus-members-only'),
        // Callback
        'zub_members_only_api_password',
        // Page
        'zubarus_members_only_api_credentials',
        // Section
        'zubarus-members-only-api-credentials'
    );
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
