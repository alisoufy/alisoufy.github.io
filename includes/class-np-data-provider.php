<?php
defined('ABSPATH') or die;

require_once dirname(__FILE__) . '/class-np-shortcodes.php';
require_once dirname(__FILE__) . '/class-np-data-product.php';

class NpDataProvider {

    public $page_id;
    public $preview;
    public $saveAndPublish;

    /**
     * NpDataProvider constructor.
     *
     * @param int|string $page_id        Page Id
     * @param bool|null  $preview        Need or not preview version. Default - $_REQUEST['isPreview']
     * @param bool|null  $saveAndPublish Need or not saveAndPublish page. Default - $_REQUEST['saveAndPublish']
     */
    public function __construct($page_id = 0, $preview = null, $saveAndPublish = true) {
        $this->page_id = $page_id;

        if (is_bool($preview)) {
            $this->preview = $preview;
        } else {
            $this->preview = isset($_REQUEST['isPreview']) && ($_REQUEST['isPreview'] === 'true' || $_REQUEST['isPreview'] === '1');
        }
        if (is_bool($saveAndPublish)) {
            $this->saveAndPublish = $saveAndPublish;
        } else {
            $this->saveAndPublish = isset($_REQUEST['saveAndPublish']) && ($_REQUEST['saveAndPublish'] === 'true' || $_REQUEST['saveAndPublish'] === '1');
        }
        $this->_doBackward();
    }

    /**
     * Returns true if page have Nicepage content
     *
     * @return bool
     */
    public function isNicepage() {
        return !!$this->_getPostMeta('_np_html') || !!$this->_getPostMeta('_np_html_auto_save');
    }

    /**
     * Returns true if page content is empty
     *
     * @return bool
     */
    public function isEmpty() {
        $post = get_post($this->page_id);
        if (!$post) {
            return true;
        }
        return $post->post_content === '';
    }

    /**
     * Returns true is page will be converted, false if it will be edited
     *
     * @return bool
     */
    public function isConvertRequired() {
        return !$this->isNicepage() && !$this->isEmpty();
    }

    /**
     * Wrapper for update_post_meta with wp_slash
     * need to neutralize wp_unslash($meta_value) in update_metadata function
     *
     * @param string $meta_key
     * @param string $meta_value
     */
    private function _updatePostMeta($meta_key, $meta_value) {
        $meta_value = wp_slash($meta_value);
        if ($this->preview) {
            $meta_key .= '_preview';
        }
        if (!$this->preview && !$this->saveAndPublish) {
            $meta_key .= '_auto_save';
        }
        update_post_meta($this->page_id, $meta_key, $meta_value);
    }

    /**
     * Get Post Meta
     *
     * @param string $meta_key Meta Key
     *
     * @return mixed
     */
    private function _getPostMeta($meta_key) {
        if ($this->preview) {
            $result = get_post_meta($this->page_id, $meta_key . '_preview', true);
            if ($result) {
                return $result;
            }
        }
        if (!$this->preview && !$this->saveAndPublish) {
            $result = get_post_meta($this->page_id, $meta_key . '_auto_save', true);
            if ($result) {
                return $result;
            }
        }
        return get_post_meta($this->page_id, $meta_key, true);
    }

    /**
     * Remove Post Meta
     *
     * @param string $meta_key Meta Key
     */
    private function _removePostMeta($meta_key) {
        if ($this->preview) {
            $meta_key .= '_preview';
        }
        if (!$this->preview && $this->saveAndPublish) {
            $meta_key .= '_auto_save';
        }
        delete_post_meta($this->page_id, $meta_key);
    }

    /**
     * Get editable header
     */
    public function getNpHeader() {
        $headerOptionName = 'headerNp';
        if ($this->preview) {
            $headerOptionName .= '_preview';
        }
        if (!$this->preview && !$this->saveAndPublish) {
            $headerOptionName .= '_auto_save';
        }
        $content = get_option($headerOptionName);
        $content = !$content ? get_option('headerNp', true) : $content;
        $content = $this->fixImagePaths($content);
        return $content;
    }

    /**
     * Get editable footer
     */
    public function getNpFooter() {
        $footerOptionName = 'footerNp';
        if ($this->preview) {
            $footerOptionName .= '_preview';
        }
        if (!$this->preview && !$this->saveAndPublish) {
            $footerOptionName .= '_auto_save';
        }
        $content = get_option($footerOptionName);
        $content = !$content ? get_option('footerNp', true) : $content;
        $content = $this->fixImagePaths($content);
        return $content;
    }

