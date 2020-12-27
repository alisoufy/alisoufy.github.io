<?php
defined('ABSPATH') or die;

if (isset($controlProps) && isset($controlTemplate)) {
    $controlTemplate = str_replace('[[action]]', home_url('/'), $controlTemplate);
    $controlTemplate = str_replace('[[query]]', esc_html(get_search_query()), $controlTemplate);
    echo $controlTemplate;
}