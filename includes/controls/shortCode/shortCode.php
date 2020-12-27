<?php
defined('ABSPATH') or die;

if (isset($controlProps) && isset($controlTemplate)) {
    $controlTemplate = str_replace(
        '[[content]]', do_shortcode(
            <<<SHORTCODE_CONTENT
    $controlProps
SHORTCODE_CONTENT
        ), $controlTemplate
    );
    echo $controlTemplate;
}