    /**
     * Set editable header
     *
     * @param string $data
     */
    public function setNpHeader($data) {
        $headerOptionName = 'headerNp';
        if ($this->preview) {
            $headerOptionName .= '_preview';
        }
        if (!$this->preview && !$this->saveAndPublish) {
            $headerOptionName .= '_auto_save';
        }
        update_option($headerOptionName, $data);
    }

    /**
     * Set editable footer
     *
     * @param string $data
     */
    public function setNpFooter($data) {
        $footerOptionName = 'footerNp';
        if ($this->preview) {
            $footerOptionName .= '_preview';
        }
        if (!$this->preview && !$this->saveAndPublish) {
            $footerOptionName .= '_auto_save';
        }
        update_option($footerOptionName, $data);
    }

    /**
     * Remove editable header
     */
    private function _removeNpHeader() {
        $headerOptionName = 'headerNp';
        if ($this->preview) {
            $headerOptionName .= '_preview';
        }
        if (!$this->preview && $this->saveAndPublish) {
            $headerOptionName .= '_auto_save';
        }
        delete_option($headerOptionName);
    }

    /**
     * Remove editable footer
     */
    private function _removeNpFooter() {
        $footerOptionName = 'footerNp';
        if ($this->preview) {
            $footerOptionName .= '_preview';
        }
        if (!$this->preview && $this->saveAndPublish) {
            $footerOptionName .= '_auto_save';
        }
        delete_option($footerOptionName);
    }

    /**
     * Get page html
     * This html used only in Nicepage editor
     *
     * @return string
     */
    public function getPageHtml() {
        $return = $this->_getPostMeta('_np_html');
        $return = $this->fixImagePaths($return);
        return $return ? $return : '';
    }

    /**
     * Set page html
     *
     * @param string $html
     */
    public function setPageHtml($html) {
        $html = $this->replaceImagePaths($html);
        $this->_updatePostMeta('_np_html', $html);
    }

    /**
     * Get page publish-html
     * This html used in live site
     *
     * @return string
     */
    public function getPagePublishHtml() {
        $return = $this->_getPostMeta('_np_publish_html');
        $return = $this->fixImagePaths($return);
        return $return ? $return : '';
    }

    /**
     * Replace page custom-php code from data-custom-php to custom-php content
     *
     * @param string $html
     *
     * @return string
     */
    public function _replaceCustomPhpPubishHtml($html) {
        if (stripos($html, 'data-custom-php') !== false) {
            return preg_replace_callback('/data-custom-php="([^"]+)"([^>]*)>/', 'NpDataProvider::_phpReplacePublishHtml', $html);
        }
        return $html;
    }

    /**
     * _replaceCustomPhpPubishHtml preg_replace_callback callback
     *
     * @param array $code_php
     *
     * @return string
     */
    private static function _phpReplacePublishHtml($code_php) {
        $code_php[1] = str_replace("&quot;", "'", $code_php[1]);
        return $code_php[2].">".$code_php[1];
    }

    /**
     * Set page publish-html
     *
     * @param string $html
     */
    public function setPagePublishHtml($html) {
        $html = $this->_replaceCustomPhpPubishHtml($html);
        $html = $this->replaceImagePaths($html);
        $this->_updatePostMeta('_np_publish_html', $html);
    }

    /**
     * Set header/footer publish-html
     *
     * @param string $html
     *
     * @return string $html
     */
    public function setHeaderFooterPublishHtml($html) {
        $html = $this->_replaceCustomPhpPubishHtml($html);
        return $html;
    }

    /**
     * Get page styles (css rules without <style> tag)
     *
     * @return string
     */
    public function getPageHead() {
        $return = $this->_getPostMeta('_np_head');
        $return = $this->fixImagePaths($return);
        return $return ? $return : '';
    }

    /**
     * Set page styles
     *
     * @param string $head
     */
    public function setPageHead($head) {
        $head = $this->replaceImagePaths($head);
        $this->_updatePostMeta('_np_head', $head);
    }

    /**
     * Get page fonts (string with <link> tags)
     *
     * @return string
     */
    public function getPageFonts() {
        $return = $this->_getPostMeta('_np_fonts');
        $return = str_replace('|', urlencode('|'), $return);
        return $return ? $return : '';
    }

    /**
     * Set page fonts
     *
     * @param string $html
     */
    public function setPageFonts( $html) {
        $this->_updatePostMeta('_np_fonts', $html);
    }

