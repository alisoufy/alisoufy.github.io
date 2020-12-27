<?php
defined('ABSPATH') or die;

$post_id = get_the_ID();
$data_provider = np_data_provider($post_id);
$headerNp = $data_provider->getNpHeader();
$headerItem = json_decode($headerNp, true);
$publishHeader = $headerItem['php'];
if (preg_match('/<\!--shopping_cart-->([\s\S]+?)<\!--\/shopping_cart-->/', $publishHeader, $matches)) {
    $shoppingCartHtml = $matches[1];

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
    $cart_parent_open = '<div class="widget_shopping_cart_content">';
    $cart_parent_close = '</div>';
    if (preg_match('/<a[\s\S]+?class=[\'"]([\s\S]+?)[\'"]/', $shoppingCartHtml, $matches)) {
        $shoppingCartHtml = str_replace($matches[1], '', $shoppingCartHtml);
    }
    echo  $cart_parent_open . $shoppingCartHtml . $cart_parent_close;
}