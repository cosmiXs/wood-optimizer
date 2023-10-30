let currentDistance = null; // This will store the most recently computed distance.
let currentVolume = null;   // This will store the most recent cart volume.
let currentTrucks = null;   // This will store the current trucks
let optimalCombinationResult = null; // This will store the optimal combination of trucks

jQuery(document).ready(function($) {
    // This will be called when the checkout page loads.
    let sellingPoints = [];  // This will store the selling points fetched from the backend.
    // Listen to the update on the checkout form.
    $('form.checkout').on('update', function () {
        // Extract customer's address.
        let customerAddress = $('#shipping_address_1').val() + ', ' + $('#shipping_city').val() + ', ' + $('#shipping_postcode').val();

        if (!customerAddress || sellingPoints.length === 0) {
            return;
        }
        //console.log(customerAddress);
        
    });
   
    // jQuery(document.body).on('updated_wc_div', function() {
    //     currentVolume = calculateRemainingVolume();
    //     let distance = getDistance(); // Assuming you have a function or way to get the updated distance
    //     let rVolume = currentVolume; // Function to get the updated cart volume
    //     let currentTrucks = getCurrentTrucks(); // Function to get the current trucks
    //     console.log('VOLUME: '+ rVolume);
    //     calculateShippingCost(distance, currentTrucks, rVolume); // or update the DOM or do whatever you need with the new volume.
    // });
    
    $('#shipping_address_1, #shipping_city, #calc_shipping_city').change(function() {
        console.log("address changed");
        
    });

    $("section.shipping-calculator-form button.button").click(function() {
        currentVolume = calculateRemainingVolume();
        console.log('refresh costs on \n CART ADDRESS Update');
        let distance = getDistance(); // Assuming you have a function or way to get the updated distance
        let rVolume = currentVolume; // Function to get the updated cart volume
        let currentTrucks = getCurrentTrucks(); // Function to get the current trucks
        calculateShippingCost(distance, currentTrucks, rVolume); // or update the DOM or do whatever you need with the new volume.
    });
    function calculateRemainingVolume() {
        let totalVolume = 0;
        // Loop over each cart item and accumulate the total volume
        $('.woocommerce-cart-form .cart_item').each(function() {
            let quantity = $(this).find('.quantity input').val();
            
            // Get values from the hidden div
            let hiddenData = $(this).find('.hidden-volume-data');
            let length = hiddenData.data('length');
            let width = hiddenData.data('width');
            let height = hiddenData.data('height');
    
            let prod_m3 = length * width * height * quantity;
            prod_m3 = prod_m3 / 1000000; // Convert to cubic meters
            totalVolume += prod_m3;
        });
        return totalVolume;
    }
    
    // Optional: If you use AJAX add to cart on shop/archive pages
    $('body').on('added_to_cart', function() {
        // Similar to above, re-run your function
        let distance = getDistance();
        let remainingVolume = getRemainingVolume();
        let currentTrucks = getCurrentTrucks(); // Function to get the current trucks
        //console.log('calculate costs on add_to_cart');
        calculateShippingCost(distance, currentTrucks, remainingVolume);
    });
    
    function fetchSellingPointAndTruckData() {
        return $.ajax({
            type: 'POST',
            url: woodOptimizerData.ajax_url,
            data: {
                action: 'fetch_selling_point_and_truck'
            },
            success: function (response) {
                if(response.success) {
                    console.log("fetchSellingPointAndTruckData");
                } else {
                    console.error("Couldn't fetch data.");
                }
            },
            error: function (error) {
                console.error("AJAX Error:", error);
            }
        });
    }

    // Call the function to fetch data - this is not done after an update so maybe this is the cause for not showing updated shiping costs on update_cart event
    fetchSellingPointAndTruckData().done(function (response) {
        if (response.success) {
            const productLocation = new google.maps.LatLng(response.data.lat, response.data.long);
            const trucks = response.data.trucks;
            const clientAddress = response.data.customer_address; // Retrieve address from cart
            const totalVolume = (currentVolume === null ) ? response.data.total_volume : currentVolume; // Retrieve the volume from initial cart or after update
            currentTrucks = trucks;
            currentVolume = totalVolume; // Store the volume globally
            initMap(productLocation, clientAddress, trucks, totalVolume);
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error("Request failed: " + textStatus);
        console.error(errorThrown);
    });
    function initMap(productLocation, clientAddress, trucks, totalVolume) {
        const directionsService = new google.maps.DirectionsService();

        calculateRoute(directionsService, productLocation, clientAddress).then(distance => {
            currentDistance = distance; // Store the distance globally
            const shippingCost = calculateShippingCost(distance, trucks, totalVolume);
            //console.log(`Total shipping cost: ${shippingCost}`);
        });
    }

    function calculateRoute(directionsService, origin, destination) {
        return new Promise((resolve, reject) => {
            directionsService.route({
                origin: origin,
                destination: destination,
                travelMode: 'DRIVING'
            }, (result, status) => {
                if (status === 'OK') {
                    const distanceInMeters = result.routes[0].legs[0].distance.value;
                    const distanceInKm = distanceInMeters / 1000;
                    console.log('Shipping distance: '+ distanceInKm);
                    resolve(distanceInKm);
                } else {
                    reject(new Error('Failed to calculate route.'));
                }
            });
        });
    }
    
    function calculateShippingCost(distance, trucks, remainingVolume) {
        console.log('The data for wich shipping is calculated\n  DISTANCE: '+ distance + '\n  Available trucks: ' + JSON.stringify(trucks) + '\n  CART volume: ' + remainingVolume );
        optimalCombinationResult = findOptimalTruckCombination(trucks, remainingVolume, distance);
        sessionStorage.setItem('hasUpdatedShippingCost', 'false');
        
        $.ajax({
            type: 'POST',
            url: woodOptimizerData.ajax_url,
            data: {
                action: 'save_shipping_cost',
                shipping_cost: optimalCombinationResult.totalCost,
                is_cart: $('body').hasClass('woocommerce-cart') ? 'yes' : 'no' 
            },
            success: function(response) {
                //console.log('is cart: '+ response.data.is_cart );
                //console.log('session: '+ sessionStorage.getItem('hasUpdatedShippingCost') );
                if (response.data.is_cart && sessionStorage.getItem('hasUpdatedShippingCost') !== 'true') {
                    sessionStorage.setItem('hasUpdatedShippingCost', 'true'); // Set the flag in sessionStorage
                    // Maybe trigger recalculation of totals here
                    $(document.body).trigger('updated_wc_div');
                    $(document.body).trigger('update_checkout');
                    //console.log('Shipping cost CART> '+ optimalCombinationResult.totalCost);
                }
                if (response.data.is_cart && sessionStorage.getItem('hasUpdatedShippingCost')){
                    sessionStorage.setItem('hasUpdatedShippingCost', 'true');
                    //console.log('Shipping cost RESET> '+ optimalCombinationResult.totalCost);
                    $(document.body).trigger('update_checkout');
                }
                if (!response.data.is_cart){
                    //console.log('Shipping cost CHECKOUT> '+ optimalCombinationResult.totalCost);
                    $(document.body).trigger('updated_checkout');
                }
                if (optimalCombinationResult && optimalCombinationResult.combination) {
                    // Save the computed details in a WordPress transient
                    $.ajax({
                        type: 'POST',
                        url: woodOptimizerData.ajax_url,
                        data: {
                            action: 'save_shipping_details_in_transient',
                            shipping_details: optimalCombinationResult.combination
                        },
                        success: function(response) {
                            console.log('Optimal combination: '+ JSON.stringify(optimalCombinationResult.combination) );
                            
                        }
                    });
                }
                
            },
            error: function (error) {
                console.error("AJAX Error:", error);
            }
        });
        return optimalCombinationResult.totalCost;
    }
    $(document).on('click', '.removeTruckButton', function() {
        // 1. Remove the truck from display
        const removedTruckDiv = $(this).closest('.truck-details');
        removedTruckDiv.remove();
    
        // 2. Gather all remaining trucks from the display
        const remainingTrucks = [];
        let totalShippingCost = 0;
        $('.truck-details').each(function() {
            const truckName = $(this).find('.truck-name').data('name');
            const truckCapacity = parseFloat($(this).find('.truck-capacity').data('capacity'));
            const truckRate = parseFloat($(this).find('.truck-rate').data('rate'));
    
            remainingTrucks.push({
                name: truckName,
                capacity: truckCapacity,
                price_per_km: truckRate
            });
            3.// Calculate shipping cost directly
            totalShippingCost += (truckRate * currentDistance);
            
        });
    
        
    
        // 4. Update the WooCommerce cart with the new shipping cost
        $.ajax({
            type: 'POST',
            url: woodOptimizerData.ajax_url,
            data: {
                action: 'save_shipping_cost',
                shipping_cost: totalShippingCost,
                is_cart: $('body').hasClass('woocommerce-cart') ? 'yes' : 'no'
            },
            success: function(response) {
                console.log('total costs: ', totalShippingCost);
                // 5. Trigger cart recalculation to reflect the new shipping cost
                if ($('body').hasClass('woocommerce-cart')) {
                    $(document.body).trigger('updated_wc_div');
                    $(document.body).trigger('update_checkout');
                } else {
                    $(document.body).trigger('update_checkout');
                }
    
                // 6. Optionally, store the remaining trucks in a WordPress transient for later retrieval or display
                $.ajax({
                    type: 'POST',
                    url: woodOptimizerData.ajax_url,
                    data: {
                        action: 'save_shipping_details_in_transient',
                        shipping_details: remainingTrucks
                    },
                    success: function(response) {
                        console.log('remaining trucks: ', remainingTrucks);
                       // 5. Trigger cart recalculation to reflect the new shipping cost
                        if ($('body').hasClass('woocommerce-cart')) {
                            console.log('>> CART ');
                            $(document.body).trigger('updated_wc_div');
                            $(document.body).trigger('update_checkout');
                        } else {
                            $(document.body).trigger('update_checkout');
                        }
                         
                    }
                });
            },
            error: function(error) {
                console.error("AJAX Error:", error);
            }
        });
    });
    
    
    
    
    // function findOptimalTruckCombination(trucks, volume, distance) {
    //     let bestCost = Infinity;
    //     let bestCombination = null;
        
    //     // Generate all possible combinations of trucks
    //     let combinations = getCombinations(trucks);
    
    //     for (let combination of combinations) {
    //         console.log("Evaluating combination: ", combination); // Add this
    //         let combinedVolume = combination.reduce((sum, truck) => sum + truck.capacity, 0);
    //         let combinedRate = combination.reduce((sum, truck) => {
    //             console.log("Truck:", truck.name, "Price per km:", truck.price_per_km, "Distance:", distance, "Result:", truck.price_per_km * distance); // Print individual calculations
    //             return sum + truck.price_per_km * distance;
    //         }, 0);
    //         // console.log("Evaluating Volume: ", combinedVolume); 
    //         // console.log("Evaluating Rate: ", combinedRate);
            
    //         // If the combination covers the volume and has a better rate than the current best
    //         if (combinedVolume >= volume && combinedRate < bestCost) {
    //             bestCost = combinedRate;
    //             bestCombination = combination;
    //         }
    //     }
    
    //     return {
    //         combination: bestCombination,
    //         totalCost: bestCost
    //     };
    // }    
    // function findOptimalTruckCombination(trucks, volume, distance) {
    //     // Sort the trucks by capacity for efficiency
    //     trucks.sort((a, b) => a.capacity - b.capacity);
    //     let truckPenalty = 10; // Or any other value you deem suitable

    //     // Check if a single truck can cover the volume
    //     for(let truck of trucks) {
    //         console.log ('Truck:', truck);
    //         if(truck.capacity >= volume) {
    //             return {
    //                 combination: [truck],
    //                 totalCost: truck.price_per_km * distance
    //             };
    //         }
    //     }
    
    //     // Generate all combinations of trucks
    //     let combinations = getCombinations(trucks);
    //     let bestCost = Infinity;
    //     let bestCombination = null;
    
    //     for (let combination of combinations) {
    //         console.log ('Truck Combination:', combination);
    //         let combinedVolume = combination.reduce((sum, truck) => sum + truck.capacity, 0);
    //         let combinedRate = combination.reduce((sum, truck) => sum + truck.price_per_km * distance, 0) + (truckPenalty * combination.length);
    //         console.log ('Combined Volume:', combinedVolume);
    //         console.log ('Combined Rate:', combinedRate);
            
    //         if (combinedVolume >= volume) {
    //             if (combinedRate < bestCost || (combinedRate === bestCost && combination.length < bestCombination.length)) {
    //                 bestCost = combinedRate;
    //                 bestCombination = combination;
    //                 console.log ('Best cost:', bestCost);
    //                 console.log ('Best combination:', bestCombination);
    //             }
    //         }
    //     }
        
    //     return {
    //         combination: bestCombination,
    //         totalCost: bestCost
    //     };
    // }
    function findOptimalTruckCombination(trucks, volume, distance) {
        let bestCost = Infinity;
        let bestCombination = null;
    
        // Step 1: Sort the trucks based on their capacity
        trucks.sort((a, b) => parseFloat(a.capacity) - parseFloat(b.capacity));
    
        function findCombination(remainingVolume, startIndex, currentCombination, currentCost) {
            if (remainingVolume <= 0) {
                if (currentCost < bestCost) {
                    bestCost = currentCost;
                    bestCombination = [...currentCombination];
                }
                return;
            }
            for (let i = startIndex; i < trucks.length; i++) {
                let truck = trucks[i];
                let newCombination = [...currentCombination, truck];
                let newCost = currentCost + (truck.price_per_km * distance);
                findCombination(remainingVolume - parseFloat(truck.capacity), i, newCombination, newCost);
            }
        }
    
        // Start the recursive search
        findCombination(volume, 0, [], 0);
        
        return {
            combination: bestCombination,
            totalCost: bestCost
        };
    }
    
    // Utility function to generate combinations
    function getCombinations(array) {
        let result = [];
        let f = function (prefix, array) {
            for (let i = 0; i < array.length; i++) {
                result.push(prefix.concat(array[i]));
                f(prefix.concat(array[i]), array.slice(i + 1));
            }
        }
        f([], array);
        return result;
    }
    
    

    
    function getDistance() {
        return currentDistance;
    }
    
    function getRemainingVolume() {
        return currentVolume;
    }

    function getCurrentTrucks() {
        return currentTrucks;
    }

});

jQuery('form.checkout').on('update', function () {
    $(document.body).trigger('update_checkout');
});