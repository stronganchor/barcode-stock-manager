<?php
/*
Plugin Name: Barcode Stock Manager
Description: A simple barcode stock management plugin for WooCommerce with barcode scanning.
Version: 1.0
Author: LayLay Bebe
Author URI: https://laylaybebe.com
*/

// Enqueue QuaggaJS library
function barcode_stock_manager_enqueue_scripts() {
    wp_enqueue_script('quagga', 'https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js', array(), '0.12.1', true);
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
            <div id="interactive" class="viewport"></div>
            <div class="controls">
                <button id="startButton">Start Scanner</button>
                <button id="resetButton">Reset Scanner</button>
				<div id="barcodeLabel">
					Barcode will show here
				</div>
            </div>
        </div>
        <div id="result_strip">
            <ul class="collector"></ul>
        </div>
        <form method="post" action="">
            <input type="hidden" id="barcode" name="barcode">
            <input type="submit" name="action" value="Increase Stock">
            <input type="submit" name="action" value="Decrease Stock">
        </form>
    </div>

    <script>
    var App = {
        init: function() {
            var self = this;

            Quagga.init(this.state, function(err) {
                if (err) {
                    console.log(err);
                    return;
                }
                Quagga.start();
            });

            Quagga.onDetected(function(result) {
                var code = result.codeResult.code;
                document.getElementById('barcode').value = code;
				document.getElementById('barcodeLabel').innerText = code;
				console.log(code);
                Quagga.stop();
            });
        },
        state: {
            inputStream: {
                type: "LiveStream",
                constraints: {
                    width: 640,
                    height: 480,
                    facingMode: "environment"
                }
            },
            locator: {
                patchSize: "medium",
                halfSample: true
            },
            numOfWorkers: 2,
            frequency: 10,
            decoder: {
                readers: ["ean_reader"]
            },
            locate: true
        }
    };

    document.getElementById('startButton').addEventListener('click', function() {
        App.init();
    });

    document.getElementById('resetButton').addEventListener('click', function() {
        Quagga.stop();
        document.getElementById('barcode').value = '';
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
