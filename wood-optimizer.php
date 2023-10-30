<?php
/*
Plugin Name: Wood Optimizer
Description: WooCommerce plugin to optimize wood product selling based on location, shipping, and pricing.
Version: 1.3
Author: Cosmin Baidoc
*/

// Avoid direct calls to this file.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Activation
function wood_optimizer_activate() {
    // Code to run on activation
}
register_activation_hook( __FILE__, 'wood_optimizer_activate' );

// Deactivation
function wood_optimizer_deactivate() {
    // Code to run on deactivation
}
register_deactivation_hook( __FILE__, 'wood_optimizer_deactivate' );

function wood_optimizer_enqueue_scripts() {
    // Register the script
    wp_register_script('wood-optimizer', plugin_dir_url(__FILE__) . 'includes/js/woodOptimizer.js', array('jquery'), '1.2', true);
    
    // Localize script to pass data from PHP to JS, if needed
    wp_localize_script('wood-optimizer', 'woodOptimizerData', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
    if (is_cart() || is_checkout()) {
        wp_enqueue_script('cart-checkout-handler', plugin_dir_url(__FILE__) . 'includes/js/truckDetails.js', array('jquery'), '1.0', true);
    }
    // Enqueue the script
    wp_enqueue_script('wood-optimizer');

    // Also, enqueue the Google Maps API script
    wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyAuQDCZvTJhBIM3jRGgPu5BIYWA3a02D_U', array(), null, true);

    
}
add_action('wp_enqueue_scripts', 'wood_optimizer_enqueue_scripts');

function wood_optimizer_menu() {
    add_menu_page( 'Wood Optimizer Settings', 'Wood Optimizer', 'manage_options', 'wood-optimizer', 'wood_optimizer_settings_page', '', 200 );
}
add_action( 'admin_menu', 'wood_optimizer_menu' );
function wood_optimizer_submenu() {
    add_submenu_page('wood-optimizer', 'Associate Variations', 'Associate Variations', 'manage_options', 'wood-optimizer-variations', 'wood_optimizer_variations_page');
}
add_action('admin_menu', 'wood_optimizer_submenu');
//group variations by name
function get_variations_grouped_by_name() {
    $args = array('post_type' => 'product', 'posts_per_page' => -1);
    $products = get_posts($args);

    $variations_grouped = array();

    foreach ($products as $product) {
        $product_obj = wc_get_product($product->ID);
        
        if ($product_obj->is_type('variable')) {
            $variations = $product_obj->get_available_variations();

            foreach ($variations as $variation) {
                // Assuming 'attribute_pa_your-attribute-name' is the variation name you're referring to
                $variation_name = $variation['attributes']['attribute_pa_depozitul'];
                
                if (!isset($variations_grouped[$variation_name])) {
                    $variations_grouped[$variation_name] = array();
                }
                $variations_grouped[$variation_name][] = $variation;
            }
        }
    }
    
    return $variations_grouped;
}
//set coordiantes for variations
function wood_optimizer_variations_page() {
   
    echo '<h2>Associate Variation Names with Locations & Trucks</h2>';

    $variations_grouped = get_variations_grouped_by_name();
     if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['wood_optimizer_nonce']) && wp_verify_nonce($_POST['wood_optimizer_nonce'], 'save_variation_details')) {
        foreach ($variations_grouped as $variation_name => $variations) {
            $sanitized_variation_name = sanitize_title($variation_name);
            // Saving email addresses for each variant helping us to send emails on order complete
            update_option('variation_name_email_' . $sanitized_variation_name, sanitize_text_field($_POST['email_' . $sanitized_variation_name]));
            // Saving coordinates is the same
            update_option('variation_name_lat_' . $sanitized_variation_name, sanitize_text_field($_POST['lat_' . $sanitized_variation_name]));
            update_option('variation_name_lon_' . $sanitized_variation_name, sanitize_text_field($_POST['lon_' . $sanitized_variation_name]));

            // Save trucks
            $trucks = [];
            if (isset($_POST['truck_name_' . $sanitized_variation_name]) && is_array($_POST['truck_name_' . $sanitized_variation_name])) {
                for ($i = 0; $i < count($_POST['truck_name_' . $sanitized_variation_name]); $i++) {
                    $truck = [
                        'name' => sanitize_text_field($_POST['truck_name_' . $sanitized_variation_name][$i]),
                        'capacity' => sanitize_text_field($_POST['truck_capacity_' . $sanitized_variation_name][$i]),
                        'price_per_km' => sanitize_text_field($_POST['truck_price_per_km_' . $sanitized_variation_name][$i])
                    ];
                    $trucks[] = $truck;
                }
            }
            update_option('variation_name_trucks_' . $sanitized_variation_name, $trucks);
            error_log(print_r($trucks, true));
        }
    }
    foreach ($variations_grouped as $variation_name => $variations) {

        echo '<form method="post" action="">';
        echo '<p>';
        echo '<strong>Variation Name: ' . $variation_name . '</strong><br>';
        // Fetch saved emails if available
        $saved_email = get_option('variation_name_email_' . sanitize_title($variation_name), '');
        // Fetch saved coordinates for the variation name if available
        $saved_lat = get_option('variation_name_lat_' . sanitize_title($variation_name), '');
        $saved_lon = get_option('variation_name_lon_' . sanitize_title($variation_name), '');

        // email Input
        echo '<div style="display:block">';
        echo '<label for="email_' . sanitize_title($variation_name) . '">Email: </label>';
        echo '<input type="text" id="email_' . sanitize_title($variation_name) . '" name="email_' . sanitize_title($variation_name) . '" value="' . esc_attr($saved_email) . '">';
        echo '</div>';
        // Coordinates Input
        echo '<label for="lat_' . sanitize_title($variation_name) . '">Latitude: </label>';
        echo '<input type="text" id="lat_' . sanitize_title($variation_name) . '" name="lat_' . sanitize_title($variation_name) . '" value="' . esc_attr($saved_lat) . '">';
        echo '<label for="lon_' . sanitize_title($variation_name) . '">Longitude: </label>';
        echo '<input type="text" id="lon_' . sanitize_title($variation_name) . '" name="lon_' . sanitize_title($variation_name) . '" value="' . esc_attr($saved_lon) . '"><br>';
        
        $saved_trucks = get_option('variation_name_trucks_' . sanitize_title($variation_name), []);

        foreach ($saved_trucks as $truck) {
            echo '<div>';
            echo '<label>Truck Name: <input type="text" name="truck_name_' . sanitize_title($variation_name) . '[]" value="' . esc_attr($truck['name']) . '"></label>';
            echo '<label>Truck Capacity: <input type="text" name="truck_capacity_' . sanitize_title($variation_name) . '[]" value="' . esc_attr($truck['capacity']) . '"></label>';
            echo '<label>Price/km: <input type="text" name="truck_price_per_km_' . sanitize_title($variation_name) . '[]" value="' . esc_attr($truck['price_per_km']) . '"></label>';
            echo '<button type="button" onclick="this.parentElement.remove()">Remove Truck</button>';
            echo '</div>';
        }

        // Embed JavaScript to handle dynamic adding/removing of truck input sets
        echo '<script>
            function addTruckFields(variationName) {
                const container = document.getElementById("trucks_for_" + variationName);
                const div = document.createElement("div");
                
                const fieldsHTML = `
                    <label>Truck Name: <input type="text" name="truck_name_` + variationName + `[]"></label>
                    <label>Truck Capacity: <input type="text" name="truck_capacity_` + variationName + `[]"></label>
                    <label>Price/km: <input type="text" name="truck_price_per_km_` + variationName + `[]"></label>
                    <button type="button" onclick="this.parentElement.remove()">Remove Truck</button><br>
                `;
                div.innerHTML = fieldsHTML;
                container.appendChild(div);
            }
        </script>';
        // Truck Details Input
        echo '<div id="trucks_for_' . sanitize_title($variation_name) . '">';
        echo '</div>';
        echo '<button type="button" onclick="addTruckFields(\'' . sanitize_title($variation_name) . '\')">Add Truck</button>';
       
        echo '</p>';
    }
    
    // Add nonce for security and a submit button to save details
    wp_nonce_field('save_variation_details', 'wood_optimizer_nonce');
    echo '<input type="submit" value="Save Details">';
     echo '</form>';
}
function get_total_cart_volume() {

    $totalVolume = 0;

    $cart_prods_m3 = array();
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $_product =  wc_get_product( $cart_item['data']->get_id());
        //GET GET PRODUCT M3 
        $prod_m3 = $_product->get_length() * 
                $_product->get_width() * 
                $_product->get_height();
        //MULTIPLY BY THE CART ITEM QUANTITY
        //DIVIDE BY 1000000 (ONE MILLION) IF ENTERING THE SIZE IN CENTIMETERS
        $prod_m3 = ($prod_m3 * $cart_item['quantity']) / 1000000;
        //PUSH RESULT TO ARRAY
        array_push($cart_prods_m3, $prod_m3);
    }

    $totalVolume = array_sum($cart_prods_m3);

    return $totalVolume;
}
function wood_optimizer_settings_page() {
    // Your settings page HTML and form handling here
    echo '<h2>Wood Optimizer Settings</h2>';
}

