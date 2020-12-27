<?php
defined('ABSPATH') or die;

require_once dirname(__FILE__) . '/class-np-shortcodes.php';
require_once dirname(__FILE__) . '/class-np-svg-uploader.php';

class Nicepage {

    /**
     * Filter on the_content
     *
     * @param string $content
     *
     * @return string
     */
    public static function theContentFilter($content) {
        $post = get_post();
        //if $content not post content we need only return
        if ($content === '' && $post->post_content !== '') {
            return $content;
        }
        if ($post) {
            $sections_html = self::html($post->ID);
            if ($sections_html) {
                $content = $sections_html;
            }
        }
        return $content;
    }

    /**
     * Html preg_replace_callback callback
     *
     * @param array $code_php
     *
     * @return string
     */
    private static function _phpReplaceHtml($code_php) {
        if (stripos($code_php[1], '<?php') === 0 && stripos($code_php[1], '?>') === strlen($code_php[1])-2) {
            $code_php[1] = str_replace("<?php", "", $code_php[1]);
            $code_php[1] = str_replace("?>", "", $code_php[1]);
            ob_start();
            eval($code_php[1]);
            $string = ob_get_contents();
            ob_end_clean();
            $code_php[1] = $string;
        } elseif (stripos($code_php[1], '<?php') === 0 && stripos($code_php[1], '?>') !== strlen($code_php[1])-2 OR stripos($code_php[1], '<?php') !== 0 && stripos($code_php[1], '<?php') !== false) {
            /* For more than one opening and closing php tags and attempts to insert html */
            preg_match_all("/(<\?([\s\S]+?)?>)/", $code_php[1], $matches);
            $code_php[1] = "";
            foreach ($matches[0] as &$element_php) {
                $code_php[1] = $code_php[1].$element_php;
            }
            $code_php[1] = str_replace("<?php", "", $code_php[1]);
            $code_php[1] = str_replace("?>", "", $code_php[1]);
            ob_start();
            eval($code_php[1]);
            $string = ob_get_contents();
            ob_end_clean();
            $code_php[1] = $string;
        }
        return $code_php[1];
    }

    /**
     * Get processed publishHtml for page
     *
     * @param string|int $post_id
     *
     * @return string
     */
    public static function html($post_id) {
        if (! post_password_required($post_id)) {
            $sections_html = np_data_provider($post_id)->getPagePublishHtml();
        } else {
            return "<div style='text-align:center'>".get_the_password_form($post_id)."</div>";
        }

        if ($sections_html) {
            $sections_html = self::processFormCustomPhp($sections_html, $post_id);
            $sections_html = self::processContent($sections_html, false);
            if (self::isAutoResponsive($post_id)) {
                $sections_html = self::_getAutoResponsiveScript($post_id) . $sections_html;
            }
            if (!self::isNpTheme()) {
                $template_page = NpMetaOptions::get($post_id, 'np_template');
                if ($template_page == "html") {
                    $sections_html = '<div class="' . implode(' ', self::bodyClassFilter(array())) . '" style="' . self::bodyStyleFilter() . '">' . $sections_html . "</div>";
                } else {
                    $sections_html = '<div class="nicepage-container"><div class="' . implode(' ', self::bodyClassFilter(array())) . '" style="' . self::bodyStyleFilter() . '">' . $sections_html . "</div></div>";
                }
            }
        }

        return $sections_html;
    }

    /**
     * Filter on body_class
     *
     * Add page classes to <body>
     *
     * @param string[] $classes
     *
     * @return string[]
     */
    public static function bodyClassFilter($classes) {
        if (self::isHtmlQuery()) {
            return $classes;
        }

        $post = get_post();
        if ($post) {
            $class = np_data_provider($post->ID)->getPageBodyClass();
            if ($class && is_singular()) {
                $classes[] = $class;

                if (self::isAutoResponsive($post->ID)) {
                    $initial_mode = self::_getInitialResponsiveMode($post->ID);
                    foreach (array_reverse(self::$responsiveModes) as $mode) {
                        $classes[] = self::$responsiveBorders[$mode]['CLASS'];

                        if ($mode === $initial_mode) {
                            break;
                        }
                    }
                }
            }
        }
        return $classes;
    }

    /**
     * Filter on body style
     *
     * Add page style attribute to <body>
     *
     * @return string
     */
    public static function bodyStyleFilter() {
        $post = get_post();
        if ($post) {
            $style = np_data_provider($post->ID)->getPageBodyStyle();
            return $style && is_singular() ? $style : '';
        }
        return '';
    }

    /**
     * Action on wp_footer
     * Print backlink html
     */
    public static function wpFooterAction() {
        $post = get_post();
        if (!$post) {
            global $post;
        }
        $is_np_page = np_data_provider($post->ID)->isNicepage();
        // if not our theme code need render only on the our pages
        $renderPages = self::isNpTheme() ? ($is_np_page || is_single() || is_home()) : $is_np_page;
        if ($post && $renderPages) {
            $backlink = np_data_provider($post->ID)->getPageBacklink();
            if ($backlink && get_option('np_hide_backlink') || isset($GLOBALS['theme_backlink'])) {
                // back compat for old versions
                // backlink's html isn't empty even np_hide_backlink is true
                $backlink = str_replace('u-backlink', 'u-backlink u-hidden', $backlink);
            }
            echo $backlink;

            $bodyClass = implode(' ', self::bodyClassFilter(array()));
            $bodyStyle = self::bodyStyleFilter();
            $template = '<div class="nicepage-container"><div class="' . $bodyClass . '" style="' . $bodyStyle . '">{content}</div></div>';

            $data_provider = np_data_provider($post->ID);
            $sections_html = $data_provider->getPagePublishHtml();
            $cookiesConsent = NpMeta::get('cookiesConsent') ? json_decode(NpMeta::get('cookiesConsent'), true) : '';
            if ($cookiesConsent && (!$cookiesConsent['hideCookies'] || $cookiesConsent['hideCookies'] === 'false') && $sections_html && !self::isNpTheme()) {
                $cookiesConsent['publishCookiesSection'] = $data_provider->fixImagePaths($cookiesConsent['publishCookiesSection']);
                echo str_replace('{content}', $cookiesConsent['publishCookiesSection'], $template);
            }

            $hideBackToTop = $data_provider->getHideBackToTop();
            if (!$hideBackToTop && np_data_provider($post->ID)->isNicepage()) {
                echo str_replace('{content}', NpMeta::get('backToTop'), $template);
            }

            $template_page = NpMetaOptions::get($post->ID, 'np_template');
            if ($template_page !== "html") {
                $publishDialogs = $data_provider->getActivePublishDialogs($sections_html);
                echo str_replace('{content}', $publishDialogs, $template);
            }
        }
    }

    /**
     * Function for publish_html postprocessing
     *
     * @param string $content
     * @param bool   $isPublic
     * @param string $templateName
     *
     * @return mixed|string
     **/
    public static function processContent($content, $isPublic = true, $templateName = '') {
        //$content = self::_productLinksFilter($content);
        if ($isPublic) {
            $content = self::processControls($content);
        }
        $content = self::_processForms($content, $templateName);
        $content = self::_prepareShortcodes($content);
        $content = self::_prepareCustomPhp($content);
        $content = self::_processBlogControl($content);
        if (function_exists('wc_get_product')) {
            $content = self::_processProducts($content);
            $content = self::_processCartControl($content);
        }
        $content = do_shortcode($content);
        $content = NpWidgetsImporter::processLink($content);
        return $content;
    }

    /**
     * @param string $content
     * @param string $pageId
     */
    public static function processFormCustomPhp($content, $pageId) {
        if ($pageId) {
            $plgDir = dirname(plugins_url('', __FILE__));
            $formFile = $plgDir . '/templates/form.php';
            $content = preg_replace(
                '/(<form[^>]*action=[\'\"]+)\[\[form\-(.*?)\]\]([\'\"][^>]*source=[\'\"]customphp)/',
                '$1' . $formFile . '?id=' . $pageId . '&formId=$2$3',
                $content
            );
        }
        return $content;
    }

