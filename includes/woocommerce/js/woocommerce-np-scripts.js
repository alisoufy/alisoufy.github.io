jQuery(document).ready(function($) {
    jQuery(document).on('click', '.u-product .u-btn.u-product-control', function(e) {
        e.preventDefault();
        jQuery(this).parents('.u-product').find('.single_add_to_cart_button').click();
    });
    function changePrice() {
        if (jQuery('.woocommerce-variation-price').length) {
            jQuery(this).parents('.u-product').find('.u-product-price:visible .woocommerce-Price-amount').html(jQuery(this).parents('.u-product').find('.woocommerce-variation-price .price ins .woocommerce-Price-amount').not(':visible').html());
        }
    };
    jQuery(document).on('change', '.u-product-variant select', changePrice);
    function changeQuantity() {
        if (jQuery('.u-product form.cart .quantity').length) {
            jQuery(this).parents('.u-product').find('form .quantity input.qty').val(jQuery(this).find('.u-input').val());
        }
    };
    jQuery(document).on('change', '.u-quantity-input', changeQuantity);
});