function get_all_selling_points() {
    $variations_grouped = get_variations_grouped_by_name();
    $selling_points = [];

    foreach ($variations_grouped as $variation_name => $variations) {
        $sanitized_variation_name = sanitize_title($variation_name);
        $latitude = get_option('variation_name_lat_' . $sanitized_variation_name);
        $longitude = get_option('variation_name_lon_' . $sanitized_variation_name);
        
        if ($latitude && $longitude) {
            $selling_points[$variation_name] = [
                'lat' => $latitude,
                'lon' => $longitude
            ];
        }
    }

    return $selling_points;
}

function add_data_attributes_to_cart_item($product_name, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];

    $length = $product->get_length();
    $width = $product->get_width();
    $height = $product->get_height();

    return $product_name . sprintf('<div class="hidden-volume-data" data-length="%s" data-width="%s" data-height="%s"></div>', $length, $width, $height);
}

add_filter('woocommerce_cart_item_name', 'add_data_attributes_to_cart_item', 10, 3);


// add the shipping method
function add_wc_truck_shipping( $methods ) {
    $methods['truck_shipping'] = 'WC_Truck_Shipping';
    return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'add_wc_truck_shipping' );

//include and load the Shipping Method
function wood_optimizer_shipping_method_init() {
    if ( ! class_exists( 'WC_Truck_Shipping' ) ) {
        require 'includes/classes/shipping-method.php'; // Make sure to provide the correct path
    }
}
add_action( 'woocommerce_shipping_init', 'wood_optimizer_shipping_method_init' );


