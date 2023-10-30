<?/**
 * Summary.
 *
 * Ads a shipping class to woocommerce
 *
 * @since Version 1.0
 */
class WC_Truck_Shipping extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        $this->id                 = 'truck_shipping'; 
        $this->method_title       = __( 'Truck Shipping', 'cosmixs-shipping' ); 
        $this->method_description = __( 'Delivery by Truck offered by Selling Point', 'cosmixs-shipping' ); 
        $this->instance_id        = absint($instance_id);

        $this->init();

        // Ensure the session is loaded
        add_action('woocommerce_init', array($this, 'ensure_session_is_loaded'));
    }

    public function ensure_session_is_loaded() {
        if (null === WC()->session && ! doing_action('woocommerce_init')) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
    }

    public function init() {
        $this->init_form_fields(); 
        $this->init_settings(); 

        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'cosmixs-shipping' ),
                'type' => 'checkbox',
                'description' => __( 'Enable this shipping method?', 'cosmixs-shipping' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __( 'Truck Shipping', 'cosmixs-shipping' ),
                'type' => 'text',
                'description' => __( 'Delivery by Truck offered by Selling Point', 'cosmixs-shipping' ),
                'default' => __( 'Truck Shipping', 'cosmixs-shipping' )
            )
        );
    }

    public function calculate_shipping($package = array()) {
        $cost = WC()->session->get('calculated_shipping_cost', 0); // Default to 0 if no value is set

        if (!$cost || $cost <= 0) {
            // Handle fallback scenario or set a minimum shipping cost
            // $cost = some_default_value; 
        }

        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost
        );

        $this->add_rate($rate);
    }

    
}
?>