    /**
     * Get page backlink html
     *
     * @return string
     */
    public function getPageBacklink() {
        $return = $this->_getPostMeta('_np_backlink');
        return $return ? $return : '';
    }

    /**
     * Set page backlink html
     *
     * @param string $html
     */
    public function setPageBacklink($html) {
        $this->_updatePostMeta('_np_backlink', $html);
    }

    /**
     * Get page body class (space separated string of classes)
     *
     * @return string
     */
    public function getPageBodyClass() {
        $return = $this->_getPostMeta('_np_body_class');
        if (!$return) {
            $return = '';
        }
        return $return;
    }

    /**
     * Get page body styles (space separated string of classes)
     *
     * @return string
     */
    public function getPageBodyStyle() {
        $return = $this->_getPostMeta('_np_body_style');
        if (!$return) {
            $return = '';
        }
        $return = $this->fixImagePaths($return);
        return $return;
    }

    /**
     * Get hide header flag
     *
     * @return string
     */
    public function getHideHeader() {
        $return = $this->_getPostMeta('_np_hide_header');
        if (!$return) {
            $return = false;
        }
        return $return;
    }

    /**
     * Set hide header flag
     *
     * @param string $value
     */
    public function setHideHeader($value) {
        $this->_updatePostMeta('_np_hide_header', ($value == false || $value == 'false') ? false : true);
    }

    /**
     * Get hide header flag
     *
     * @return string
     */
    public function getHideFooter() {
        $return = $this->_getPostMeta('_np_hide_footer');
        if (!$return) {
            $return = false;
        }
        return $return;
    }

    /**
     * Set hide footer flag
     *
     * @param string $value
     */
    public function setHideFooter($value) {
        $this->_updatePostMeta('_np_hide_footer', ($value == false || $value =='false') ? false : true);
    }

    /**
     * Get hide backtotop flag
     *
     * @return string
     */
    public function getHideBackToTop() {
        $return = $this->_getPostMeta('_np_hide_backtotop');
        if (!$return) {
            $return = false;
        }
        return $return;
    }

    /**
     * Set hide backtotop flag
     *
     * @param string $value
     */
    public function setHideBackToTop($value) {
        $this->_updatePostMeta('_np_hide_backtotop', ($value == false || $value === 'false') ? false : true);
    }

    /**
     * Set page body class
     *
     * @param string $class
     */
    public function setPageBodyClass($class) {
        $this->_updatePostMeta('_np_body_class', $class);
    }

    /**
     * Set page body styles
     *
     * @param string $styles
     */
    public function setPageBodyStyle($styles) {
        $styles = $this->replaceImagePaths($styles);
        $this->_updatePostMeta('_np_body_style', $styles);
    }

    /**
     * Get page meta description
     * Usage: <meta name="description" content="$description">
     *
     * @return string
     */
    public function getPageDescription() {
        $return = $this->_getPostMeta('page_description');
        return $return ? $return : '';
    }

    /**
     * Set page meta description
     *
     * @param string $description
     */
    public function setPageDescription($description) {
        $this->_updatePostMeta('page_description', $description);
    }

    /**
     * Get page canonical
     *
     * @return string
     */
    public function getPageCanonical() {
        $return = $this->_getPostMeta('page_canonical');
        return $return ? $return : '';
    }

    /**
     * Set page canonical
     *
     * @param string $canonical
     */
    public function setPageCanonical($canonical) {
        $this->_updatePostMeta('page_canonical', $canonical);
    }

    /**
     * Get page meta keywords
     * Usage: <meta name="keywords" content="$keywords">
     *
     * @return string
     */
    public function getPageKeywords() {
        $return = $this->_getPostMeta('page_keywords');
        return $return ? $return : '';
    }

    /**
     * Set page meta keywords
     *
     * @param string $keywords
     */
    public function setPageKeywords($keywords) {
        $this->_updatePostMeta('page_keywords', $keywords);
    }

    /**
     * Get page meta tags
     *
     * @return string
     */
    public function getPageMetaTags() {
        $return = $this->_getPostMeta('page_metaTags');
        return $return ? $return : '';
    }

    /**
     * Set page meta tags
     *
     * @param string $meta_tags
     */
    public function setPageMetaTags($meta_tags) {
        $this->_updatePostMeta('page_metaTags', $meta_tags);
    }

    /**
     * Set page meta generator
     *
     * @param string $metaGeneratorContent
     */
    function setPageMetaGenerator($metaGeneratorContent) {
        $this->_updatePostMeta('page_metaGeneratorContent', $metaGeneratorContent);
    }