// Handle edge cases

function handle_large_orders($order_size) {
    // If an order is too large for one truck, determine the best combination of trucks or 
    // maybe even multiple trips.
}


/// Handle the AJAX request 
function handle_fetch_selling_point_and_truck() {
    $chosen_selling_point = '';

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if ($cart_item['variation'] && isset($cart_item['variation']['attribute_pa_depozitul'])) {
            $chosen_selling_point = $cart_item['variation']['attribute_pa_depozitul'];
            break;
        }
    }
        

    if (!$chosen_selling_point) {
        wp_send_json_error();
        return;
    }

    $sanitized_selling_point_name = sanitize_title($chosen_selling_point);
    $trucks = get_option('variation_name_trucks_' . $sanitized_selling_point_name);
    $latitude = get_option('variation_name_lat_' . $sanitized_selling_point_name);
    $longitude = get_option('variation_name_lon_' . $sanitized_selling_point_name);
    if ($latitude && $longitude) {
        $selling_points_coordinates = [
            'lat' => $latitude,
            'long' => $longitude
        ];
    }

    wp_send_json_success([
        'selling_point' => $chosen_selling_point,
        'coordinates' => $selling_points_coordinates,
        'lat' => $latitude,
        'long' => $longitude,
        'trucks' => $trucks,
        'customer_address' => WC()->customer->get_shipping_address_1() . ' ' . 
                              WC()->customer->get_shipping_city() . ' ' . 
                              WC()->customer->get_shipping_postcode() . ' ' . 
                              WC()->customer->get_shipping_state() . ' ' . 
                              WC()->customer->get_shipping_country(), // get shipping address from cart.
        'total_volume' => get_total_cart_volume()
    ]);
}

