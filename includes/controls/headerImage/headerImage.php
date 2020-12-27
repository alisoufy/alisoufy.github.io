<?php
defined('ABSPATH') or die;

if (isset($controlProps) && isset($controlTemplate)) {
    $header_image = get_header_image();
    if ($header_image) {
        echo esc_url($header_image);
    } else {
        echo $controlTemplate;
    }
}
