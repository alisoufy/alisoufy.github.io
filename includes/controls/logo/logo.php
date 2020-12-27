<?php
defined('ABSPATH') or die;

if (isset($controlProps) && isset($controlTemplate)) {
    if ($controlProps['href'] === '/') {
        $controlProps['href'] = home_url('/');
    }
    $logo = plugin_get_logo(
        array(
            'default_src' => $controlProps['src'],
            'default_url' => $controlProps['href'],
            'logo_src' => $controlProps['src'],
            'logo_url' => $controlProps['href'],
            'default_width' => $controlProps['defaultWidth'],
        )
    );

    if (is_customize_preview()) {
        $controlTemplate = str_replace('href="',  'data-default-src="' . esc_url($logo['default_src']) . '" href="', $controlTemplate);
    }
    if ($logo['svg']) {
        $width = '<img style="width:'.$logo['width'].'px"';
    } else {
        $width = '<img style="width:auto"';
    }
    $controlTemplate = str_replace('<img', $width, $controlTemplate);
    $controlTemplate = str_replace('[[url]]', $logo['url'], $controlTemplate);
    $controlTemplate = str_replace('[[src]]', $logo['src'], $controlTemplate);
    echo $controlTemplate;
}