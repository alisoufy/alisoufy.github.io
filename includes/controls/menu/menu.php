<?php

defined('ABSPATH') or die;

require_once dirname(__FILE__) . '/navigation.php';

if (isset($controlProps) && isset($controlTemplate)) {
    register_nav_menus(
        array(
            $controlProps['menuInfo']['id'] => $controlProps['menuInfo']['name'],
        )
    );

    echo Plugin_NavMenu::getMenuHtml(
        array(
            'container_class' => $controlProps['container_class'],
            'menu' => array(
                'menu_class' => $controlProps['menu_class'],
                'item_class' => $controlProps['item_class'],
                'link_class' => $controlProps['link_class'],
                'link_style' => $controlProps['link_style'],
                'submenu_class' => $controlProps['submenu_class'],
                'submenu_item_class' => $controlProps['submenu_item_class'],
                'submenu_link_class' => $controlProps['submenu_link_class'],
                'submenu_link_style' => $controlProps['submenu_link_style'],
            ),
            'responsive_menu' => array(
                'menu_class' => $controlProps['r_menu_class'],
                'item_class' => $controlProps['r_item_class'],
                'link_class' => $controlProps['r_link_class'],
                'link_style' => $controlProps['r_link_style'],
                'submenu_class' => $controlProps['r_submenu_class'],
                'submenu_item_class' => $controlProps['r_submenu_item_class'],
                'submenu_link_class' => $controlProps['r_submenu_link_class'],
                'submenu_link_style' => $controlProps['r_submenu_link_style'],
            ),
            'theme_location' => $controlProps['theme_location'],
            'template' => $controlTemplate
        )
    );
}