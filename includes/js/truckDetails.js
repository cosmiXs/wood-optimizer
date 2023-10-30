jQuery(document).ready(function($) {
    //Initially hide the details
    $('.truck-details').hide();

    // Watch for changes in the shipping address
    $(document.body).on('updated_checkout', function() {
        // If a shipping address is chosen, display the truck details
        if ($('#shipping_address_1').val() && $('#shipping_city').val() && $('#calc_shipping_city').val() ) {
            $('.truck-details').show();
        } else {
            $('.truck-details').hide();
        }
    });
});