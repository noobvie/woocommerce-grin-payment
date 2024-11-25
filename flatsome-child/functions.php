<?php
// Add custom Theme Functions here
// Modify the empty cart message
add_filter( 'wc_empty_cart_message', 'custom_empty_cart_message' );
function custom_empty_cart_message( $message ) {
    // Custom message for the empty cart page
    if ( is_cart() ) {
        $message = __( 'Your cart is currently empty. <br> If you have just ordered, please check your email box (including junk mail). Thanks.', 'your-text-domain' );
    }
    return $message;
}