    /**
     * Get page meta generator
     *
     * @return string
     */
    public function getPageMetaGenerator() {
        return $this->_getPostMeta('page_metaGeneratorContent');
    }

    /**
     * Get page custom head html
     *
     * @return string
     */
    public function getPageCustomHeadHtml() {
        $return = $this->_getPostMeta('page_customHeadHtml');
        return $return ? $return : '';
    }

    /**
     * Set page custom head html
     *
     * @param string $custom_head_html
     */
    public function setPageCustomHeadHtml($custom_head_html) {
        $this->_updatePostMeta('page_customHeadHtml', $custom_head_html);
    }

    /**
     * Get page title
     * Usage: <title>$title</title>
     *
     * @return string
     */
    public function getPageTitleInBrowser() {
        $return = $this->_getPostMeta('page_title');
        return $return ? $return : '';
    }

    /**
     * Set page title
     *
     * @param string $title
     */
    public function setPageTitleInBrowser($title) {
        $this->_updatePostMeta('page_title', $title);
    }

    /**
     * Get forms data
     *
     * @return string
     */
    public function getFormsData() {
        $return = $this->_getPostMeta('formsData');
        return $return ? $return : '';
    }

    /**
     * Set forms data
     *
     * @param string $data
     */
    public function setFormsData($data) {
        $this->_updatePostMeta('formsData', $data);
    }

    /**
     * Get dialogs data
     *
     * @return string
     */
    public function getDialogsData() {
        $return = $this->_getPostMeta('dialogs');
        return $return ? $return : '';
    }

    /**
     * Set dialogs data
     *
     * @param string $data
     */
    public function setDialogsData($data) {
        $this->_updatePostMeta('dialogs', $data);
    }


    /**
     * Set publish dialogs
     *
     * @param string $data Data
     */
    public function setPublishDialogs($data) {
        if ($data) {
            if (count($data) > 0) {
                $dialogsHtml = '';
                foreach ($data as $dialog) {
                    $dialogsHtml .= $dialog['publishHtml'];
                }
                NpForms::updateForms(0, 'dialogs', $dialogsHtml);
            }
            NpMeta::update('publishDialogs', json_encode($data));
        }
    }

    /**
     * Get active publish dialogs
     *
     * @param string $html   Html
     * @param string $header Header
     * @param string $footer Footer
     *
     * @return string
     */
    public function getActivePublishDialogs($html, $header = '', $footer = '') {
        $result = '';

        $addedAnchors = array();
        if ($header && isset($header['dialogs']) && $header['dialogs']) {
            $headerDialogs = json_decode($header['dialogs'], true);
            foreach ($headerDialogs as $headerDialog) {
                $result .= $headerDialog['publishHtml'] . '<style>' . $headerDialog['publishCss'] . '</style>';
                array_push($addedAnchors, $headerDialog['sectionAnchorId']);
            }
        }

        if ($footer && isset($footer['dialogs']) && $footer['dialogs']) {
            $footerDialogs = json_decode($footer['dialogs'], true);
            foreach ($footerDialogs as $footerDialog) {
                $result .= $footerDialog['publishHtml'] . '<style>' . $footerDialog['publishCss'] . '</style>';
                array_push($addedAnchors, $footerDialog['sectionAnchorId']);
            }
        }

        $pageDialogs = $this->getDialogsData();
        if ($pageDialogs) {
            foreach ($pageDialogs as $pageDialog) {
                $result .= $pageDialog['publishHtml'] . '<style>' . $pageDialog['publishCss'] . '</style>';
                array_push($addedAnchors, $pageDialog['sectionAnchorId']);
            }
        }

        $publishDialogJson = NpMeta::get('publishDialogs');
        if ($publishDialogJson) {
            $publishDialogs = json_decode($publishDialogJson, true);
            foreach ($publishDialogs as $dialog) {
                if (strpos($html, $dialog['sectionAnchorId']) !== false && !in_array($dialog['sectionAnchorId'], $addedAnchors)) {
                    $result .= $dialog['publishHtml'] . '<style>' . $dialog['publishCss'] . '</style>';
                }
            }
        }
        return $result;
    }

    /**
     * Add dialog to body
     *
     * @param string $html   Html
     * @param string $header Header
     * @param string $footer Footer
     *
     * @return mixed
     */
    public function addPublishDialogToBody($html, $header = '', $footer = '') {
        $publishDialogs = $this->getActivePublishDialogs($html, $header, $footer);
        if ($publishDialogs) {
            $publishDialogs = Nicepage::processContent($publishDialogs, true, 'dialogs');
            global $post;
            $publishDialogs = Nicepage::processFormCustomPhp($publishDialogs, $post->ID);
            $html = str_replace('</body>', $publishDialogs . '</body>', $html);
        }
        return $html;
    }

