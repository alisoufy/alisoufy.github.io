<?php
defined('ABSPATH') or die;

class NpShortcodes {
    /**
     * Shortcode function for [np_widget]
     *
     * @param array  $atts
     * @param string $content
     *
     * @return string
     */
    public static function nicepageWidget($atts, $content) {
        $content = htmlspecialchars_decode($content);
        $props = json_decode($content, true);
        if (!$props) {
            return '';
        }

        ob_start();
        $type = _arr($props, 'type', 'text');
        $data = self::$data[$type];
        $class = $data[0];
        $args = _arr($data, 2, array());
        foreach ($data[1] as $args_key => $source_key) {
            if (is_string($source_key)) {
                $args[$args_key] = _arr($props, $source_key);
            } else if (is_array($source_key)) {
                $args[$args_key] = _arr($props, $source_key[0], $source_key[1]);
            }
        }
        the_widget($class, $args);
        return ob_get_clean();
    }

    public static $data = array(
        'text' => array(
            'WP_Widget_Text',
            array(
                'title' => 'title',
                'text' => 'content',
            ),
            array(
                'filter' => true,
            )
        ),
        'calendar' => array(
            'WP_Widget_Calendar',
            array(
                'title' => 'title',
            )
        ),
        'searchWidget' => array(
            'WP_Widget_Search',
            array(
                'title' => 'title',
            )
        ),
        'meta' => array(
            'WP_Widget_Meta',
            array(
                'title' => 'title',
            )
        ),
        'pages' => array(
            'WP_Widget_Pages',
            array(
                'title' => 'title',
                'exclude' => array('excludes', ''),
                'sortby' => array('order-by', 'ID'),
            )
        ),
        'tag-cloud' => array(
            'WP_Widget_Tag_Cloud',
            array(
                'title' => 'title',
                'taxonomy' => 'taxonomy',
                'count' => 'tag-cloud-counts',
            )
        ),
        'menuWidget' => array(
            'WP_Nav_Menu_Widget',
            array(
                'title' => 'title',
                'nav_menu' => 'menu',
            )
        ),
        'categories' => array(
            'WP_Widget_Categories',
            array(
                'title' => 'title',
                'count' => 'show-post-counts',
                'hierarchical' => 'show-hierarchy',
                'dropdown' => 'display-as-dropdown',
            )
        ),
        'archives' =>array(
            'WP_Widget_Archives',
            array(
                'title' => 'title',
                'count' => 'show-post-counts',
                'dropdown' => 'display-as-dropdown',
            )
        ),
        'comments' => array(
            'WP_Widget_Recent_Comments',
            array(
                'title' => 'title',
                'number' => 'posts-count',
            )
        ),
        'posts' => array(
            'WP_Widget_Recent_Posts',
            array(
                'title' => 'title',
                'number' => 'posts-count',
                'show_date' => 'display-post-date',
            )
        ),
        'rss' => array(
            'WP_Widget_RSS',
            array(
                'title' => 'title',
                'url' => 'feed-url',
                'items' => 'posts-count',
                'show_summary' => 'display-item-content',
                'show_author' => 'display-item-author',
                'show_date' => 'display-item-date',
            )
        ),
    );


    private static $_currentWidget = null;
    private static $_currentWidgets = null;

    /**
     * Shortcode function for [np_block_content_content]
     *
     * @param array  $atts
     * @param string $content
     *
     * @return string
     */
    public static function blockContentContent($atts, $content) {
        if (!empty(self::$_currentWidget['content'])) {
            return self::$_currentWidget['content'];
        } else {
            return $content;
        }
    }

    /**
     * Shortcode function for [np_block_header_content]
     *
     * @param array  $atts
     * @param string $content
     *
     * @return string
     */
    public static function blockHeaderContent($atts, $content) {
        return self::$_currentWidget['title'];
    }

