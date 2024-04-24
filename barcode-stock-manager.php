<?php
/*
Plugin Name: Barcode Stock Manager
Description: A simple barcode stock management plugin for WooCommerce with barcode scanning using ZXing.
Version: 1.1.1
Author: LayLay Bebe
Author URI: https://laylaybebe.com
*/

// Enqueue ZXing library and plugin scripts
function barcode_stock_manager_enqueue_scripts() {
    wp_enqueue_script('zxing', 'https://unpkg.com/@zxing/library@latest/umd/index.min.js', array(), '0.18.2', true);
    wp_enqueue_style('barcode-stock-manager-style', plugins_url('style.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'barcode_stock_manager_enqueue_scripts');

// Add plugin menu item
add_action('admin_menu', 'barcode_stock_manager_menu');
function barcode_stock_manager_menu() {
    add_menu_page('Barcode Stock Manager', 'Barcode Stock Manager', 'manage_options', 'barcode-stock-manager', 'barcode_stock_manager_page');
}

// Add custom wholesale cost field to product
function add_wholesale_cost_field() {
    woocommerce_wp_text_input(
        array(
            'id'          => '_wholesale_cost',
            'label'       => __('Wholesale Cost', 'woocommerce'),
            'placeholder' => '',
            'desc_tip'    => true,
            'description' => __('Enter the wholesale cost of the product.', 'woocommerce'),
        )
    );
}
add_action('woocommerce_product_options_pricing', 'add_wholesale_cost_field');

// Save custom wholesale cost field
function save_wholesale_cost_field($post_id) {
    $wholesale_cost = isset($_POST['_wholesale_cost']) ? sanitize_text_field($_POST['_wholesale_cost']) : '';
    update_post_meta($post_id, '_wholesale_cost', $wholesale_cost);
}
add_action('woocommerce_process_product_meta', 'save_wholesale_cost_field');

// Plugin page content
function barcode_stock_manager_page() {
    ?>
    <div class="wrap">
        <h1>Barcode Stock Manager</h1>
        <div id="scanner-container">
            <video id="video" width="300" height="200"></video>
            <div class="controls">
                <button id="startButton">Start Scanner</button>
            </div>
        </div>
        <div id="barcodeLabel" style="display: none;"></div>
        <div id="loading-animation" style="display: none;">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>
        <div id="product-info" style="display: none;">
            <img id="product-image" src="" alt="Product Image" width="100">
            <p><strong>Product Name:</strong> <span id="product-name"></span></p>
            <p><strong>Price:</strong> <span id="product-price"></span></p>
            <p><strong>Wholesale Cost:</strong> <span id="product-wholesale-cost"></span></p>
            <p><strong>Current Stock:</strong> <span id="current-stock"></span></p>
        </div>
        <form method="post" action="" id="stock-form" style="display: none;">
            <input type="hidden" id="barcode" name="barcode">
            <div id="new-product-fields" style="display: none;">
                <label for="new-product-name">Product Name:</label>
                <input type="text" id="new-product-name" name="new_product_name"><br>
                <label for="sale-price">Sale Price:</label>
                <input type="number" id="sale-price" name="sale_price" min="0" step="0.01"><br>
                <label for="wholesale-price">Wholesale Price:</label>
                <input type="number" id="wholesale-price" name="wholesale_price" min="0" step="0.01"><br>
            </div>
            <label for="quantity">Quantity:</label>
            <input type="number" id="quantity" name="quantity" min="1" value="1"><br>
            <input type="submit" name="action" value="Increase Stock">
            <input type="submit" name="action" value="Decrease Stock" id="decrease-stock-btn">
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    var codeReader;
    $(document).ready(function() {
        startScanner();
    });

	function startScanner() {
	    codeReader = new ZXing.BrowserBarcodeReader();
	    navigator.mediaDevices.enumerateDevices()
	        .then(function(devices) {
	            var videoDevices = devices.filter(function(device) {
	                return device.kind === 'videoinput';
	            });
	
	            var selectedCameraId = null;
	            videoDevices.forEach(function(device) {
	                if (device.label.toLowerCase().includes('back') ||
	                    device.label.toLowerCase().includes('rear')) {
	                    selectedCameraId = device.deviceId;
	                }
	            });
	
	            if (!selectedCameraId && videoDevices.length > 0) {
	                selectedCameraId = videoDevices[0].deviceId;
	            }
	
	            if (selectedCameraId) {
	                codeReader.decodeFromInputVideoDevice(selectedCameraId, 'video', {
	                    videoFacingMode: 'environment',
	                    focusMode: 'continuous'
	                })
	                    .then(function(result) {
	                        $('#barcode').val(result.text);
	                        $('#barcodeLabel').text('Barcode: ' + result.text).show();
	                        $('#video').hide();
	                        $('#loading-animation').show();
	                        checkProduct(result.text);
	                    })
	                    .catch(function(err) {
	                        console.error(err);
	                        if (err.name === 'NotAllowedError') {
	                            alert('Camera permission denied');
	                        } else {
	                            alert('An error occurred during scanning');
	                        }
	                    });
	            } else {
	                alert('No camera found on this device');
	            }
	        })
	        .catch(function(err) {
	            console.error(err);
	            alert('An error occurred while accessing the camera');
	        });
	}

    function stopScanner() {
        if (codeReader) {
            codeReader.reset();
            codeReader = null;
        }
    }

    $('#startButton').on('click', function() {
        $('#video').show();
        startScanner();
    });
	    
    function checkProduct(barcode) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'check_product_exists',
                barcode: barcode
            },
            success: function(response) {
                $('#loading-animation').hide();
                if (response.exists) {
                    $('#product-info').show();
                    if (response.image) {
                        $('#product-image').attr('src', response.image);
                    } else {
                        $('#product-image').attr('src', '<?php echo wc_placeholder_img_src(); ?>');
                    }
                    $('#product-name').text(response.name);
                    $('#product-price').html(response.price);
                    $('#product-wholesale-cost').text(response.wholesale_cost);
                    $('#current-stock').text(response.stock);
                    $('#new-product-fields').hide();
                    $('#decrease-stock-btn').show();
                } else {
                    $('#product-info').hide();
                    $('#new-product-fields').show();
                    $('#decrease-stock-btn').hide();
                }
                $('#stock-form').show();
            },
            error: function() {
                $('#loading-animation').hide();
                alert('An error occurred while checking the product.');
            }
        });
    }
    </script>
    <?php

    if (isset($_POST['action'])) {
        $barcode = sanitize_text_field($_POST['barcode']);
        $action = sanitize_text_field($_POST['action']);
        $quantity = intval($_POST['quantity']);

        // Find the product by barcode (assuming barcode is stored in SKU field)
        $product_id = wc_get_product_id_by_sku($barcode);

        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($action === 'Increase Stock') {
                $new_stock = $product->get_stock_quantity() + $quantity;
                $product->set_stock_quantity($new_stock);
                $product->save();
                echo '<p>Stock increased by ' . $quantity . '. New stock: ' . $new_stock . '</p>';
            } elseif ($action === 'Decrease Stock') {
                $stock = $product->get_stock_quantity();
                if ($stock >= $quantity) {
                    $new_stock = $stock - $quantity;
                    $product->set_stock_quantity($new_stock);
                    $product->save();
                    echo '<p>Stock decreased by ' . $quantity . '. New stock: ' . $new_stock . '</p>';
                } else {
                    echo '<p>Insufficient stock. Cannot decrease by ' . $quantity . '.</p>';
                }
            }
        } else {
            if ($action === 'Increase Stock') {
                $new_product_name = sanitize_text_field($_POST['new_product_name']);
                $sale_price = floatval($_POST['sale_price']);
                $wholesale_price = floatval($_POST['wholesale_price']);
                // Create a new product
                $product = new WC_Product();
                $product->set_name($new_product_name);
                $product->set_status('publish');
                $product->set_sku($barcode);
                $product->set_manage_stock(true);
                $product->set_stock_quantity($quantity);
                $product->set_price($sale_price);
                $product->set_regular_price($sale_price);
                $product->update_meta_data('_wholesale_cost', $wholesale_price);
                $product->save();
                echo '<p>New product "' . $new_product_name . '" created with stock ' . $quantity . ', sale price ' . wc_price($sale_price) . ', and wholesale price ' . wc_price($wholesale_price) . '.</p>';
            } else {
                echo '<p>Product not found.</p>';
            }
        }
    }
}

// AJAX handler for checking if a product exists
add_action('wp_ajax_check_product_exists', 'check_product_exists');
function check_product_exists() {
    $barcode = sanitize_text_field($_POST['barcode']);
    $product_id = wc_get_product_id_by_sku($barcode);

    if ($product_id) {
        $product = wc_get_product($product_id);
        $image_id = $product->get_image_id();
        $image_url = '';
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
        }
        
        // Get the formatted price HTML
        $formatted_price = $product->get_price_html();

        // Get the wholesale cost
        $wholesale_cost = get_post_meta($product_id, '_wholesale_cost', true);

        $response = array(
            'exists' => true,
            'name' => $product->get_name(),
            'image' => $image_url,
            'price' => $formatted_price,
            'stock' => $product->get_stock_quantity(),
            'wholesale_cost' => $wholesale_cost
        );
    } else {
        $response = array('exists' => false);
    }

    wp_send_json($response);
}
