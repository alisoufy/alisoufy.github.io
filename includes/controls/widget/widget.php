<?php
defined('ABSPATH') or die;

if (isset($controlProps) && isset($controlTemplate)) {
    $content = json_decode($controlProps, true);
    ob_start();
    NpWidgetsImporter::pluginWPWidget($content);
    $widget = ob_get_clean();
    $controlTemplate = str_replace('[[content]]', $widget, $controlTemplate);
    echo $controlTemplate;
}