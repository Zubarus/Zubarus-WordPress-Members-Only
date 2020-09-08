<?php

if (!defined('WPINC')) {
    die;
}

/**
 * Returns HTML string of form to use when verifying
 * phone number / PIN.
 *
 * Used for replacing `{form}` inside
 *
 * @return string
 */
function zub_form_text_verify_phone()
{
    $html = <<<FORM_HTML
<form method="post" action="">
    <label for="zubarus_phone_number"><strong>%s</strong></label>
    <br />
    <input type="text" name="zubarus_phone_number" id="zubarus_phone_number" required="" placeholder="********" />
    <br /><br />
    <input name="submit" class="button button-primary" type="submit" value="%s" />
</form>
FORM_HTML;

    $html = sprintf($html, __('Phone number', 'zubarus-members-only'), esc_attr(__('Submit')));

    return $html;
}

function zub_form_text_verify_pin()
{
    $html = <<<FORM_HTML
<form method="post" action="">
    <label for="zubarus_pin_verify"><strong>%s</strong></label>
    <br />
    <input type="text" name="zubarus_pin_verify" id="zubarus_pin_verify" required="" placeholder="****" />
    <br /><br />
    <input name="submit" class="button button-primary" type="submit" value="%s" />
</form>
FORM_HTML;

    $html = sprintf($html, __('Verify Pin', 'zubarus-members-only'), esc_attr(__('Submit')));

    return $html;
}
