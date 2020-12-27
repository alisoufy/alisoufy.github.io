<?php

class Plugin_Walker_Nav_Menu extends Walker_Nav_Menu {

    public $args = array();
    public static $app_menu;
    
    public function display_element($el, &$children, $max_depth, $depth = 0, $args, &$output) {
        $item_class = array_search('mega', $el->classes);
        if ($item_class === false) {
            self::$app_menu = "base_menu";
        } else {
            self::$app_menu = "mega_menu";
        }
        parent::display_element($el, $children, $max_depth, $depth, $args, $output);
    }

    public function start_lvl(&$output, $depth = 0, $args = array()) {
        if (self::$app_menu === "base_menu") {
            $output .= '<div class="u-nav-popup"><ul class="' . $this->args['submenu_class'] . '">';
        } elseif (self::$app_menu === "mega_menu") {
            $output .= '<ul style="display:none" class="' . $this->args['submenu_class'] . '">';
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = array()) {
        parent::end_lvl($output, $depth, $args);
    }

    function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
        if (self::$app_menu === "base_menu") {
            parent::start_el( $output, $item, $depth, $args, $id = 0);
        } elseif (self::$app_menu === "mega_menu") {
            global $wp_query;
            $indent = ( $depth > 0 ? str_repeat( "\t", $depth ) : '' ); // code indent

            static $is_first;
            $is_first++;
            $depth_class_names = esc_attr( 'u-nav-item' );
            // passed classes
            $classes = empty( $item->classes ) ? array() : (array) $item->classes;
            $class_names = implode( ' ', array_filter( $classes ));

            $is_mega_menu = (strpos($class_names,'mega') !== false) ? true : false;
            $is_sidebar = (strpos($class_names,'menu_sidebar') !== false) ? true : false;
            $is_add_html = (strpos($class_names,'add_html') !== false) ? true : false;

            // build html
            $output .= $indent . '<li id="nav-menu-item-'. esc_attr($item->ID) . '" class="' . esc_attr($depth_class_names) . ' ' . esc_attr($class_names) . '">';
            // link attributes
            $attributes  = ! empty( $item->attr_title ) ? ' title="'  . esc_attr( $item->attr_title ) .'"' : '';
            $attributes .= ! empty( $item->target )     ? ' target="' . esc_attr( $item->target     ) .'"' : '';
            $attributes .= ! empty( $item->xfn )        ? ' rel="'    . esc_attr( $item->xfn        ) .'"' : '';
            $attributes .= ! empty( $item->url )        ? ' href="' . (($item->url[0] == "#" && !is_front_page()) ? home_url() : '') . esc_attr($item->url) .'"' : '';

            $attributes .= ' class="u-nav-link u-button-style active menu-link '.((strpos($item->url,'#') === false) ? '' : 'scroll').' ' . ( $depth > 0 ? 'sub-menu-link' : 'main-menu-link' ) . '"';

            $atts = array();
            $atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
            $atts['target'] = ! empty( $item->target )     ? $item->target     : '';
            $atts['rel']    = ! empty( $item->xfn )        ? $item->xfn        : '';
            $atts['href']   = ! empty( $item->url )        ? $item->url        : '';
            $atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

            $attributes = '';
            foreach ( $atts as $attr => $value ) {
                if ( ! empty( $value ) ) {
                    $value = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
                    $attributes .= ' ' . $attr . '="' . $value . '"';
                }
            }

            if ($is_add_html) {
                $add_html = "<img style=\"text-align:left; width: 212px; height: 140px; margin: 20px 5px;\" src=\"//images.devupage.ap.cr.smartlancer.net/a6/16/a616bcab-3f1c-4618-bb7f-69a6943eb6ba.jpg\" alt=\"\" class=\"u-image u-image-default u-block-777b-14\" data-image-width=\"1457\" data-image-height=\"1000\" data-block=\"35\">
                <h2 style=\"margin-left: 0px; margin-right: 0px; position: relative;\" class=\"u-text u-block-777b-16\" data-block=\"37\">Sample Headline</h2>
                <h3 style=\"margin-left: 0px; margin-right: 0px; position: relative;\" class=\"u-text u-block-777b-17\" data-block=\"38\">Sample Headline</h3>
                <div style=\"width: 204.8px; margin: 30px auto 20px 0px; position: relative;\" class=\"u-line u-border-grey-dark-1 u-border-3 u-line-horizontal u-block-777b-18\" data-block=\"39\">
                </div>
                <p style=\"margin-right: 0px; position: relative; margin-left: 0px;\" class=\"u-text u-block-777b-19\" data-block=\"40\">Sample text. Lorem ipsum dolor sit amet, consectetur adipiscing elit nullam nunc justo sagittis suscipit ultrices.</p>
                <a href=\"#\" style=\"margin-right: auto; position: relative; margin-left: 0px;\" class=\"u-btn u-button-style u-block-777b-20\" data-block=\"41\">Button</a>";
            } else {
                $add_html = "";
            }

            if ($is_sidebar) {
                $names_of_sidebars = explode(",", $item->description);
                $count_sidebars = count($names_of_sidebars);
                $sidebar_html_array = array();
                for($s = 0; $s < $count_sidebars; $s++ ) {
                    ob_start();
                    dynamic_sidebar($names_of_sidebars[$s]);
                    $sidebar_html_array[$s] = ob_get_clean();
                    if(!isset($sidebar_html)) {$sidebar_html = "";}
                    $sidebar_html .= "<div data-position=\"".$names_of_sidebars[$s]."\" class=\"u-position u-block-d0bc-15\" style=\"width: 250px; min-height: 50px; margin: 30px 0px 20px; position: relative;\" data-block=\"8\"><div class=\"u-block u-block-d0bc-16\" style=\"\" data-block=\"9\"><div class=\"u-block-container u-clearfix\"><div class=\"u-block-content u-text u-block-d0bc-18\" style=\"font-size: 0.875rem; line-height: 2\" data-block=\"11\" contenteditable=\"true\">" .$sidebar_html_array[$s]. "</div></div></div></div>";
                }
                $sidebar_output = '<a ' . $attributes . '>' . apply_filters( 'the_title', $item->title, $item->ID ) . '</a><div class="u-nav-popup u-inner-container-layout u-mega-popup level-2 u-palette-1-base u-hover-palette-1-base u-active-palette-1-base u-block-777b-21" style="box-shadow: -2px 2px 8px 0 rgba(var(--grey-50-r),var(--grey-50-g),var(--grey-50-b),1);padding-left: 20px;padding-right: 40px;">'.$add_html.$sidebar_html.'</div>';
                $item_output = $sidebar_output;
            } else{
                $item_output = '<a ' . $attributes . '>' . apply_filters( 'the_title', $item->title, $item->ID ) . '</a><div class="u-nav-popup u-inner-container-layout u-mega-popup level-2 u-palette-1-base u-hover-palette-1-base u-active-palette-1-base u-block-777b-21" style="box-shadow: -2px 2px 8px 0 rgba(var(--grey-50-r),var(--grey-50-g),var(--grey-50-b),1);padding-left: 20px;padding-right: 40px;">'.$add_html.'</div>';
            }
            // build html
            $output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
        }
    }