add_action('wp_ajax_fetch_selling_point_and_truck', 'handle_fetch_selling_point_and_truck');
add_action('wp_ajax_nopriv_fetch_selling_point_and_truck', 'handle_fetch_selling_point_and_truck');

// Function to customize the shipping label
function custom_shipping_label($label, $method) {
    if ($method->id === 'truck_shipping') {
        $truckDetails = get_transient('optimal_truck_details');
        if (!empty($truckDetails)) {
            if (is_array($truckDetails)) {
                foreach ($truckDetails as $truck) {
                    $label .= sprintf(
                        '<div class="truck-details">
                            <strong class="truck-name" data-name="%s">Truck Name: %s</strong><br>
                            <strong class="truck-capacity" data-capacity="%s">Capacity: %s</strong><br>
                            <strong class="truck-rate" data-rate="%s">Price/km: %s</strong><br>
                            <button class="removeTruckButton" data-truck-name="%s">Remove Truck</button>
                        </div>',
                        $truck['name'],
                        $truck['name'],
                        $truck['capacity'],
                        $truck['capacity'],
                        $truck['price_per_km'],
                        $truck['price_per_km'],
                        sanitize_title($truck['name'])
                    );
                }
            }
        }
    }
    return $label;
}
add_filter('woocommerce_cart_shipping_method_full_label', 'custom_shipping_label', 10, 2);


function handle_save_shipping_cost() {
    $is_cart = $_POST['is_cart'] === 'yes';  // Check if the current page is the cart page based on the passed parameter

    if(isset($_POST['shipping_cost'])) {
        $new_cost = sanitize_text_field($_POST['shipping_cost']);
        WC()->session->set('calculated_shipping_cost', $new_cost);
    }
    wp_send_json_success(array('is_cart' => $is_cart));
}

add_action('wp_ajax_save_shipping_cost', 'handle_save_shipping_cost');           // If user is logged in
add_action('wp_ajax_nopriv_save_shipping_cost', 'handle_save_shipping_cost');    // If user is not logged in

//error_log('Is session initialized: ' . ( WC()->session ? 'Yes' : 'No' ));

function update_shipping_rate_cost($rates, $package) {
    $calculated_shipping_cost = WC()->session->get('calculated_shipping_cost');

    if ($calculated_shipping_cost) {
        foreach ($rates as $rate) {
            if ('truck_shipping' === $rate->method_id) {
                $rate->cost = $calculated_shipping_cost;
            }
        }
    }
    return $rates;
}
add_filter('woocommerce_package_rates', 'update_shipping_rate_cost', 20, 2);

function handle_save_shipping_details_in_transient() {
    if(isset($_POST['shipping_details'])) {
        set_transient('optimal_truck_details', $_POST['shipping_details'], 12 * HOUR_IN_SECONDS); // Store for 12 hours
    }
    wp_send_json_success();
}
add_action('wp_ajax_save_shipping_details_in_transient', 'handle_save_shipping_details_in_transient');
add_action('wp_ajax_nopriv_save_shipping_details_in_transient', 'handle_save_shipping_details_in_transient');


?>