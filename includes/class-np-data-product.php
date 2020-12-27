<?php
defined('ABSPATH') or die;

class NpDataProduct {

    public static $product;

    /**
     * Get woocommerce product object
     *
     * @param int $product_id
     *
     * @return object $product
     */
    public static function getProduct($product_id) {
        if (function_exists('wc_get_product')) {
            return $product = wc_get_product($product_id);
        } else {
            return null;
        }
    }

    /**
     * Get full data product for np
     *
     * @param bool $editor
     *
     * @return array $product_data
     */
    public static function getProductData($editor = false) {
        $product_data = array(
            'product'               => self::$product,
            'type'                  => self::getProductType(),
            'title'                 => self::getProductTitle(),
            'desc'                  => self::getProductDesc(),
            'image_url'             => self::getProductImageUrl(),
            'price'                 => self::getProductPrice($editor),
            'price_old'             => self::getProductPriceOld($editor),
            'add_to_cart_text'      => self::getProductAddToCartText(),
            'attributes'            => self::getProductAttributes(),
            'variations_attributes' => self::getProductVariationAttributes(),
            'gallery_images_ids'    => self::getProductImagesIds(),
            'tabs'                  => self::getProductDefaultProductTabs(),
        );
        return $product_data;
    }

    /**
     * Get product type
     *
     * @return string $product_type
     */
    public static function getProductType() {
        return $product_type = self::$product->get_type();
    }

    /**
     * Get product title
     *
     * @return string $title
     */
    public static function getProductTitle() {
        return $title = self::$product->get_title();
    }

    /**
     * Get product description
     *
     * @return string $desc
     */
    public static function getProductDesc() {
        $product_id  = self::$product->get_id();
        return $desc = plugin_trim_long_str(NpAdminActions::getTheExcerpt($product_id), 150);
    }

    /**
     * Get product image url
     *
     * @return string $image_url
     */
    public static function getProductImageUrl() {
        $image_id  = self::$product->get_image_id();
        $image_url = wp_get_attachment_image_url($image_id, 'full');
        return $image_url;
    }

    /**
     * Get product price
     *
     * @param bool $editor
     *
     * @return int $price
     */
    public static function getProductPrice($editor = false) {
        $price = self::$product->get_price();
        if ($editor) {
            $price = (!$price || $price === '') ? 0 : $price;
        }
        if ($price !== '') {
            $price = wc_price($price);
        }
        return $price;
    }

    /**
     * Get product price old
     *
     * @param bool $editor
     *
     * @return int $price_old
     */
    public static function getProductPriceOld($editor = false) {
        $price_old = self::$product->get_regular_price();
        if ($editor) {
            $price_old = (!$price_old || $price_old === '') ? 0 : $price_old;
        }
        if ($price_old !== '') {
            $price_old = wc_price($price_old);;
        }
        return $price_old;
    }

    /**
     * Get product add to cart text
     *
     * @return string $add_to_cart_text
     */
    public static function getProductAddToCartText() {
        return $add_to_cart_text = self::$product->add_to_cart_text();
    }

    /**
     * Get product attributes as an entity for attributes
     * or ready-made values ​​for custom attributes created when editing a product
     *
     * @return array $productAttributes
     */
    public static function getProductAttributes() {
        return $productAttributes = self::$product->get_attributes();
    }

    /**
     * Get product variation attributes
     *
     * @return array $variation_attributes
     */
    public static function getProductVariationAttributes() {
        $product_type         = self::getProductType(self::$product);
        $variation_attributes = array();
        if ($product_type === 'variable') {
            $variation_attributes = self::$product->get_variation_attributes();
        }
        return $variation_attributes;
    }

    /**
     * Get product gallery images ids
     *
     * @return object $attachment_ids
     */
    public static function getProductImagesIds() {
        return $attachment_ids = self::$product->get_gallery_image_ids();
    }

    /**
     * Get product default tabs
     *
     * @return array $tabs
     */
    public static function getProductDefaultProductTabs() {
        $product_id = self::$product->get_id();
        global $post;
        $isNp = np_data_provider($post->ID)->isNicepage();
        $post_old = $post;
        $post = get_post($product_id);
        $post->isNp = $isNp;
        remove_filter('comments_template', array('WC_Template_Loader', 'comments_template_loader'));
        $parameters['description'] = array(
            'title'    => __('Description', 'woocommerce'),
            'priority' => 10
        );
        $parameters['reviews'] = array(
            'title'    => sprintf(__('Reviews (%d)', 'woocommerce'), self::$product->get_review_count()),
            'priority' => 30,
            'callback' => 'comments_template',
        );
        $tabs = array();
        foreach ($parameters as $key => $parameter) {
            if ($key == "description") {
                $heading = apply_filters('woocommerce_product_description_heading', __('Description', 'woocommerce'));
                $content = '<h2>' . esc_html($heading) . '</h2>' . self::$product->get_description();
            } else {
                global $product;
                $product = self::getProduct($product_id) === null ? $product : self::getProduct($product_id);
                global $withcomments;
                $withcomments = true;
                ob_start();
                comments_template();
                $content = ob_get_clean();
            }
            $tabs[] = array (
                'title'   => $parameter['title'],
                'content' => $content,
            );
        }
        $post = $post_old;
        return $tabs;
    }

