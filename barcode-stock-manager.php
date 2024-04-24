<?php
/*
Plugin Name: Barcode Stock Manager
Description: A simple barcode stock management plugin for WooCommerce with barcode scanning using ZXing.
Version: 1.0.2
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
                <button id="resetButton">Reset Scanner</button>
                <div id="barcodeLabel">
                    Barcode will show here
                </div>
            </div>
        </div>
        <form method="post" action="">
            <input type="hidden" id="barcode" name="barcode">
            <input type="submit" name="action" value="Increase Stock">
            <input type="submit" name="action" value="Decrease Stock">
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    var codeReader;
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

    $('#resetButton').on('click', function() {
        stopScanner();
        $('#barcode').val('');
        $('#barcodeLabel').text('Barcode will show here');
        $('#video').show();
    });
    </script>
    <?php

    if (isset($_POST['action'])) {
        $barcode = sanitize_text_field($_POST['barcode']);
        $action = sanitize_text_field($_POST['action']);

        // Find the product by barcode (assuming barcode is stored in SKU field)
        $product_id = wc_get_product_id_by_sku($barcode);

        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($action === 'Increase Stock') {
                $new_stock = $product->get_stock_quantity() + 1;
                $product->set_stock_quantity($new_stock);
                $product->save();
                echo '<p>Stock increased by 1. New stock: ' . $new_stock . '</p>';
            } elseif ($action === 'Decrease Stock') {
                $stock = $product->get_stock_quantity();
                if ($stock > 0) {
                    $new_stock = $stock - 1;
                    $product->set_stock_quantity($new_stock);
                    $product->save();
                    echo '<p>Stock decreased by 1. New stock: ' . $new_stock . '</p>';
                } else {
                    echo '<p>Stock is already 0. Cannot decrease further.</p>';
                }
            }
        } else {
            if ($action === 'Increase Stock') {
                // Create a new product
                $product = new WC_Product();
                $product->set_name('New Product');
                $product->set_status('publish');
                $product->set_sku($barcode);
                $product->set_stock_quantity(1);
                $product->save();
                echo '<p>New product created with stock 1.</p>';
            } else {
                echo '<p>Product not found.</p>';
            }
        }
    }
}
