<?php
defined('ABSPATH') or die;

if (isset($controlProps) && isset($controlTemplate)) {
    $description = get_bloginfo('description', 'display');
    if ($description || is_customize_preview()) {
        $controlProps['content'] = $description;
    }
    $controlTemplate = str_replace('[[content]]', $controlProps['content'], $controlTemplate);
    echo $controlTemplate;
}
