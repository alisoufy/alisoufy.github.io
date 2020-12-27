<?php
defined('ABSPATH') or die;

if (isset($controlProps) && isset($controlTemplate)) {
    $blogName = get_option('blogname');
    $content = '';
    if (strlen($blogName) > 0) {
        $content = $blogName;
    } else {
        $content = $controlProps['content'];
    };
    $controlTemplate = str_replace('[[content]]', $content, $controlTemplate);
    $controlTemplate = str_replace('[[url]]', home_url('/'), $controlTemplate);
    echo $controlTemplate;
}