    function end_el( &$output, $item, $depth = 0, $args = array() ) {
        parent::end_el( $output, $item, $depth, $args);
    }

}

class Plugin_NavMenu {

    private static $_itemClass;
    private static $_linkClass;
    private static $_linkStyle;
    private static $_submenuItemClass;
    private static $_submenuLinkClass;
    private static $_submenuLinkStyle;
    private static $_themeLocation;

    /**
     * Filter on nav_menu_css_class
     *
     * @param string[] $classes
     * @param WP_Post  $item
     * @param stdClass $args
     * @param int      $depth
     * @return string[]
     */
    public static function _itemClassFilter($classes=array(), $item=null, $args=null, $depth=0) {
        if ($args === null) {
            $args = (object)array('theme_location' => '');
        }
        if ($args->theme_location === self::$_themeLocation) {
            $classes[] = $depth === 0 ? self::$_itemClass : self::$_submenuItemClass;
        }
        return $classes;
    }

    /**
     * Filter on nav_menu_link_attributes
     *
     * @param string[] $atts
     * @param WP_Post  $item
     * @param stdClass $args
     * @param int      $depth
     * @return string[]
     */
    public static function _linkAttrsFilter($atts=array(), $item=null, $args=null, $depth=0) {
        if ($args === null) {
            $args = (object)array('theme_location' => '');
        }
        if ($args->theme_location === self::$_themeLocation) {
            $class = $depth === 0 ? self::$_linkClass : self::$_submenuLinkClass;
            $style = $depth === 0 ? self::$_linkStyle : self::$_submenuLinkStyle;
            if ($class) {
                $atts['class'] = empty($atts['class']) ? $class : $atts['class'] . ' ' . $class;
            }
            if ($item->current) {
                $atts['class'] = empty($atts['class']) ? 'active' : $atts['class'] . ' active';
            }
            if ($style) {
                $atts['style'] = empty($atts['style']) ? $style : $atts['style'] . ';' . $style;
            }
        }
        return $atts;
    }