    /**
     * Get site style CSS
     *
     * @return string
     */
    public function getStyleCss() {
        global $post;
        $data_provider = np_data_provider($post->ID);
        $css_parts = NpMeta::get('site_style_css_parts');
        if ($css_parts) {
            $css_parts = json_decode($css_parts, true);
            $ids_json_str = $this->_getPostMeta('_np_site_style_css_used_ids');
            if ($ids_json_str === false || $ids_json_str === '') {
                $this->_updateUsedIds($css_parts, $this->getPagePublishHtml());
                $ids_json_str = $this->_getPostMeta('_np_site_style_css_used_ids');
            }
            $used_ids = json_decode($ids_json_str, true);

            $header_footer_json_str = NpMeta::get('header_footer_css_used_ids');
            $header_footer_css_used_ids = $header_footer_json_str ? json_decode($header_footer_json_str, true) : array();

            $cookies_json_str = NpMeta::get('cookies_css_used_ids');
            $cookies_css_used_ids = $cookies_json_str ? json_decode($cookies_json_str, true) : array();

            $result = '';

            foreach ($css_parts as $part) {
                if ($part['type'] !== 'color' || !empty($used_ids[$part['id']]) || !empty($header_footer_css_used_ids[$part['id']]) || !empty($cookies_css_used_ids[$part['id']])) {
                    $result .= $part['css'];
                }
            }
            $result = $data_provider->fixImagePaths($result);
            return $result;
        }
        // for old versions:

        $css = NpMeta::get('site_style_css');
        if (!$css) {
            $css = '';
        }
        if (substr($css, 0, 6) === '<style') {
            // backward compatibility
            $css = preg_replace('#</?style[^>]*>#', '', $css);
            $css = $css . file_get_contents(APP_PLUGIN_PATH . 'assets/css/nicepage-dynamic.css');
        }
        $css = $data_provider->fixImagePaths($css);
        return $css;
    }

    /**
     * Save site CSS
     * Save CSS id's used in this page
     *
     * @param string $styles
     * @param string $publish_html
     * @param string $publishHeaderFooter
     * @param string $publishCookiesSection
     */
    public function setStyleCss($styles, $publish_html, $publishHeaderFooter = '', $publishCookiesSection = '') {
        $split = preg_split('#(\/\*begin-color [^*]+\*\/[\s\S]*?\/\*end-color [^*]+\*\/)#', $styles, -1, PREG_SPLIT_DELIM_CAPTURE);
        $parts = array();
        foreach ($split as &$part) {
            $part = trim($part);
            if (!$part) {
                continue;
            }

            if (preg_match('#\/\*begin-color ([^*]+)\*\/#', $part, $m)) {
                $id = 'color_' . $m[1];
                $parts[] = array(
                    'type' => 'color',
                    'id' => $id,
                    'css' => $part,
                );
            } else {
                $parts[] = array(
                    'type' => '',
                    'css' => $part,
                );
            }
        }

        NpMeta::update('site_style_css_parts', json_encode($parts));
        NpMeta::update('site_style_css', ''); // clear old field
        if ($publishHeaderFooter) {
            $used_ids = array();
            foreach ($parts as &$part) {
                if (isset($part['id']) && strpos($publishHeaderFooter, preg_replace('#^color_#', '', $part['id'])) !== false) {
                    $used_ids[$part['id']] = true;
                }
            }
            NpMeta::update('header_footer_css_used_ids', json_encode($used_ids));
        }
        if ($publishCookiesSection) {
            $cookies_used_ids = array();
            foreach ($parts as &$part) {
                if (isset($part['id']) && strpos($publishCookiesSection, preg_replace('#^color_#', '', $part['id'])) !== false) {
                    $cookies_used_ids[$part['id']] = true;
                }
            }
            NpMeta::update('cookies_css_used_ids', json_encode($cookies_used_ids));
        }
        if ($this->page_id) {
            $this->_updateUsedIds($parts, $publish_html);
        }
    }

    /**
     * Update cache for used style id's
     *
     * @param array  $style_parts
     * @param string $publish_html
     */
    private function _updateUsedIds($style_parts, $publish_html) {
        $used_ids = array();
        foreach ($style_parts as &$part) {
            if (isset($part['id']) && strpos($publish_html, preg_replace('#^color_#', '', $part['id'])) !== false) {
                $used_ids[$part['id']] = true;
            }
        }
        $this->_updatePostMeta('_np_site_style_css_used_ids', json_encode($used_ids));
    }

