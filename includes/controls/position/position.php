<?php
defined('ABSPATH') or die;

if (!function_exists('pluginStylingDefaultControls')) {
    /**
     * @param array $content
     *
     * @return string|string[]|null
     */
    function pluginStylingDefaultControls($content) {
        $content = preg_replace_callback('/<input[^>]+>/', 'plugin_inputs_in_string', $content);
        $content = preg_replace_callback('/<button[^>]+>/', 'plugin_buttons_in_string', $content);
        $content = preg_replace_callback('/<textarea[^>]+>/', 'plugin_textarea_in_string', $content);
        return $content;
    }
}

if (!function_exists('plugin_inputs_in_string')) {
    /**
     * @param array $inputs_in_string
     *
     * @return mixed
     */
    function plugin_inputs_in_string($inputs_in_string) {
        if (stripos($inputs_in_string[0], 'class') == true && stripos($inputs_in_string[0], 'u-input') == false && stripos($inputs_in_string[0], 'u-btn') == false) {
            if (stripos($inputs_in_string[0], 'button') == false && stripos($inputs_in_string[0], 'submit') == false) {
                $inputs_in_string[0] = str_replace('class="', 'class="u-input ', $inputs_in_string[0]);
            } else {
                $inputs_in_string[0] = str_replace('class="', 'class="u-btn u-button-style ', $inputs_in_string[0]);
            }
        } elseif (stripos($inputs_in_string[0], 'class') == false) {
            if (stripos($inputs_in_string[0], 'button') == true || stripos($inputs_in_string[0], 'submit') == true) {
                $inputs_in_string[0] = str_replace('>', 'class="u-btn u-button-style">', $inputs_in_string[0]);
            } else {
                $inputs_in_string[0] = str_replace('>', ' class="u-input">', $inputs_in_string[0]);
            }
        }
        return $inputs_in_string[0];
    }
}

if (!function_exists('plugin_buttons_in_string')) {
    /**
     * @param array $buttons_in_string
     *
     * @return mixed
     */
    function plugin_buttons_in_string($buttons_in_string) {
        if (stripos($buttons_in_string[0], 'class') == true) {
            $buttons_in_string[0] = str_replace('class="', 'class="u-btn u-button-style ', $buttons_in_string[0]);
        } else {
            $buttons_in_string[0] = str_replace('>', ' class="u-btn u-button-style">', $buttons_in_string[0]);
        }
        return $buttons_in_string[0];
    }
}

if (!function_exists('plugin_textarea_in_string')) {
    /**
     * @param array $textarea_in_string
     *
     * @return mixed
     */
    function plugin_textarea_in_string($textarea_in_string) {
        if (stripos($textarea_in_string[0], 'class') == true) {
            $textarea_in_string[0] = str_replace('class="', 'class="u-input ', $textarea_in_string[0]);
        } else {
            $textarea_in_string[0] = str_replace('>', ' class="u-input">', $textarea_in_string[0]);
        }
        return $textarea_in_string[0];
    }
}

if (!function_exists('plugin_sidebar')) {
    /**
     * @param array $args
     *
     * @return string
     */
    function plugin_sidebar($args) {
        ob_start();
        dynamic_sidebar($args['id']);
        $content = ob_get_clean();

        $data = explode('</widget>', $content);
        $widgets = array();
        foreach ($data as $widget) {
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
                'content' => $widget,
            );
        }

        $result = '';
        foreach ($widgets as $widget) {
            $result .= strtr(
                $args['template'], array(
                    '{block_content}' => $widget['content'],
                    '{block_header}' => $widget['title'],
                )
            );
        }
        return $result;
    }
}

if (isset($controlProps) && isset($controlTemplate)) {
    if (!isset($controlProps['id'])) {
        // Okay, There is not sidebar yet.
        return;
    }
    if (preg_match('#data-position="([^"]*)"#', $controlTemplate, $m)) {
        $position_name = $m[1];
    } else {
        $position_name = '';
    }
    // if empty value from page use auto value from server
    if ($position_name === '') {
        $position_name = $controlProps['name'];
    }
    ob_start();
    dynamic_sidebar($position_name);
    $widgets_html = ob_get_clean();
    if (preg_match('#widget id="([^"]*)"#', $widgets_html, $m)) {
        $widget_id = $m[1];
    } else {
        $widget_id = '';
    }

    if (preg_match('#class="([^"]*)"#', $controlTemplate, $c)) {
        $position_class = $c[1];
    } else {
        $position_class = '';
    }
    $active_widgets = get_option('sidebars_widgets');
    $sidebar_array = array();
    foreach ($active_widgets AS $key => $value) {
        if (is_array($value) && in_array($widget_id, $value) == true ) {
            $sidebar_id = $key;
        }
    }
    // if sidebar id from export server !== sidebar id from theme use from theme
    if ($sidebar_id && $controlProps['id'] !== $sidebar_id) {
        $controlProps['id'] = $sidebar_id;
    }

    $widgetContent['title'] = $controlProps['header'];
    $widgetContent['text'] = $controlProps['content'];
    $widgetContent['filter'] = true;
    $widgetContent['visual'] = true;
    if (is_active_sidebar($controlProps['id'])) {
        if ($widget_id === '') {
            NpWidgetsImporter::importWidgetsContent($controlProps['id'], $widgetContent, false);
        }
    }
    $controlTemplate = str_replace('{block_template}', pluginStylingDefaultControls($controlProps['template']), $controlTemplate);
    $titleClasses = (isset($controlProps['titleClasses']) && $controlProps['titleClasses'] !== '') ? $controlProps['titleClasses'] : 'u-block-header u-text u-text-1';
    $contentClasses = (isset($controlProps['contentClasses']) && $controlProps['contentClasses'] !== '') ? $controlProps['contentClasses'] : 'u-block-content u-text u-text-2';
    $sidebar_html = plugin_sidebar(
        array(
            'id' => $controlProps['id'],
            'template' => <<<WIDGET_TEMPLATE
                <div class="u-block">
        <div class="u-block-container u-clearfix"><!--block_header-->
          <h5 class="$titleClasses">{block_header}</h5><!--/block_header--><!--block_content-->
          <div class="$contentClasses">{block_content}</div><!--/block_content-->
        </div>
      </div>
WIDGET_TEMPLATE
        )
    );
    if ($sidebar_html) {
        echo "<div data-position='".$controlProps['id']."' class='".$position_class."'>".pluginStylingDefaultControls($sidebar_html)."</div>";
    } else {
        echo "<div data-position='".$controlProps['id']."' class='".$position_class."'>".pluginStylingDefaultControls($controlProps['template'])."</div>";
    }
}