    public static function getMenuHtml($args) {
        self::$_themeLocation = $args['theme_location'];
        if (plugin_get_option('use_default_menu')) {
            return wp_nav_menu(array('theme_location' => self::$_themeLocation, 'echo' => false));
        }

        $locations = get_nav_menu_locations();
        $location = isset($locations[self::$_themeLocation]) ? $locations[self::$_themeLocation] : false;
        if (!$location && !empty($locations)) {
            $location = max(array_values($locations));
        }
        if (!$location) {
            return '';
        }

        $menu = wp_get_nav_menu_object($location);
        if (!$menu) {
            return '';
        }

        self::$_itemClass = $args['menu']['item_class'];
        self::$_linkClass = $args['menu']['link_class'];
        self::$_linkStyle = $args['menu']['link_style'];
        self::$_submenuItemClass = $args['menu']['submenu_item_class'];
        self::$_submenuLinkClass = $args['menu']['submenu_link_class'];
        self::$_submenuLinkStyle = $args['menu']['submenu_link_style'];

        add_filter('nav_menu_css_class', 'Plugin_NavMenu::_itemClassFilter', 10, 4);
        add_filter('nav_menu_link_attributes', 'Plugin_NavMenu::_linkAttrsFilter', 10, 4);

        $menu_walker = new Plugin_Walker_Nav_Menu;
        $menu_walker->args = $args['menu'];

        $menu_html = wp_nav_menu(array(
            'menu' => $menu,
            'menu_class' => $args['menu']['menu_class'],
            'container' => 'nav',
            'container_class' => $args['container_class'],
            'theme_location' => self::$_themeLocation,
            'walker' => $menu_walker,
            'echo' => false,
        ));

        self::$_itemClass = $args['responsive_menu']['item_class'];
        self::$_linkClass = $args['responsive_menu']['link_class'];
        self::$_linkStyle = $args['responsive_menu']['link_style'];
        self::$_submenuItemClass = $args['responsive_menu']['submenu_item_class'];
        self::$_submenuLinkClass = $args['responsive_menu']['submenu_link_class'];
        self::$_submenuLinkStyle = $args['responsive_menu']['submenu_link_style'];

        $responsive_menu_walker = new Plugin_Walker_Nav_Menu;
        $responsive_menu_walker->args = $args['responsive_menu'];

        $responsive_menu_html = wp_nav_menu(array(
            'menu' => $menu,
            'menu_class' => $args['responsive_menu']['menu_class'],
            'container' => 'nav',
            'container_class' => $args['container_class'],
            'theme_location' => self::$_themeLocation,
            'walker' => $responsive_menu_walker,
            'echo' => false,
        ));

        if (!preg_match('#<ul[\s\S]*ul>#', $responsive_menu_html, $m)) {
            return '';
        }
        $responsive_nav = $m[0];

        if (!preg_match('#<ul[\s\S]*ul>#', $menu_html, $m)) {
            return '';
        }
        $regular_nav = $m[0];

        $menu_html = strtr($args['template'], array('{menu}' => $regular_nav, '{responsive_menu}' => $responsive_nav));
        $menu_html = preg_replace('#<\/li>\s+<li#', '</li><li', $menu_html); // remove spaces
        return $menu_html;
    }

    public static function menuItemTitleFilter($title, $item, $args, $depth) {
        if (plugin_get_option('menu_use_tag_filter')) {
            $allowed_tags = explode(',', str_replace(' ', '', plugin_get_option('menu_allowed_tags')));
            $title = strip_tags($title, $allowed_tags ? '<' . implode('><', $allowed_tags) . '>' : '');
        }
        if (plugin_get_option('menu_trim_title')) {
            $title = plugin_trim_long_str($title, plugin_get_option($depth == 0 ? 'menu_trim_len' : 'submenu_trim_len'));
        }
        return $title;
    }
}
add_filter('nav_menu_item_title', 'Plugin_NavMenu::menuItemTitleFilter', 10, 4);