    /**
     * Process custom php controls
     *
     * @param string $content
     *
     * @return string
     */
    private static function _prepareCustomPhp($content) {
        return preg_replace_callback('/<!--custom_php-->([\s\S]+?)<!--\/custom_php-->/', 'Nicepage::_phpReplaceHtml', $content);
    }

    private static $_formIdx;
    private static $_formsSources;
    public static $_post;
    public static $_posts;
    public static $_postId = 0;
    public static $_productVariationId = 0;
    public static $_product;
    public static $_productData;

    /**
     * Process blog controls
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _processBlogControl($content) {
        $content = preg_replace_callback(
            '/<\!--blog-->([\s\S]+?)<\!--\/blog-->/',
            function ($blogMatch) {
                $blogHtml = $blogMatch[1];
                $blogOptions = array();
                if (preg_match('/<\!--blog_options_json--><\!--([\s\S]+?)--><\!--\/blog_options_json-->/', $blogHtml, $matches)) {
                    $blogOptions = json_decode($matches[1], true);
                    $blogHtml = str_replace($matches[0], '', $blogHtml);
                }
                $blogSourceType = isset($blogOptions['type']) ? $blogOptions['type'] : '';
                if ($blogSourceType === 'Tags') {
                    $blogSource = 'tags:' . (isset($blogOptions['tags']) && $blogOptions['tags'] ? $blogOptions['tags'] : '');
                } else {
                    $blogSource = isset($blogOptions['source']) && $blogOptions['source'] ? $blogOptions['source'] : false;
                }
                // if $blogSource == false - get last posts
                Nicepage::$_posts = NpAdminActions::getPosts($blogSource);
                return preg_replace_callback(
                    '/<!--blog_post-->([\s\S]+?)<!--\/blog_post-->/',
                    function ($content) {
                        if (count(Nicepage::$_posts) < 1) {
                            return ''; // remove cell, if post is missing
                        }
                        Nicepage::$_post = array_shift(Nicepage::$_posts);
                        Nicepage::$_postId = Nicepage::$_post->ID;
                        $content[1] = preg_replace_callback(
                            '/<!--blog_post_header-->([\s\S]+?)<!--\/blog_post_header-->/',
                            function ($content) {
                                $postTitle = Nicepage::$_post->post_title;
                                $postUrl = get_permalink(Nicepage::$_postId);
                                $postUrl = $postUrl ? $postUrl : '#';
                                if ($postUrl) {
                                    $content[1] = preg_replace('/href=[\'|"][\s\S]+?[\'|"]/', 'href="' . $postUrl . '"', $content[1]);
                                    if (isset($postTitle) && $postTitle != '') {
                                        $content[1] = preg_replace('/<!--blog_post_header_content-->([\s\S]+?)<!--\/blog_post_header_content-->/', $postTitle, $content[1]);
                                    }
                                }
                                return $content[1];
                            },
                            $content[1]
                        );
                        $content[1] = preg_replace_callback(
                            '/<!--blog_post_content-->([\s\S]+?)<!--\/blog_post_content-->/',
                            function ($content) {
                                $postContent = plugin_trim_long_str(NpAdminActions::getTheExcerpt(Nicepage::$_post->ID), 150);
                                if (isset($postContent) && $postContent != '') {
                                    $content[1] = preg_replace('/<!--blog_post_content_content-->([\s\S]+?)<!--\/blog_post_content_content-->/', $postContent, $content[1]);
                                }
                                return $content[1];
                            },
                            $content[1]
                        );
                        $content[1] = preg_replace_callback(
                            '/<!--blog_post_image-->([\s\S]+?)<!--\/blog_post_image-->/',
                            function ($content) {
                                $imageHtml = $content[1];
                                $thumb_id = get_post_thumbnail_id(Nicepage::$_postId);
                                if ($thumb_id) {
                                    $url = get_attached_file($thumb_id);
                                } else {
                                    preg_match('/<img[\s\S]+?src=[\'"]([\s\S]+?)[\'"] [\s\S]+?>/', Nicepage::$_post->post_content, $regexResult);
                                    if (count($regexResult) < 1) {
                                        return '';
                                    }
                                    $url = $regexResult[1];
                                }
                                $isBackgroundImage = strpos($imageHtml, '<div') !== false ? true : false;
                                $uploads = wp_upload_dir();
                                $url = str_replace($uploads['basedir'], $uploads['baseurl'], $url);
                                if ($isBackgroundImage) {
                                    if (strpos($imageHtml, 'data-bg') !== false) {
                                        $imageHtml = preg_replace('/(data-bg=[\'"])([\s\S]+?)([\'"])/', '$1url(' . $url . ')$3', $imageHtml);
                                    } else {
                                        $imageHtml = str_replace('<div', '<div' . ' style="background-image:url(' . $url . ')"', $imageHtml);
                                    }
                                } else {
                                    $imageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $url . '$3', $imageHtml);
                                }

                                return $imageHtml;



                            },
                            $content[1]
                        );
                        $content[1] = preg_replace_callback(
                            '/<!--blog_post_readmore-->([\s\S]+?)<!--\/blog_post_readmore-->/',
                            function ($content) {
                                $content[1] = preg_replace('/href=[\'|"][\s\S]+?[\'|"]/', 'href="' . get_permalink(Nicepage::$_postId) . '"', $content[1]);
                                return preg_replace('/<!--blog_post_readmore_content-->([\s\S]+?)<!--\/blog_post_readmore_content-->/', sprintf(__('Read more', 'nicepage')), $content[1]);
                            },
                            $content[1]
                        );
                        $content[1] = preg_replace_callback(
                            '/<!--blog_post_metadata-->([\s\S]+?)<!--\/blog_post_metadata-->/',
                            function ($content) {
                                $content[1] = preg_replace_callback(
                                    '/<!--blog_post_metadata_author-->([\s\S]+?)<!--\/blog_post_metadata_author-->/',
                                    function ($content) {
                                        $authorId = Nicepage::$_post->post_author;
                                        $authorName = get_the_author_meta('display_name', $authorId);
                                        $authorLink = get_author_posts_url($authorId);
                                        if ($authorName == '') {
                                            $authorName = 'User';
                                            $authorLink = '#';
                                        }
                                        $link = '<a class="url u-textlink" href="' . $authorLink . '" title="' . esc_attr(sprintf(__('View all posts by %s', 'nicepage'), $authorName)) . '"><span class="fn n">' . $authorName . '</span></a>';
                                        return $content[1] = preg_replace('/<!--blog_post_metadata_author_content-->([\s\S]+?)<!--\/blog_post_metadata_author_content-->/', $link, $content[1]);
                                    },
                                    $content[1]
                                );
                                $content[1] = preg_replace_callback(
                                    '/<!--blog_post_metadata_date-->([\s\S]+?)<!--\/blog_post_metadata_date-->/',
                                    function ($content) {
                                        $postDate = get_the_date('', Nicepage::$_postId);
                                        return $content[1] = preg_replace('/<!--blog_post_metadata_date_content-->([\s\S]+?)<!--\/blog_post_metadata_date_content-->/', $postDate, $content[1]);
                                    },
                                    $content[1]
                                );
                                $content[1] = preg_replace_callback(
                                    '/<!--blog_post_metadata_category-->([\s\S]+?)<!--\/blog_post_metadata_category-->/',
                                    function ($content) {
                                        $postCategories = str_replace(
                                            '<a',
                                            '<a class="u-textlink"',
                                            get_the_category_list(_x(', ', 'Used between list items, there is a space after the comma.', 'nicepage'), '', Nicepage::$_postId)
                                        );
                                        return $content[1] = preg_replace('/<!--blog_post_metadata_category_content-->([\s\S]+?)<!--\/blog_post_metadata_category_content-->/', $postCategories, $content[1]);
                                    },
                                    $content[1]
                                );
                                $content[1] = preg_replace_callback(
                                    '/<!--blog_post_metadata_comments-->([\s\S]+?)<!--\/blog_post_metadata_comments-->/',
                                    function ($content) {
                                        $link = '<a class="u-textlink" href="' . get_comments_link(Nicepage::$_postId) . '">' . sprintf(__('Comments (%d)', 'nicepage'), (int)get_comments_number(Nicepage::$_postId)) . '</a>';
                                        return $content[1] = preg_replace('/<!--blog_post_metadata_comments_content-->([\s\S]+?)<!--\/blog_post_metadata_comments_content-->/', $link, $content[1]);
                                    },
                                    $content[1]
                                );
                                $content[1] = preg_replace_callback(
                                    '/<!--blog_post_metadata_edit-->([\s\S]+?)<!--\/blog_post_metadata_edit-->/',
                                    function ($content) {
                                        $link = '<a href="' . get_edit_post_link(Nicepage::$_postId) . '">'. translate('Edit') . '</a>';
                                        return $content[1] = preg_replace('/<!--blog_post_metadata_edit_content-->([\s\S]+?)<!--\/blog_post_metadata_edit_content-->/', $link, $content[1]);
                                    },
                                    $content[1]
                                );
                                return $content[1];
                            },
                            $content[1]
                        );
                        $content[1] = preg_replace_callback(
                            '/<!--blog_post_tags-->([\s\S]+?)<!--\/blog_post_tags-->/',
                            function ($content) {
                                $tags = get_the_tag_list('', _x(', ', 'Used between list items, there is a space after the comma.', 'nicepage'), '', Nicepage::$_postId);
                                if ($tags) {
                                    $content[1] = $content[1] = preg_replace('/<!--blog_post_tags_content-->([\s\S]+?)<!--\/blog_post_tags_content-->/', $tags, $content[1]);
                                }
                                return $content[1];
                            },
                            $content[1]
                        );
                        return $content[1];
                    },
                    $blogHtml
                );
            },
            $content
        );
        return $content;
    }

    /**
     * Process products
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _processProducts($content) {
        $content = Nicepage::_processProductsListControl($content);
        $content = Nicepage::_processProductControl($content);
        return $content;
    }

    public static $_typeControl;

    /**
     * Process Product List Control
     *
     * @param string $content Page content
     *
     * @return string|string[]|null
     */
    private static function _processProductsListControl($content) {
        return preg_replace_callback(
            '/<\!--products-->([\s\S]+?)<\!--\/products-->/',
            function ($productsMatch) {
                $productsHtml = $productsMatch[1];
                $productsOptions = array();
                if (preg_match('/<\!--products_options_json--><\!--([\s\S]+?)--><\!--\/products_options_json-->/', $productsHtml, $matches)) {
                    $productsOptions = json_decode($matches[1], true);
                    $productsHtml = str_replace($matches[0], '', $productsHtml);
                }
                $productsSourceType = isset($productsOptions['type']) ? $productsOptions['type'] : '';
                if ($productsSourceType === 'Tags') {
                    $productsSource = 'tags:' . (isset($productsOptions['tags']) && $productsOptions['tags'] ? $productsOptions['tags'] : '');
                } else if ($productsSourceType === 'products-featured') {
                    $productsSource = 'featured';
                } else {
                    $productsSource = isset($productsOptions['source']) && $productsOptions['source'] ? $productsOptions['source'] : false;
                }
                // if $productsSource == false - get last posts
                Nicepage::$_posts = NpAdminActions::getPosts($productsSource, 20, 'product');
                Nicepage::$_typeControl = 'products';
                return Nicepage::_processProductItem($productsHtml);
            },
            $content
        );
    }

