<?php
/*
Plugin Name: WooCommerce Grin Payment
Description: Adds a Grin payment gateway to WooCommerce.
Version: 1.0
Author: noobvie
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Register Grin Payment Gateway.
add_filter('woocommerce_payment_gateways', 'add_grin_payment_gateway');

function add_grin_payment_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Grin';
    return $gateways;
}

// Initialize the payment gateway class.
add_action('plugins_loaded', 'init_grin_payment_gateway');

function init_grin_payment_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Grin extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'grin';
            $this->method_title       = 'Grin Payment';
            $this->method_description = 'Pay with Grin cryptocurrency.';
            $this->has_fields         = false;

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            $this->title        = $this->get_option('title');
            $this->description  = $this->get_option('description');
            $this->grin_address = $this->get_option('grin_address');

            // Save settings.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Display Grin total and address at checkout.
            add_action('woocommerce_review_order_after_order_total', array($this, 'display_grin_total_and_address'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'type'        => 'checkbox',
                    'label'       => 'Enable Grin Payment',
                    'default'     => 'yes'
                ),
                'grin_address' => array(
                    'title'       => 'Grin Address',
                    'type'        => 'text',
                    'description' => 'Enter your Grin cryptocurrency address here.',
                    'default'     => '',
                    'desc_tip'    => true
                )
            );
        }

        public function display_grin_total_and_address() {
            $grin_rate = fetch_grin_exchange_rate(); // Use global function.

            if ($grin_rate) {
                $cart_total = WC()->cart->get_total('raw');
                $grin_total = round($cart_total / $grin_rate, 4);

                echo '<tr class="order-total grin-total">
                        <th>Equivalent in Grin</th>
                        <td>' . sprintf('%.4f GRIN', $grin_total) . '</td>
                      </tr>';
            } else {
                echo '<tr class="order-total grin-total">
                        <th>Equivalent in Grin</th>
                        <td>Unable to fetch rate</td>
                      </tr>';
            }
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $grin_rate = fetch_grin_exchange_rate();

            if ($grin_rate) {
                $cart_total = $order->get_total();
                $grin_total = round($cart_total / $grin_rate, 4);

                // Save Grin total in order meta.
                $order->update_meta_data('grin_total', $grin_total);
            }

            $order->update_status('on-hold', 'Awaiting Grin payment.');
            $order->reduce_order_stock();
            WC()->cart->empty_cart();

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }
    }
}

// Global function for fetching Grin exchange rate.
if (!function_exists('fetch_grin_exchange_rate')) {
    function fetch_grin_exchange_rate() {
        $api_url = 'https://api.coingecko.com/api/v3/simple/price?ids=grin&vs_currencies=usd';
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['grin']['usd'] ?? null;
    }
}

// Add Grin total to the cart page.
add_action('woocommerce_cart_totals_after_order_total', 'display_grin_total_in_cart', 10);

function display_grin_total_in_cart() {
    $cart_total_usd = WC()->cart->get_cart_contents_total();
    $grin_exchange_rate = fetch_grin_exchange_rate(); // Use global function.

    if ($grin_exchange_rate) {
        $cart_total_grin = round($cart_total_usd / $grin_exchange_rate, 4);

        echo '<tr class="grin-total">
                <th>' . __('Equivalent in Grin', 'woocommerce') . ':</th>
                <td><strong>' . esc_html($cart_total_grin) . ' GRIN</strong></td>
              </tr>';
    } else {
        echo '<tr class="grin-total">
                <th>' . __('Equivalent in Grin', 'woocommerce') . ':</th>
                <td><strong>' . __('Exchange rate unavailable', 'woocommerce') . '</strong></td>
              </tr>';
    }
}

// Display Grin address below the payment description in the checkout.
add_action('woocommerce_review_order_after_payment', 'display_grin_address_below_description');

function display_grin_address_below_description() {
    // Get the Grin address dynamically from the payment gateway settings.
    $grin_address = get_option('woocommerce_grin_settings')['grin_address']; // Fetch the address from settings.

    if (!$grin_address) {
        return; // If no Grin address is set, do not display anything.
    }

    // Output the Grin address below the payment method description.
    echo '<div class="grin-payment-block">
            <p><strong>Send amount to Grin Address:</strong></p>
            <p class="grin-address-text">' . esc_html($grin_address) . '</p>
          </div>';
}

// Add Grin total to the order tracking page
add_action( 'woocommerce_order_details_after_order_table', 'add_grin_total_to_order_tracking', 10, 1 );

function add_grin_total_to_order_tracking( $order ) {
    // Get the Grin total from the order meta
    $grin_total = $order->get_meta( 'grin_total' );

    // Check if Grin total exists and display it
    if ( $grin_total ) {
        // Display the Grin total in the order tracking details
        echo '<p><strong>' . __( 'Equivalent in Grin:', 'Grinily.com' ) . ' ' . esc_html( $grin_total ) . ' GRIN</strong></p>';
    }
}
