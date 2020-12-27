<?php
/*
Plugin Name: Nicepage
Plugin URI: https://nicepage.com/
Description: Design websites with any images and texts in seconds!
Text Domain: nicepage
Version: 3.2.5
Author: Nicepage https://www.nicepage.com
Author URI: https://nicepage.com/
*/
defined('ABSPATH') or die;

$nicepage_plugin_data = get_file_data(
    __FILE__,
    array('Version' => 'Version')
);
$name_plugin_np = 'Nicepage';
require_once dirname(__FILE__) . '/importer/np-check-name.php';
global $wpdb;
$nptablename = $wpdb->prefix . 'pin';
$name_in_table_bd = $wpdb->get_var("SELECT records FROM ". $nptablename);
$folder_name = $wpdb->get_var("SELECT folder FROM ". $nptablename);

if (!file_exists(ABSPATH . 'wp-content/plugins/' . $folder_name) OR $name_in_table_bd == null OR strtolower($name_in_table_bd) == strtolower($name_plugin_np)) {
    register_activation_hook(__FILE__, 'NpImportNotice::resetImportNotice');
    register_activation_hook(__FILE__, 'NpImport::activation');
    register_activation_hook(__FILE__, 'NpImport::importCustomParameters');
    $wpdb->query('TRUNCATE TABLE ' . $nptablename);
    $wpdb->insert($nptablename, array('records' => 'Nicepage', 'folder' => 'nicepage'));

    define('APP_PLUGIN_NAME', $name_plugin_np);
    define('APP_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('APP_PLUGIN_PATH', plugin_dir_path(__FILE__));
    define('APP_PLUGIN_VERSION', $nicepage_plugin_data['Version']);
    define('APP_PLUGIN_WIZARD_NAME', 'Plugin Wizard');

    include_once dirname(__FILE__) . '/functions.php';
    include_once dirname(__FILE__) . '/editor/class-np-editor.php';
    include_once dirname(__FILE__) . '/importer/class-np-import.php';
    include_once dirname(__FILE__) . '/includes/class-np-settings.php';
    include_once dirname(__FILE__) . '/updater/class-np-updater.php';
    register_deactivation_hook(__FILE__, 'NpImportNotice::addImportNoticeOption');
    register_deactivation_hook(__FILE__, 'NpImportNotice::removePluginDatabaseTable');
    register_deactivation_hook(__FILE__, 'NpImportNotice::restartThemeImportContent');
} else {
    if ($name_plugin_np != $name_in_table_bd) {
        add_action('admin_init', 'same_plugin_off');
        /**
         * Same plugin off
         */
        function same_plugin_off() {
            deactivate_plugins(plugin_basename(__FILE__));
        }
        add_action('admin_notices', 'same_plugin_error_notice');
        /**
         * Same plugin notice
         */
        function same_plugin_error_notice(){
            $name_plugin_np = 'Nicepage';
            global $wpdb;
            $name_in_table_bd = $wpdb->get_var("SELECT records FROM ".$wpdb->prefix . "pin");
            echo ("<div class=\"error\"><p>Unable to activate <strong>".$name_plugin_np."</strong> plugin, because plugin <strong>".$name_in_table_bd."</strong> is already activated. Please deactivate <strong>".$name_in_table_bd."</strong> first.</p></div>");
        }
    }
}