    /**
     * Process Product control
     *
     * @param string $content Page content
     *
     * @return string|string[]|null
     */
    private static function _processProductControl($content) {
        return preg_replace_callback(
            '/<\!--product-->([\s\S]+?)<\!--\/product-->/',
            function ($productMatch) {
                $productHtml = $productMatch[1];
                $productOptions = array();
                if (preg_match('/<\!--product_options_json--><\!--([\s\S]+?)--><\!--\/product_options_json-->/', $productHtml, $matches)) {
                    $productOptions = json_decode($matches[1], true);
                    $productHtml = str_replace($matches[0], '', $productHtml);
                }
                $productsSource = isset($productOptions['source']) && $productOptions['source'] ? $productOptions['source'] : false;
                // if $productsSource == false - get last posts
                if ($productsSource) {
                    Nicepage::$_posts = NpAdminActions::getPost($productsSource);
                } else {
                    Nicepage::$_posts = NpAdminActions::getPosts($productsSource, 20, 'product');
                }
                Nicepage::$_typeControl = 'product';
                return Nicepage::_processProductItem($productHtml);
            },
            $content
        );
    }

    public static $_tabItemIndex = 0;
    public static $_tabContentIndex = 0;

    /**
     * Process product item
     *
     * @param string $content Page content
     *
     * @return string|string[]|null
     */
    private static function _processProductItem($content) {
        return preg_replace_callback(
            '/<!--product_item-->([\s\S]+?)<!--\/product_item-->/',
            function ($productItemMatch) {
                $productItemHtml = $productItemMatch[1];
                if (count(Nicepage::$_posts) < 1) {
                    return ''; // remove cell, if post is missing
                }
                Nicepage::$_post = array_shift(Nicepage::$_posts);
                Nicepage::$_postId = Nicepage::$_post->ID;
                Nicepage::$_productData = np_data_product(Nicepage::$_postId);
                if (count(Nicepage::$_productData) > 0) {
                    Nicepage::$_product = Nicepage::$_productData['product'];
                    $productItemHtml = preg_replace_callback(
                        '/<!--product_title-->([\s\S]+?)<!--\/product_title-->/',
                        function ($titleMatch) {
                            $titleHtml = $titleMatch[1];
                            $productTitle = Nicepage::$_productData['title'];
                            $productTitle = $productTitle ? $productTitle : Nicepage::$_post->post_title;
                            $postUrl = get_permalink(Nicepage::$_postId);
                            $postUrl = $postUrl ? $postUrl : '#';
                            if ($postUrl) {
                                $titleHtml = preg_replace('/href=[\'|"][\s\S]+?[\'|"]/', 'href="' . $postUrl . '"', $titleHtml);
                                if (isset($productTitle) && $productTitle != '') {
                                    $titleHtml = preg_replace('/<!--product_title_content-->([\s\S]+?)<!--\/product_title_content-->/', $productTitle, $titleHtml);
                                }
                            }
                            return $titleHtml;
                        },
                        $productItemHtml
                    );
                    $productItemHtml = preg_replace_callback(
                        '/<!--product_content-->([\s\S]+?)<!--\/product_content-->/',
                        function ($textMatch) {
                            $textHtml = $textMatch[1];
                            $productContent = Nicepage::$_productData['desc'];
                            if (isset($productContent) && $productContent != '') {
                                $textHtml = preg_replace('/<!--product_content_content-->([\s\S]+?)<!--\/product_content_content-->/', $productContent, $textHtml);
                            }
                            return $textHtml;
                        },
                        $productItemHtml
                    );
                    $productItemHtml = preg_replace_callback(
                        '/<!--product_image-->([\s\S]+?)<!--\/product_image-->/',
                        function ($imageMatch) {
                            $imageHtml = $imageMatch[1];
                            $url = Nicepage::$_productData['image_url'];
                            if (!$url) {
                                return $imageHtml;
                            }
                            $isBackgroundImage = strpos($imageHtml, '<div') !== false ? true : false;
                            $link = get_permalink(Nicepage::$_postId);
                            if ($isBackgroundImage) {
                                $imageHtml = str_replace('<div', '<div data-product-control="' . $link . '"', $imageHtml);
                                if (strpos($imageHtml, 'data-bg') !== false) {
                                    $imageHtml = preg_replace('/(data-bg=[\'"])([\s\S]+?)([\'"])/', '$1url(' . $url . ')$3', $imageHtml);
                                } else {
                                    $imageHtml = str_replace('<div', '<div' . ' style="background-image:url(' . $url . ')"', $imageHtml);
                                }
                            } else {
                                $imageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $url . '$3 style="cursor:pointer;" data-product-control="' . $link . '"', $imageHtml);
                            }
                            return $imageHtml;
                        },
                        $productItemHtml
                    );
                    $productItemHtml = preg_replace_callback(
                        '/<!--product_button-->([\s\S]+?)<!--\/product_button-->/',
                        function ($buttonMatch) {
                            $button_html = $buttonMatch[1];
                            $controlOptions = [];
                            if (preg_match('/<\!--options_json--><\!--([\s\S]+?)--><\!--\/options_json-->/', $button_html, $matches)) {
                                $controlOptions = json_decode($matches[1], true);
                                $button_html = str_replace($matches[0], '', $button_html);
                            }
                            $goToProduct = false;
                            if (isset($controlOptions['clickType']) && $controlOptions['clickType'] === 'go-to-page') {
                                $goToProduct = true;
                            }
                            $buttonText = sprintf(__('%s', 'woocommerce'), Nicepage::$_productData['add_to_cart_text']);
                            if (Nicepage::$_typeControl === "products") {
                                $button_html = preg_replace('/href=[\'|"][\s\S]+?[\'|"]/', 'href="%s"', $button_html);
                                $button_html = preg_replace('/<!--product_button_content-->([\s\S]+?)<!--\/product_button_content-->/', '%s', $button_html);
                            }
                            if ($goToProduct) {
                                $button_html = sprintf(
                                    $button_html,
                                    get_permalink(Nicepage::$_postId),
                                    $buttonText
                                );
                            } else {
                                if (Nicepage::$_typeControl === "products") {
                                    $button_html = preg_replace('/class=[\'|"]([\s\S]+?)[\'|"]/', 'class="$1 %s"', $button_html);
                                    $button_html = str_replace('href', 'data-quantity="1" data-product_id="%s" data-product_sku="%s" href', $button_html);
                                }
                                $button_html = NpDataProduct::getProductButtonHtml($button_html, Nicepage::$_product, Nicepage::$_typeControl, $controlOptions);
                                if (Nicepage::$_typeControl === "product" && Nicepage::$_productData['type'] !== "variable") {
                                    global $product;
                                    $product = Nicepage::$_productData['product'];
                                    ob_start();
                                    woocommerce_template_single_add_to_cart();
                                    $form = ob_get_clean();
                                    return $form = preg_replace('/(<form[\s\S]*?>)([\s\S]+?)(<\/form>)/', '$1' . $button_html . '<div style="display:none;">$2</div>' . '$3', $form);
                                }
                            }
                            return $button_html;
                        },
                        $productItemHtml
                    );
                    $productItemHtml = preg_replace_callback(
                        '/<!--product_price-->([\s\S]+?)<!--\/product_price-->/',
                        function ($priceHtml) {
                            $priceHtml = $priceHtml[1];
                            $price = Nicepage::$_productData['price'];
                            $price_old = Nicepage::$_productData['price_old'];
                            if ($price_old == $price) {
                                $price_old = '';
                            }
                            $priceHtml = preg_replace('/<!--product_old_price_content-->([\s\S]*?)<!--\/product_old_price_content-->/', $price_old, $priceHtml);
                            return preg_replace('/<!--product_regular_price_content-->([\s\S]+?)<!--\/product_regular_price_content-->/', $price, $priceHtml);
                        },
                        $productItemHtml
                    );
                    $productItemHtml = preg_replace_callback(
                        '/<!--product_gallery-->([\s\S]+?)<!--\/product_gallery-->/',
                        function ($galleryMatch) {
                            $galleryHtml = $galleryMatch[1];
                            $galleryData = array();
                            $attachment_ids = Nicepage::$_productData['gallery_images_ids'];
                            foreach ($attachment_ids as $attachment_id) {
                                array_push($galleryData, wp_get_attachment_url($attachment_id));
                            }

                            if (count($galleryData) < 1) {
                                return '';
                            }

                            $galleryItemRe = '/<\!--product_gallery_item-->([\s\S]+?)<\!--\/product_gallery_item-->/';
                            preg_match($galleryItemRe, $galleryHtml, $galleryItemMatch);
                            $galleryItemHtml = str_replace('u-active', '', $galleryItemMatch[1]);

                            $galleryThumbnailRe = '/<\!--product_gallery_thumbnail-->([\s\S]+?)<\!--\/product_gallery_thumbnail-->/';
                            $galleryThumbnailHtml = '';
                            if (preg_match($galleryThumbnailRe, $galleryHtml, $galleryThumbnailMatch)) {
                                $galleryThumbnailHtml = $galleryThumbnailMatch[1];
                            }

                            $newGalleryItemListHtml = '';
                            $newThumbnailListHtml = '';
                            foreach ($galleryData as $key => $img) {
                                $newGalleryItemHtml = $key == 0 ? str_replace('u-gallery-item', 'u-gallery-item u-active', $galleryItemHtml) : $galleryItemHtml;
                                $newGalleryItemListHtml .= preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $img . '$3', $newGalleryItemHtml);
                                if ($galleryThumbnailHtml) {
                                    $newThumbnailHtml = preg_replace('/data-u-slide-to=([\'"])([\s\S]+?)([\'"])/', 'data-u-slide-to="' . $key . '"', $galleryThumbnailHtml);
                                    $newThumbnailListHtml .= preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $img . '$3', $newThumbnailHtml);
                                }
                            }

                            $galleryParts = preg_split($galleryItemRe, $galleryHtml, -1, PREG_SPLIT_NO_EMPTY);
                            $newGalleryHtml = $galleryParts[0] . $newGalleryItemListHtml . $galleryParts[1];

                            $newGalleryParts = preg_split($galleryThumbnailRe, $newGalleryHtml, -1, PREG_SPLIT_NO_EMPTY);
                            return $newGalleryParts[0] . $newThumbnailListHtml . $newGalleryParts[1];
                        },
                        $productItemHtml
                    );

                    $productItemHtml = preg_replace_callback(
                        '/<!--product_variations-->([\s\S]+?)<!--\/product_variations-->/',
                        function ($product_variations) {
                            $productVariationsHtml = $product_variations[1];
                            $productVariationsHtml = str_replace('u-product-variations ', 'u-product-variations variations ', $productVariationsHtml);
                            preg_match('/<!--product_variation-->([\s\S]+?)<!--\/product_variation-->/', $productVariationsHtml, $productVariations);
                            $firstVariationHtml = $productVariations[0];
                            $productVariationsHtml = preg_replace('/<!--product_variation-->([\s\S]+)<!--\/product_variation-->/', $firstVariationHtml, $productVariationsHtml);
                            $newVariationHtml = '';
                            $productAttributes = Nicepage::$_productData['attributes'];
                            $product_type = Nicepage::$_productData['type'];
                            if ($product_type == 'variable') {
                                $variation_attributes = Nicepage::$_productData['variations_attributes'];
                                if (count($variation_attributes) > 0) {
                                    foreach ($variation_attributes as $name => $variation_attribute) {
                                        $doubleVariationHtml = $firstVariationHtml;
                                        $optionsHtml = '<option value="">' . __('Choose an option', 'woocommerce') . '</option>';
                                        $productAttribute = $productAttributes[strtolower($name)] ? $productAttributes[strtolower($name)] : $productAttributes[wc_attribute_taxonomy_slug($name)];
                                        $variation_title = $productAttribute['name'];
                                        $variation_options = $productAttribute['options'];
                                        $select_id = strtolower($variation_title);
                                        if (isset($productAttribute['id']) && $productAttribute['id'] > 0) {
                                            $attribute = NpDataProduct::getProductAttribute($productAttribute['id']);
                                            $variation_options = $productAttribute->get_terms();
                                            $variation_title = NpDataProduct::getProductVariationTitle($attribute, $productAttribute);
                                        }
                                        $doubleVariationHtml = preg_replace('/<!--product_variation_label_content-->([\s\S]*?)<!--\/product_variation_label_content-->/', $variation_title, $doubleVariationHtml);
                                        $doubleVariationHtml = preg_replace('/for=[\'"][\s\S]+?[\'"]/', 'for="' . $select_id . '"', $doubleVariationHtml);
                                        $doubleVariationHtml = preg_replace('/(select[\s\S]+?id=[\'"])([\s\S]+?)([\'"])/', '$1' . $select_id . '$3' . ' name="attribute_' . $select_id . '" data-attribute_name="attribute_' . $select_id . '" data-show_option_none="yes"', $doubleVariationHtml);
                                        preg_match('/<!--product_variation_option-->([\s\S]+?)<!--\/product_variation_option-->/', $doubleVariationHtml, $productOptions);
                                        $firstOptionHtml = $productOptions[0];
                                        if (is_array($variation_options)) {
                                            foreach ($variation_options as $variation_option) {
                                                $variation_option_title = NpDataProduct::getProductVariationOptionTitle($variation_option);
                                                $doubleOptionHtml = $firstOptionHtml;
                                                $doubleOptionHtml = preg_replace('/value=[\'"][\s\S]+?[\'"]/', 'value="' . $variation_option_title . '"', $doubleOptionHtml);
                                                $doubleOptionHtml = preg_replace('/<!--product_variation_option_content-->([\s\S]+?)<!--\/product_variation_option_content-->/', $variation_option_title, $doubleOptionHtml);
                                                $optionsHtml .= $doubleOptionHtml;
                                            }
                                        }
                                        $doubleVariationHtml = preg_replace('/<!--product_variation_option-->([\s\S]+)<!--\/product_variation_option-->/', $optionsHtml, $doubleVariationHtml);
                                        $newVariationHtml .= preg_replace('/<!--product_variation_select_content-->([\s\S]*?)<!--\/product_variation_select_content-->/', $optionsHtml, $doubleVariationHtml);
                                        Nicepage::$_productVariationId++;
                                    }
                                }
                                $productVariationsHtml = preg_replace('/<!--product_variation-->([\s\S]*?)<!--\/product_variation-->/', $newVariationHtml, $productVariationsHtml);
                                global $product;
                                $product = Nicepage::$_productData['product'];
                                ob_start();
                                woocommerce_template_single_add_to_cart();
                                $form = ob_get_clean();
                                $add_to_cart = '<div class="single_variation_wrap-np" style="display: none">
			<div class="woocommerce-variation single_variation" style="display: none;"></div>
			<div class="woocommerce-variation-add-to-cart variations_button woocommerce-variation-add-to-cart-enabled">
	
		<div class="quantity">
		<input type="number" class="input-text qty text" step="1" min="1" max="" name="quantity" value="1" size="4" placeholder="" inputmode="numeric">
			</div>
			<button type="submit" class="np-submit single_add_to_cart_button button alt disabled wc-variation-selection-needed">Add to cart</button>
	<input type="hidden" name="add-to-cart" value="'. Nicepage::$_postId .'">
	<input type="hidden" name="product_id" value="' . Nicepage::$_postId .'">
	<input type="hidden" name="variation_id" class="variation_id" value="0">
</div>
		</div>';
                                $productVariationsHtml = str_replace('u-form-select-wrapper', 'u-form-select-wrapper value', $productVariationsHtml);
                                if (Nicepage::$_productData['product']->is_in_stock() && Nicepage::$_productData['product']->is_purchasable()) {
                                    return $form = preg_replace('/(<form[\s\S]*?>)([\s\S]+?)(<\/form>)/', '$1' . $productVariationsHtml . $add_to_cart . '$3', $form);
                                } else {
                                    return $productVariationsHtml = '<p class="stock out-of-stock">' . esc_html(apply_filters('woocommerce_out_of_stock_message', __('This product is currently out of stock and unavailable.', 'woocommerce'))) . '</p>';
                                }
                            } else {
                                $productVariationsHtml = '';
                            }
                            return $productVariationsHtml;
                        },
                        $productItemHtml
                    );

                    $productItemHtml = preg_replace_callback(
                        '/<!--product_tabs-->([\s\S]+?)<!--\/product_tabs-->/',
                        function ($product_tabs) {
                            $productTabsHtml = $product_tabs[1];
                            $productTabsHtml = preg_replace_callback(
                                '/<!--product_tabitem-->([\s\S]+?)<!--\/product_tabitem-->/',
                                function ($productTabsHtml) {
                                    $productTabsHtml = $productTabsHtml[1];
                                    if (isset(Nicepage::$_productData['tabs'][self::$_tabItemIndex])) {
                                        $title = sprintf(__('%s', 'woocommerce'), Nicepage::$_productData['tabs'][self::$_tabItemIndex]['title']);
                                        $productTabsHtml = preg_replace('/<!--product_tabitem_title-->([\s\S]*)<!--\/product_tabitem_title-->/', $title, $productTabsHtml);
                                    } else {
                                        return '';
                                    }
                                    self::$_tabItemIndex++;
                                    return $productTabsHtml;
                                },
                                $productTabsHtml
                            );
                            $productTabsHtml = preg_replace_callback(
                                '/<!--product_tabpane-->([\s\S]+?)<!--\/product_tabpane-->/',
                                function ($productTabsHtml) {
                                    $productTabsHtml = $productTabsHtml[1];
                                    if (isset(Nicepage::$_productData['tabs'][self::$_tabContentIndex])) {
                                        $productTabsHtml = preg_replace('/<!--product_tabpane_content-->([\s\S]*)<!--\/product_tabpane_content-->/', Nicepage::$_productData['tabs'][self::$_tabContentIndex]['content'], $productTabsHtml);
                                    } else {
                                        return '';
                                    }
                                    self::$_tabContentIndex++;
                                    return $productTabsHtml;
                                },
                                $productTabsHtml
                            );
                            return $productTabsHtml;
                        },
                        $productItemHtml
                    );

                    $productItemHtml = preg_replace_callback(
                        '/<!--product_quantity-->([\s\S]+?)<!--\/product_quantity-->/',
                        function ($quantityHtml) {
                            $quantityHtml = $quantityHtml[1];
                            $quantityHtml = preg_replace('/<!--product_quantity_label_content-->([\s\S]*?)<!--\/product_quantity_label_content-->/', esc_html__('Quantity', 'woocommerce'), $quantityHtml);
                            $quantityHtml = preg_replace_callback(
                                '/<\!--product_quantity_input-->([\s\S]+?)<\!--\/product_quantity_input-->/',
                                function ($quantityHtml) {
                                    $quantityHtml = $quantityHtml[1];
                                    preg_match('/<input[\s\S]+?class=[\'"]([\s\S]+?)[\'"]/', $quantityHtml, $quantityClasses);
                                    $max = Nicepage::$_productData['product']->get_max_purchase_quantity();
                                    $quantityHtml = '<input 
	    class="' . $quantityClasses[1] . '" 
	    type="text" 
	    value="1" 
	    step="' . esc_attr(apply_filters('woocommerce_quantity_input_step', '1', Nicepage::$_productData['product'])) . '" 
	    min="' . esc_attr(Nicepage::$_productData['product']->get_min_purchase_quantity()) . '" 
	    max="' . esc_attr(0 < $max ? $max : '') . '"
	    title="' . esc_attr_x('Qty', 'Product quantity input tooltip', 'woocommerce') . '" 
	    size="4" 
	    pattern="[0-9]+">';
                                    return $quantityHtml;
                                },
                                $quantityHtml
                            );
                            return $quantityHtml;
                        },
                        $productItemHtml
                    );
                }
                return $productItemHtml;
            },
            $content
        );
    }
    /**
     * Process cart for WooCommerce
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _processCartControl($content) {
        $content = preg_replace_callback(
            '/<\!--shopping_cart-->([\s\S]+?)<\!--\/shopping_cart-->/',
            function ($shoppingCartMatch) {
                $shoppingCartHtml = $shoppingCartMatch[1];

                if (!isset(WC()->cart)) {
                    return $shoppingCartHtml;
                }

                $shoppingCartHtml = preg_replace('/(\s+href=[\'"])([\s\S]+?)([\'"])/', '$1' . wc_get_cart_url() . '$3', $shoppingCartHtml);
                $shoppingCartHtml = preg_replace_callback(
                    '/<\!--shopping_cart_count-->([\s\S]+?)<\!--\/shopping_cart_count-->/',
                    function () {
                        $count = WC()->cart->get_cart_contents_count();
                        return isset($count) ? $count : 0;
                    },
                    $shoppingCartHtml
                );
                $script = <<<SCRIPT
<script type="text/javascript">
        if (window.sessionStorage) {
            window.sessionStorage.setItem('wc_cart_created', '');
        }
    </script>
SCRIPT;

                $cartParentOpen = '<div>';
                if (preg_match('/<a[\s\S]+?class=[\'"]([\s\S]+?)[\'"]/', $shoppingCartHtml, $matches)) {
                    $cartParentOpen = '<div class="' . $matches[1] . '">';
                    $shoppingCartHtml = str_replace($matches[1], '', $shoppingCartHtml);
                }
                $cart_open = '<div class="widget_shopping_cart_content">';
                $cart_close = '</div>';
                return $script . $cartParentOpen . $cart_open . $shoppingCartHtml . $cart_close . '</div>';
            },
            $content
        );
        return $content;
    }

    /**
     * Process forms
     *
     * @param string $content
     * @param string $templateName
     *
     * @return string
     */
    private static function _processForms($content, $templateName = '') {
        global $post;
        self::$_formIdx = 0;
        self::$_formsSources = NpForms::getPageForms($post->ID, $templateName);
        return preg_replace_callback(NpForms::$formRe, 'Nicepage::_processForm', $content);
    }

    /**
     * Convert HTML-placeholders into shortcodes
     *
     * @param string $content
     *
     * @return string
     */
    private static function _prepareShortcodes($content) {
        $content = preg_replace('#<!--(\/?)(position|block|block_header|block_header_content|block_content_content)-->#', '[$1np_$2]', $content);
        return $content;
    }

    /**
     * Process form
     * Callback for preg_replace_callback
     *
     * @param array $match
     *
     * @return string
     */
    private static function _processForm($match) {
        $form_html = $match[0];
        $form_id = isset(self::$_formsSources[self::$_formIdx]['id']) ? self::$_formsSources[self::$_formIdx]['id'] : 0;

        $return = NpForms::getHtml($form_id, $form_html);
        if (self::$_formIdx === 0) {
            $return = NpForms::getScriptsAndStyles() . "\n" . $return;
        }
        self::$_formIdx++;
        return $return;
    }

    /**
     * Process product add-to-cart links
     *
     * @param string $html
     *
     * @return string
     */
    private static function _productLinksFilter($html) {
        if (!function_exists('wc_get_product')) {
            return $html;
        }

        return preg_replace_callback('#<a(.*?)</a>#', 'Nicepage::_productLinksFilterCallback', $html);
    }

    /**
     * _productLinksFilter preg_replace_callback callback
     *
     * @param array $match
     *
     * @return string
     */
    private static function _productLinksFilterCallback($match) {
        if (preg_match('#href="\#product-(\d+)#', $match[1], $m)) {
            $product_id = (int) $m[1];

            if (preg_match('#class="([^"]*)"#', $match[1], $m)) {
                $class = $m[1];
            } else {
                $class = '';
            }
            global $product;
            $prev_product = $product;
            $product = NpDataProduct::getProduct($product_id);
            ob_start();
            woocommerce_template_loop_add_to_cart();
            $product = $prev_product;
            $add_to_cart_html = ob_get_clean();
            $add_to_cart_html = str_replace('class="button', 'class="' . $class, $add_to_cart_html);
            return $add_to_cart_html;
        }
        return $match[0];
    }

    /**
     * Filter on template_include
     * Switch to 'html' or 'html-header-footer' template
     *
     * @param string $template_path
     *
     * @return string
     */
    public static function templateFilter($template_path) {
        global $post;
        if ($post && is_singular() && np_data_provider($post->ID)->isNicepage()) {
            $np_template = NpMetaOptions::get($post->ID, 'np_template');
            $np_template = apply_filters('nicepage_template', $np_template, $post->ID, $template_path);
            if ($np_template) {
                $template_path = dirname(__FILE__) . "/../templates/$np_template.php";
            }
        }
        return $template_path;
    }

    /**
     * Check is it query for getting dummy page
     * Dummy page - it's a page without Nicepage styles
     * used for getting real typography properties from theme
     *
     * @return bool
     */
    public static function isHtmlQuery() {
        return !empty($_GET['np_html']);
    }

    /**
     * Add cookies confirm code
     */
    public static function addCookiesConfirmCode()
    {
        global $post;
        $post_id = !isset($post->ID)? get_the_ID() : $post->ID;
        $sections_html = np_data_provider($post_id)->getPagePublishHtml();
        $cookiesConsent = NpMeta::get('cookiesConsent') ? json_decode(NpMeta::get('cookiesConsent'), true) : '';
        if ($cookiesConsent && (!$cookiesConsent['hideCookies'] || $cookiesConsent['hideCookies'] === 'false') && $sections_html && !self::isNpTheme()) {
            echo $cookiesConsent['cookieConfirmCode'];
        }
    }

    /**
     * Action on wp_head
     */
    public static function addHeadStyles() {
        if (self::isHtmlQuery() || !is_singular()) {
            return;
        }

        $post_id = get_the_ID();
        $data_provider = np_data_provider($post_id);
        $siteSettings = $data_provider->getSiteSettings();
        echo $data_provider->getPageFonts();

        $styles = $data_provider->getPageHead();
        if (self::isAutoResponsive($post_id)) {
            $styles = preg_replace('#\/\*RESPONSIVE_MEDIA\*\/([\s\S]*?)\/\*\/RESPONSIVE_MEDIA\*\/#', '', $styles);
        } else {
            $styles = preg_replace('#\/\*RESPONSIVE_CLASS\*\/([\s\S]*?)\/\*\/RESPONSIVE_CLASS\*\/#', '', $styles);
        }

        if (self::isNpTheme()) {
            echo "<style>\n$styles</style>\n";
        } else {
            global $post;
            $template_page = NpMetaOptions::get($post->ID, 'np_template');
            if ($template_page != "html") {
                echo  "<style>\n".preg_replace_callback('/([^{}]+)\{[^{}]+?\}/', 'self::addContainerForConflictStyles', $styles)."</style>\n";
            } else {
                echo "<style>\n$styles</style>\n";
            }
        }

        $description = $data_provider->getPageDescription();
        if (isset($siteSettings->description) && $siteSettings->description && strpos($description, $siteSettings->description) === false) {
            if ($description !== '') {
                $description = $siteSettings->description . ', ' . $description;
            } else {
                $description = $siteSettings->description;
            }

        }
        if ($description) {
            echo "<meta name=\"description\" content=\"$description\">\n";
        }

        $keywords = $data_provider->getPageKeywords();
        if (isset($siteSettings->keywords) && $siteSettings->keywords && strpos($keywords, $siteSettings->keywords) === false) {
            if ($keywords !== '') {
                $keywords = $siteSettings->keywords . ', ' . $keywords;
            } else {
                $keywords = $siteSettings->keywords;
            }
        }
        if ($keywords) {
            echo "<meta name=\"keywords\" content=\"$keywords\">\n";
        }

        $meta_tags = $data_provider->getPageMetaTags();
        if ($meta_tags) {
            echo $meta_tags . "\n";
        }

        $meta_generator = $data_provider->getPageMetaGenerator();
        if (!isset($GLOBALS['meta_generator']) && $meta_generator) {
            echo '<meta name="generator" content="' . $meta_generator . '" />' . "\n";
        }

        $customHeadHtml = $data_provider->getPageCustomHeadHtml();
        if ($customHeadHtml) {
            echo $customHeadHtml . "\n";
        }
    }

    /**
     * Action on wp_head
     */
    public static function addHeadStyles2() {
        if (self::isHtmlQuery()) {
            return;
        }

        $post_id = get_the_ID();
        $data_provider = np_data_provider($post_id);

        if (is_singular() && $data_provider->isNicepage()) {
            $site_style_css = $data_provider->getStyleCss();
            if ($site_style_css) {
                if (self::isNpTheme()) {
                    $site_style_css = preg_replace('#<style.*?(typography|font-scheme|color-scheme)="Theme [\s\S]*?<\/style>#', '', $site_style_css);
                } else {
                    global $post;
                    $template_page = NpMetaOptions::get($post->ID, 'np_template');
                    if ($template_page != "html") {
                        $site_style_css = preg_replace_callback('/([^{}]+)\{[^{}]+?\}/', 'self::addContainerForConflictStyles', $site_style_css);
                    }
                }
                echo "<style>$site_style_css</style>\n";
            }
        }
    }
    /**
     * Add container for conflict styles
     *
     * @param array $match
     *
     * @return string
     */
    public static function addContainerForConflictStyles($match) {
        $selectors = $match[1];
        $parts = explode(',', $selectors);
        $newSelectors = implode(
            ',',
            array_map(
                function ($part) {
                    if (!preg_match('/html|body|sheet|keyframes/', $part)) {
                        return ' .nicepage-container ' . $part;
                    } else {
                        return $part;
                    }
                },
                $parts
            )
        );
        return str_replace($selectors, $newSelectors, $match[0]);
    }

    /**
     * Add viewport meta tag
     */
    public static function addViewportMeta() {
        if (self::isHtmlQuery()) {
            return;
        }
        echo <<<SCRIPT
<script>
    if (!document.querySelector("meta[name='viewport")) {
        var vpMeta = document.createElement('meta');
        vpMeta.name = "viewport";
        vpMeta.content = "width=device-width, initial-scale=1.0";
        document.getElementsByTagName('head')[0].appendChild(vpMeta);
    }
</script>
SCRIPT;
    }

    /**
     * Add site meta tags
     */
    public static function addSiteMetaTags() {
        if (self::isHtmlQuery()) {
            return;
        }
        $post_id = get_the_ID();
        $data_provider = np_data_provider($post_id);
        $siteSettings = $data_provider->getSiteSettings();
        if (isset($siteSettings->metaTags) && $siteSettings->metaTags) {
            echo $siteSettings->metaTags;
        }
    }

    /**
     * Add site custom css
     */
    public static function addSiteCustomCss() {
        if (self::isHtmlQuery()) {
            return;
        }
        $post_id = get_the_ID();
        $data_provider = np_data_provider($post_id);
        $siteSettings = $data_provider->getSiteSettings();
        if (isset($siteSettings->customCss) && $siteSettings->customCss) {
            echo '<style>' . $siteSettings->customCss . '</style>';
        }
    }

    /**
     * Add site custom html
     */
    public static function addSiteCustomHtml() {
        if (self::isHtmlQuery()) {
            return;
        }
        $post_id = get_the_ID();
        $data_provider = np_data_provider($post_id);
        $siteSettings = $data_provider->getSiteSettings();
        if (isset($siteSettings->headHtml) && $siteSettings->headHtml) {
            echo $siteSettings->headHtml;
        }
    }

    /**
     * Add site analytic
     */
    public static function addSiteAnalytic() {
        if (self::isHtmlQuery()) {
            return;
        }
        $post_id = get_the_ID();
        $data_provider = np_data_provider($post_id);
        $siteSettings = $data_provider->getSiteSettings();
        if (isset($GLOBALS['googleAnalyticsMarker']) && !$GLOBALS['googleAnalyticsMarker'] && isset($siteSettings->analyticsCode)) {
            echo $siteSettings->analyticsCode;
        }
    }

    /**
     * Check if need to enable auto-responsive
     *
     * @param string|int $page_id
     *
     * @return bool
     */
    public static function isAutoResponsive($page_id) {
        if (self::isNpTheme()) {
            return false;
        }
        if (NpMetaOptions::get($page_id, 'np_template') === 'html') {
            return false;
        }
        return !!NpSettings::getOption('np_auto_responsive');
    }

    /**
     * Filter on single_post_title
     *
     * @param string  $title
     * @param WP_Post $post
     *
     * @return string
     */
    public static function singlePostTitleFilter($title, $post) {
        $post_id = $post->ID;
        $custom_title = np_data_provider($post_id)->getPageTitleInBrowser();
        if ($custom_title) {
            $title = $custom_title;
        }
        return $title;
    }

    /**
     * Action on wp_enqueue_scripts
     */
    public static function addScriptsAndStylesAction() {
        global $post;
        $post_id = !isset($post->ID)? get_the_ID() : $post->ID;
        if (self::isHtmlQuery() || !np_data_provider($post_id)->isNicepage()) {
            return;
        }

        if (NpSettings::getOption('np_include_jquery')) {
            wp_register_script("nicepage-jquery", APP_PLUGIN_URL . 'assets/js/jquery.js', array(), APP_PLUGIN_VERSION);
            wp_enqueue_script("nicepage-jquery");

            wp_register_script("nicepage-script", APP_PLUGIN_URL . 'assets/js/nicepage.js', array('nicepage-jquery'), APP_PLUGIN_VERSION);
        } else {
            wp_register_script("nicepage-script", APP_PLUGIN_URL . 'assets/js/nicepage.js', array('jquery'), APP_PLUGIN_VERSION);
        }

        if (self::isNpTheme()) {
            wp_register_style("nicepage-style", APP_PLUGIN_URL . 'assets/css/nicepage.css', array(), APP_PLUGIN_VERSION);
            wp_enqueue_style("nicepage-style");
        } else {
            $template_page = NpMetaOptions::get($post_id, 'np_template');
            if ($template_page == "html") {
                wp_register_style("nicepage-style", APP_PLUGIN_URL . 'assets/css/nicepage.css', array(), APP_PLUGIN_VERSION);
                wp_enqueue_style("nicepage-style");
            } else {
                wp_register_style("nicepage-style", APP_PLUGIN_URL . 'assets/css/page-styles.css', array(), APP_PLUGIN_VERSION);
                wp_enqueue_style("nicepage-style");
            }
            //if our theme this scripts not need
            wp_enqueue_script("nicepage-script");
        }

        if (is_singular()) {
            if (self::isAutoResponsive($post_id)) {
                wp_register_style("nicepage-responsive", APP_PLUGIN_URL . 'assets/css/responsive.css', APP_PLUGIN_VERSION);
                wp_enqueue_style("nicepage-responsive");
            } else {
                wp_register_style("nicepage-media", APP_PLUGIN_URL . 'assets/css/media.css', APP_PLUGIN_VERSION);
                wp_enqueue_style("nicepage-media");
            }
        }
    }

    public static $responsiveModes = array('XS', 'SM', 'MD', 'LG', 'XL');
    public static $responsiveBorders = array(
        'XL' => array(
            'CLASS' => 'u-xl',
            'MAX' => 1000000,
        ),
        'LG' => array(
            'CLASS' => 'u-lg',
            'MAX' => 1199,
        ),
        'MD' => array(
            'CLASS' => 'u-md',
            'MAX' => 991,
        ),
        'SM' => array(
            'CLASS' => 'u-sm',
            'MAX' => 767,
        ),
        'XS' => array(
            'CLASS' => 'u-xs',
            'MAX' => 575,
        ),
    );

    /**
     * Get initial responsive mode using $GLOBALS['content_width']
     *
     * @param string|int $post_id
     *
     * @return mixed|string
     */
    private static function _getInitialResponsiveMode($post_id) {
        if (!self::isAutoResponsive($post_id)) {
            return 'XL';
        }
        if (NpMetaOptions::get($post_id, 'np_template')) {
            return 'XL';
        }

        global $content_width;
        if (!isset($content_width) || !$content_width) {
            return 'XL';
        }

        $width = (int) $content_width;

        foreach (self::$responsiveModes as $mode) {
            if ($width <= self::$responsiveBorders[$mode]['MAX']) {
                return $mode;
            }
        }
        return 'XL';
    }

    /**
     * Auto-responsive script
     *
     * @param int|string $post_id
     *
     * @return string
     */
    private static function _getAutoResponsiveScript($post_id) {
        ob_start();
        ?>
        <script>
            (function ($) {
                var ResponsiveCms = window.ResponsiveCms;
                if (!ResponsiveCms) {
                    return;
                }
                ResponsiveCms.contentDom = $('script:last').parent();
                ResponsiveCms.prevMode = <?php echo wp_json_encode(self::_getInitialResponsiveMode($post_id)); ?>;

                if (typeof ResponsiveCms.recalcClasses === 'function') {
                    ResponsiveCms.recalcClasses();
                }
            })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Action on init
     */
    public static function initAction() {
        if (self::isNpTheme()) {
            add_filter('body_class', 'Nicepage::bodyClassFilter');
            add_filter('add_body_style_attribute', 'Nicepage::bodyStyleFilter');
        }
    }

    /**
     * Check is it Nicepage theme
     *
     * @return bool
     */
    public static function isNpTheme() {
        if (self::$_themeSettings === null) {
            self::$_themeSettings = apply_filters('np_theme_settings', array());
        }
        return !!self::$_themeSettings;
    }
    private static $_themeSettings = null;

    /**
     * Initialize svg upload with sizes
     */
    public static function svgUploaderInitialization() {
        new NpSvgUploader();
    }

    public static $controlName = '';
    /**
     * Process all custom controls on the header
     *
     * @param string $content content
     *
     * @return mixed
     */
    public static function processControls($content) {
        $controls = array('headline', 'logo', 'menu', 'search', 'position', 'headerImage', 'slogan', 'widget', 'shortCode', 'login');
        foreach ($controls as $value) {
            self::$controlName = $value;
            $content =  preg_replace_callback(
                '/<\!--np_' . $value . '--><!--np_json-->([\s\S]+?)<\!--\/np_json-->([\s\S]*?)<\!--\/np_' . $value . '-->/',
                function ($matches) {
                    $controlProps = json_decode(trim($matches[1]), true);
                    $controlTemplate = $matches[2];
                    ob_start();
                    include APP_PLUGIN_PATH . '/includes/controls/'. Nicepage::$controlName . '/' . Nicepage::$controlName . '.php';
                    return ob_get_clean();
                },
                $content
            );
        }
        return $content;
    }

    /**
     * Add recaptcha script when not contact 7 plugin
     */
    public static function enableRecapcha() {
        if (self::isHtmlQuery()) {
            return;
        }
        $site_settings = json_decode(NpMeta::get('site_settings'));
        if (!class_exists('WPCF7') && isset($site_settings->captchaSiteKey) && isset($site_settings->captchaSecretKey) && isset($site_settings->captchaScript) && $site_settings->captchaSiteKey !== "" && $site_settings->captchaSecretKey !== "") {
            echo $site_settings->captchaScript;
        }
    }

    /**
     * Filter <title> on the all pages
     *
     * @return mixed|string
     */
    public static function frontEndTitleFilter() {
        $title = '';
        $id = get_the_ID();
        if ($id) {
            $seoTitle = get_post_meta($id, 'page_title', true);
            if ($seoTitle && $seoTitle !== '') {
                $title = $seoTitle;
            }
        }
        return $title;
    }

    /**
     *  Remove meta generator wordpress
     */
    public static function removeCmsMetaGenerator() {
        $post_id = get_the_ID();
        $data_provider = np_data_provider($post_id);
        $meta_generator = $data_provider->getPageMetaGenerator();
        if ($meta_generator) {
            remove_action('wp_head', 'wp_generator');
        }
    }

    /**
     * Filter canonical url
     *
     * @param string  $canonical_url
     * @param WP_Post $post
     *
     * @return string $canonical_url
     */
    public static function filter_canonical($canonical_url, $post){
        $data_provider = np_data_provider($post->ID);
        $canonical = $data_provider->getPageCanonical();
        $canonical_url = $canonical ? $canonical : $canonical_url;
        return $canonical_url;
    }

    /**
     * Output woo cart
     *
     * @param string $template      Template
     * @param string $template_name Template name
     * @param string $template_path Template path
     *
     * @return string
     */
    public static function miniCart($template, $template_name = '', $template_path = '') {
        $basename = basename($template);

        if ($basename !== 'mini-cart.php') {
            return $template;
        }

        $referer = wp_get_raw_referer();
        if ($referer && ($pageId = url_to_postid($referer)) === 0) {
            return $template;
        }

        if (NpMetaOptions::get($pageId, 'np_template') === 'html') {
            $template = trailingslashit(plugin_dir_path(__FILE__)) . 'controls/cart/mini-cart.php';
        }

        return $template;
    }
}