    /**
     * Get product attribute by id
     *
     * @param int $attribute_id
     *
     * @return object $attribute
     */
    public static function getProductAttribute($attribute_id) {
        return $attribute = wc_get_attribute($attribute_id);
    }

    /**
     * Get button add to cart html
     *
     * @param string $button_html
     * @param object $product
     * @param string $type
     * @param array  $options
     *
     * @return string $button_html
     */
    public static function getProductButtonHtml($button_html, $product, $type, $options = array()) {
        $product_id  = $product->get_id();

        $button_class = implode(
            ' ',
            array_filter(
                array(
                    'button',
                    'product_type_' . $product->get_type(),
                    $product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button' : '',
                    $product->supports('ajax_add_to_cart') && $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart' : '',
                )
            )
        );

        if ($type === "product") {
            $button_class = '';
        }
        $button_text = sprintf(
            __('%s', 'woocommerce'),
            NpDataProduct::getProductAddToCartText($product)
        );
        if ($button_text && isset($options['content']) && $options['content']) {
            $button_text = $options['content'];
        }
        $button_html = apply_filters(
            'woocommerce_loop_add_to_cart_link',
            sprintf(
                $button_html,
                esc_attr($product_id),
                esc_attr($product->get_sku()),
                esc_url($product->add_to_cart_url()),
                $button_class,
                $button_text
            ),
            $product
        );
        return $button_html;
    }

    /**
     * Get product variation title
     *
     * @param object $attribute
     * @param object $productAttribute
     *
     * @return string $variation_title
     */
    public static function getProductVariationTitle($attribute, $productAttribute) {
        if (isset($attribute->name)) {
            $variation_title = $attribute->name;
        } else {
            $attr_object = $productAttribute->get_taxonomy_object();
            $variation_title = $attr_object->attribute_label ? $attr_object->attribute_label : $attr_object->attribute_name;
        }
        return $variation_title;
    }

    /**
     * Get product variation option title
     *
     * @param array|string $variation_option
     *
     * @return string $variation_option_title
     */
    public static function getProductVariationOptionTitle($variation_option) {
        if (is_string($variation_option)) {
            return $variation_option;
        }
        return $variation_option_title = $variation_option->name ? strtolower($variation_option->name) : '';
    }

}

/**
 * Add scripts and styles for woocommerce
 */
function add_to_cart_scripts() {
    global $post;
    if (np_data_provider($post->ID)->isNicepage() && class_exists('WooCommerce')) {
        wp_register_script('woocommerce-np-scripts', APP_PLUGIN_URL . 'includes/woocommerce/js/woocommerce-np-scripts.js', array('jquery'), time());
        wp_enqueue_script('woocommerce-np-scripts');
        wp_register_style("woocommerce-np-styles", APP_PLUGIN_URL . 'includes/woocommerce/css/woocommerce-np-styles.css', APP_PLUGIN_VERSION);
        wp_enqueue_style("woocommerce-np-styles");
    }
}

/**
 * Construct NpDataProduct object
 *
 * @param int  $product_id Product Id
 * @param bool $editor     Need to check editor or live site
 *
 * @return array NpDataProduct
 */
function np_data_product($product_id = 0, $editor = false)
{
    NpDataProduct::$product = NpDataProduct::getProduct($product_id);
    return NpDataProduct::$product ? NpDataProduct::getProductData($editor) : array();
}

/**
 * @param string $output
 * @return string $output
 */
function change_comments_template_path($output) {
    global $post;
    if ($post->isNp) {
        return APP_PLUGIN_PATH . 'includes/controls/product-tabs/reviews/template.php';
    } else {
        return $output;
    }
}

add_filter('comments_template', 'change_comments_template_path', 10, 1);

if (!function_exists('np_review_ratings_enabled')) {
    /**
     * @return bool
     */
    function np_review_ratings_enabled() {
        return 'yes' === get_option('woocommerce_enable_reviews') && 'yes' === get_option('woocommerce_enable_review_rating');
    }
}
if (!function_exists('np_review_ratings_required')) {
    /**
     * @return bool
     */
    function np_review_ratings_required() {
        return 'yes' === get_option('woocommerce_review_rating_required');
    }
}

add_action('wp_enqueue_scripts', 'add_to_cart_scripts', 1003);