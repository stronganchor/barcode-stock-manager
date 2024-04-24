<?php
/*
Plugin Name: Barcode Stock Manager
Description: A simple barcode stock management plugin for WooCommerce with barcode scanning using ZXing.
Version: 1.0.5
Author: LayLay Bebe
Author URI: https://laylaybebe.com
*/

// Enqueue ZXing library
function barcode_stock_manager_enqueue_scripts() {
    wp_enqueue_script('zxing', 'https://unpkg.com/@zxing/library@latest/umd/index.min.js', array(), '0.18.2', true);
}
add_action('admin_enqueue_scripts', 'barcode_stock_manager_enqueue_scripts');

// Add plugin menu item
add_action('admin_menu', 'barcode_stock_manager_menu');
function barcode_stock_manager_menu() {
    add_menu_page('Barcode Stock Manager', 'Barcode Stock Manager', 'manage_options', 'barcode-stock-manager', 'barcode_stock_manager_page');
}

// Plugin page content
function barcode_stock_manager_page() {
    ?>
    <div class="wrap">
        <h1>Barcode Stock Manager</h1>
        <div id="scanner-container">
            <video id="video" width="300" height="200"></video>
            <div class="controls">
                <button id="startButton">Start Scanner</button>
                <div id="barcodeLabel">
                    Barcode will show here
                </div>
            </div>
        </div>
        <div id="product-info" style="display: none;">
            <img id="product-image" src="" alt="Product Image" width="100">
            <p id="product-name"></p>
        </div>
        <form method="post" action="">
            <input type="hidden" id="barcode" name="barcode">
            <div id="new-product-fields" style="display: none;">
                <label for="new-product-name">Product Name:</label>
                <input type="text" id="new-product-name" name="new_product_name"><br>
            </div>
            <label for="quantity">Quantity:</label>
            <input type="number" id="quantity" name="quantity" min="1" value="1"><br>
            <input type="submit" name="action" value="Increase Stock">
            <input type="submit" name="action" value="Decrease Stock">
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
	                        $('#barcodeLabel').text(result.text);
	                        $('#video').hide();
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
                if (response.exists) {
                    $('#product-info').show();
                    if (response.image) {
                        $('#product-image').attr('src', response.image);
                    } else {
                        $('#product-image').attr('src', '<?php echo wc_placeholder_img_src(); ?>');
                    }
                    $('#product-name').text(response.name);
                    $('#new-product-fields').hide();
                } else {
                    $('#product-info').hide();
                    $('#new-product-fields').show();
                }
            },
            error: function() {
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
                // Create a new product
                $product = new WC_Product();
                $product->set_name($new_product_name);
                $product->set_status('publish');
                $product->set_sku($barcode);
                $product->set_stock_quantity($quantity);
                $product->save();
                echo '<p>New product "' . $new_product_name . '" created with stock ' . $quantity . '.</p>';
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
        $response = array(
            'exists' => true,
            'name' => $product->get_name(),
            'image' => $image_url
        );
    } else {
        $response = array('exists' => false);
    }

    wp_send_json($response);
}