add_filter('woocommerce_locate_template', 'Nicepage::miniCart');
add_action('init', 'Nicepage::initAction');
add_filter('the_content', 'Nicepage::theContentFilter');
add_filter('get_canonical_url', 'Nicepage::filter_canonical', 10, 2);
add_action('wp_enqueue_scripts', 'Nicepage::addScriptsAndStylesAction', 9); // add before theme styles
add_filter('pre_get_document_title', 'Nicepage::frontEndTitleFilter');
add_action('wp_head', 'Nicepage::removeCmsMetaGenerator', 0);
add_action('wp_head', 'Nicepage::addHeadStyles');
add_action('wp_head', 'Nicepage::addCookiesConfirmCode');
add_action('wp_head', 'Nicepage::addHeadStyles2', 1003);
add_action('wp_head', 'Nicepage::addViewportMeta', 1004);
add_action('wp_head', 'Nicepage::addSiteMetaTags', 1005);
add_action('wp_head', 'Nicepage::addSiteCustomCss', 1006);
add_action('wp_head', 'Nicepage::addSiteCustomHtml', 1007);
add_action('wp_head', 'Nicepage::addSiteAnalytic', 1008);
add_action('wp_head', 'Nicepage::enableRecapcha', 1009);
add_filter('template_include', 'Nicepage::templateFilter');
add_filter('single_post_title', 'Nicepage::singlePostTitleFilter', 9, 2);
add_action('admin_init', 'Nicepage::svgUploaderInitialization');
add_action('admin_init', 'NpImport::redirectToPluginWizard');
add_action(
    'in_admin_header', function () {
        $pagename = get_admin_page_title();
        if ($pagename !== APP_PLUGIN_WIZARD_NAME) {
            return;
        }
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        wp_enqueue_style('pwizard-style', APP_PLUGIN_URL . 'importer/assets/css/pwizard-admin-style.css', array(), '');
    }, 1000
);
add_action('wp_footer', 'Nicepage::wpFooterAction');
// For UP-6583 form temporary fix
if (function_exists('w123cf_widget_text_filter')) {
    add_filter('the_content', 'w123cf_widget_text_filter', 1005);
}

if (Nicepage::isHtmlQuery()) {
    show_admin_bar(false);
}