    /**
     * Set site settings
     *
     * @param array|string $settings
     */
    public function setSiteSettings($settings) {
        if ($settings && is_string($settings)) {
            $settings = json_decode($settings, true);
        }
        if (empty($settings)) {
            return;
        }

        update_option('np_hide_backlink', _arr($settings, 'showBrand') === 'false');
        NpMeta::update('site_settings', wp_json_encode($settings));
        NpMeta::update('backToTop', _arr($settings, 'backToTop'));
        NpImportNotice::replaceCaptchaKeysContact7Form();
    }

    /**
     * Get site settings
     *
     * @param null $assoc Get assoc array if true
     *
     * @return false|string
     */
    public static function getSiteSettings($assoc = null) {
        $site_settings = json_decode(NpMeta::get('site_settings'), $assoc);
        if (!$site_settings) {
            $site_settings = json_decode('{}', $assoc);
        }
        return $site_settings;
    }

    /**
     * Clear post meta props
     */
    public function clear() {
        $this->_removePostMeta('_np_html');
        $this->_removePostMeta('_np_publish_html');
        $this->_removePostMeta('_np_head');
        $this->_removePostMeta('_np_fonts');
        $this->_removePostMeta('_np_backlink');
        $this->_removePostMeta('_np_body_class');
        $this->_removePostMeta('_np_body_style');
        $this->_removePostMeta('_np_hide_header');
        $this->_removePostMeta('_np_hide_footer');
        $this->_removePostMeta('_np_site_style_css_used_ids');
        $this->_removePostMeta('page_description');
        $this->_removePostMeta('page_keywords');
        $this->_removePostMeta('page_metaTags');
        $this->_removePostMeta('page_customHeadHtml');
        $this->_removePostMeta('page_title');
        $this->_removeNpHeader();
        $this->_removeNpFooter();
    }

    /**
     * Backward for nicepage meta values
     */
    private function _doBackward() {
        if (get_post_meta($this->page_id, '_upage_sections_html', true)) {
            foreach (array('html', 'publish_html', 'head', 'fonts', 'backlink', 'body_class') as $prop) {
                $old_meta_key = "_upage_sections_$prop";
                $new_meta_key = "_np_$prop";
                $meta_value = get_post_meta($this->page_id, $old_meta_key, true);
                update_post_meta($this->page_id, $new_meta_key, $meta_value);
                delete_post_meta($this->page_id, $old_meta_key);
            }
            update_post_meta($this->page_id, '_np_template', get_post_meta($this->page_id, '_upage_template', true));
            update_post_meta($this->page_id, '_np_forms', get_post_meta($this->page_id, '_upage_forms', true));
        }
    }

    /**
     * Fix image paths
     *
     * @param string $content Content
     *
     * @return mixed
     */
    public function fixImagePaths($content) {
        return str_replace('[[site_path_live]]', get_site_url(), $content);
    }

    /**
     * Replace image paths to placeholder
     *
     * @param string $content Content
     *
     * @return mixed
     */
    public function replaceImagePaths($content) {
        return str_replace(get_site_url(), '[[site_path_live]]', $content);
    }
}

if (!function_exists('np_data_provider')) {
    /**
     * Construct NpDataProvider object
     *
     * @param int|string $post_id        Post Id
     * @param bool|null  $preview        Need or not preview version. Default - $_REQUEST['isPreview']
     * @param bool|null  $saveAndPublish Need or not autoSave page. Default - $_REQUEST['saveAndPublish']
     *
     * @return NpDataProvider
     */
    function np_data_provider($post_id = 0, $preview = null, $saveAndPublish = true)
    {
        return new NpDataProvider($post_id, $preview, $saveAndPublish);
    }
} else {
    sleep(1);
    /**
     * Construct NpDataProvider object
     *
     * @param int|string $post_id        Post Id
     * @param bool|null  $preview        Need or not preview version. Default - $_REQUEST['isPreview']
     * @param bool|null  $saveAndPublish Need or not autoSave page. Default - $_REQUEST['saveAndPublish']
     *
     * @return NpDataProvider
     */
    function np_data_provider($post_id = 0, $preview = null, $saveAndPublish = true)
    {
        return new NpDataProvider($post_id, $preview, $saveAndPublish);
    }
}