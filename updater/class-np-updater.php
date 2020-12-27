<?php
defined('ABSPATH') or die;

class NpUpdater {

    /**
     * Get plugin info from remote server or from cache
     *
     * @return bool|object
     */
    private static function _getInfo() {
        if ($info = get_transient(self::$_transientKey)) {
            return $info;
        }

        $remote = wp_remote_get(
            self::getInfoJsonUrl(),
            array(
                'timeout' => 10,
                'sslverify'   => false,
                'headers' => array(
                    'Accept' => 'application/json'
                )
            )
        );

        if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200
            && !empty($remote['body']) && ($info = json_decode($remote['body']))
        ) {
            set_transient(self::$_transientKey, $info, 12 * HOUR_IN_SECONDS);
        }
        return $info;
    }

    /**
     * Filter on site_transient_update_plugins
     * Add plugin information
     *
     * @param object $transient
     *
     * @return mixed
     */
    public static function updatePluginsFilter($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        if ($info = self::_getInfo()) {
            if (version_compare($info->requires, get_bloginfo('version'), '<=')) { // check WordPress version requirement
                $res = new stdClass();
                $res->slug = 'nicepage';
                $res->plugin = 'nicepage/nicepage.php';
                $res->new_version = $info->version;
                $res->tested = $info->tested;
                $res->package = $info->download_url;
                $res->icons = (array) $info->icons;
                $res->banners = (array) $info->banners;
                $res->url = $info->homepage;

                if (version_compare(APP_PLUGIN_VERSION, $info->version, '<')) {
                    if (empty($transient->response)) {
                        $transient->response = array();
                    }
                    $transient->response[$res->plugin] = $res;
                } else {
                    if (empty($transient->no_update)) {
                        $transient->no_update = array();
                    }
                    $transient->no_update[$res->plugin] = $res;
                }
            }

        }
        return $transient;
    }


    /**
     * Filter on plugins_api
     * Add plugin information
     *
     * @param mixed    $res
     * @param string   $action
     * @param stdClass $args
     *
     * @return bool|stdClass
     */
    public static function pluginsApiFilter($res, $action, $args) {
        if ($action !== 'plugin_information') {
            return false;
        }

        if ('nicepage' !== $args->slug) {
            return false;
        }

        if ($info = self::_getInfo()) {
            $res = new stdClass();
            $res->name = $info->name;
            $res->slug = $info->slug;
            $res->version = $info->version;
            $res->tested = $info->tested;
            $res->requires = $info->requires;
            $res->author = $info->author;
            $res->author_profile = $info->author_profile;
            $res->homepage = $info->homepage;
            $res->download_link = $info->download_url;
            $res->trunk = $info->download_url;
            $res->last_updated = $info->last_updated;
            $res->sections = (array) $info->sections;
            $res->banners = (array) $info->banners;
            $res->screenshots = (array) $info->screenshots;
            $res->tags = (array) $info->tags;
            return $res;
        }
        return false;

    }

    /**
     * Action on upgrader_process_complete
     * Clear cache
     *
     * @param object $upgrader_object
     * @param array  $options
     */
    public static function updateCompleteAction($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient(self::$_transientKey);
        }
    }

    /**
     * Get URL to update json
     *
     * @return string
     */
    public static function getInfoJsonUrl() {
        return defined('NICEPAGE_UPDATE_URL') ? NICEPAGE_UPDATE_URL : 'https://nicepage.com/downloads/wordpress_nicepage_update.json';
    }

    /**
     * Action on admin_head
     * Fix banner background css
     * Add videos
     */
    public static function adminHeadAction() {
        if (isset($_REQUEST['plugin']) && $_REQUEST['plugin'] === 'nicepage') {
?>
            <style>
                #plugin-information-title {
                    background-position: bottom right;
                }
                .aspect-ratio iframe {
                    width: 100%;
                }
                .aspect-ratio {
                    position: relative;
                    width: 100%;
                    height: 0;
                    padding-bottom: 55%;
                }
                .aspect-ratio iframe {
                    position: absolute;
                    width: 100%;
                    height: 100%;
                    left: 0;
                    top: 0;
                }
            </style>
            <script>
                jQuery(function ($) {
                    $('.embed-video').each(function () {
                        this.innerHTML = '<div class="aspect-ratio"><iframe src="' + this.innerHTML + '" frameborder="0" allowfullscreen=""></iframe></div>';
                    });
                });
            </script>
<?php
        }
    }

    /**
     * Action on init
     * Delete transient when force-check requested
     */
    public static function maybeDeleteTransient() {
        global $pagenow;

        if ($pagenow === 'update-core.php' && isset($_GET['force-check']) && current_user_can('update_plugins')) {
            delete_transient(self::$_transientKey);
        }
    }

    private static $_transientKey = 'upgrade_nicepage';
}

add_filter('site_transient_update_plugins', 'NpUpdater::updatePluginsFilter');
add_filter('plugins_api', 'NpUpdater::pluginsApiFilter', 20, 3);
add_action('upgrader_process_complete', 'NpUpdater::updateCompleteAction', 10, 2);
add_action('admin_head', 'NpUpdater::adminHeadAction');
add_action('init', 'NpUpdater::maybeDeleteTransient');