    /**
     * Shortcode function for [np_block_header]
     *
     * @param array  $atts
     * @param string $content
     *
     * @return string
     */
    public static function blockHeader($atts, $content) {
        if (empty(self::$_currentWidget['title'])) {
            $content = preg_replace('/\[(\/?)(np_block_header_content)\]/', '', $content);
            return $content;
        }
        return do_shortcode($content);
    }

    /**
     * Shortcode function for [np_block]
     *
     * @param array  $atts
     * @param string $content
     *
     * @return string
     */
    public static function block($atts, $content) {
        $result = '';
        foreach (self::$_currentWidgets as $widget) {
            self::$_currentWidget = $widget;
            $result .= do_shortcode($content);
            self::$_currentWidget = null;
        }
        if ($result === '') {
            return $content;
        } else {
            return $result;
        }
    }

    /**
     * Shortcode function for [np_position]
     *
     * @param array  $atts
     * @param string $content
     *
     * @return string
     */
    public static function position($atts, $content) {
        if (preg_match('#data-position="([^"]*)"#', $content, $m)) {
            $position_name = $m[1];
        } else {
            $position_name = '';
        }

        self::$_currentWidgets = self::getWidgets($position_name);
        return do_shortcode($content);
    }

    /**
     * Filter on dynamic_sidebar_params
     * Replace widget args for convenience parsing
     *
     * @param array $params
     *
     * @return array
     */
    public static function paramsFilter($params) {
        $params[0]['before_widget'] = '<widget id="%1$s" name="%1$s" class="widget %2$s">';
        $params[0]['after_widget'] = '</widget>';
        $params[0]['after_title'] = '</title>';
        $params[0]['before_title'] = '<title>';
        return $params;
    }

    /**
     * Parse widgets from sidebar output
     *
     * @param string $name
     *
     * @return array
     */
    public static function getWidgets($name) {
        add_filter('dynamic_sidebar_params', 'NpShortcodes::paramsFilter');
        ob_start();
        dynamic_sidebar($name);
        $content = ob_get_clean();
        remove_filter('dynamic_sidebar_params', 'NpShortcodes::paramsFilter');

        $data = explode('</widget>', $content);
        $widgets = array();
        for ($i = 0; $i < count($data); $i++) {
            $widget = $data[$i];
            if (!$widget) {
                continue;
            }

            $id = null;
            $name = null;
            $class = null;
            $title = null;

            if (preg_match('/<widget(.*?)>/', $widget, $matches)) {
                if (preg_match('/id="(.*?)"/', $matches[1], $ids)) {
                    $id = $ids[1];
                }
                if (preg_match('/name="(.*?)"/', $matches[1], $names)) {
                    $name = $names[1];
                }
                if (preg_match('/class="(.*?)"/', $matches[1], $classes)) {
                    $class = $classes[1];
                }
                $widget = preg_replace('/<widget[^>]+>/', '', $widget);

                if (preg_match('/<title>(.*)<\/title>/', $widget, $matches)) {
                    $title = $matches[1];
                    $widget = preg_replace('/<title>.*?<\/title>/', '', $widget);
                }
            }
            $widget = str_replace('<ul class="product-categories">', '<ul>', $widget);

            $widgets[] = array(
                'id' => $id,
                'name' => $name,
                'class' => $class,
                'title' => $title,
                'content' => $widget
            );
        }
        return $widgets;
    }

}
add_shortcode('upage_widget', 'NpShortcodes::nicepageWidget');// back compat
add_shortcode('np_widget', 'NpShortcodes::nicepageWidget');
add_shortcode('np_position', 'NpShortcodes::position');
add_shortcode('np_block', 'NpShortcodes::block');
add_shortcode('np_block_header', 'NpShortcodes::blockHeader');
add_shortcode('np_block_header_content', 'NpShortcodes::blockHeaderContent');
add_shortcode('np_block_content_content', 'NpShortcodes::blockContentContent');