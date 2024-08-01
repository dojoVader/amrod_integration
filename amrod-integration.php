<?php
/**
 * Plugin Name
 *
 * @package           PluginPackage
 * @author            Okeowo Aderemi
 * @copyright         2024 Retani Consults
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Amrod Integration for Woocommerce
 * Plugin URI:
 * Description:       This plugin handles integration to Amrod API with the Woocommerce platform
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Okeowo Aderemi
 * Author URI:        https://okeowoaderemi.com
 * Text Domain:       plugin-slug
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://example.com/my-plugin/
 */

// Create a function to add menu to our page

const AMROD_TOKEN_KEY = "amrod_token";
const AMROD_DATA_RESPONSE =
    __DIR__ .
    DIRECTORY_SEPARATOR .
    "amrod-data" .
    DIRECTORY_SEPARATOR .
    "amrod_data.json";
const AMROD_GET_PRODUCT_BRANDING_ENDPOINT = "https://vendorapi.amrod.co.za/api/v1/Products/GetProductsAndBranding";
const AMROD_GET_PRICES = "https://vendorapi.amrod.co.za/api/v1/Prices/";

function render_option_page()
{
    // Create the section for the plugin
    add_options_page(
        "Amrod Settings",
        "Amrod API",
        "manage_options",
        "amrod_api",
        "render_settings_page"
    );
}

function amrodLogin()
{
    // Get the body for the Amrod Credentials and make a POST request to the API
    $http_data = [
        "username" => get_option("amrod_option_page")["username"],
        "password" => get_option("amrod_option_page")["password"],
        "customercode" => get_option("amrod_option_page")["customercode"],
    ];

    $amrodEndpoint = get_option("amrod_option_page")["endpoint"];

    // Make the API call to the endpoint
    $amrodResponse = wp_remote_post($amrodEndpoint, [
        "method" => "POST",
        "body" => json_encode($http_data),
        "headers" => [
            "Content-Type" => "application/json",
            "Accept" => "application/json",
        ],
    ]);
    $amrodToken = json_decode($amrodResponse["body"]);
    update_option(AMROD_TOKEN_KEY, $amrodToken->token); // Store the token in the database
    wp_redirect(admin_url("options-general.php?page=amrod_api"));
}

function render_settings_page()
{
    ?>
    <h2>Amrod API Integration</h2>
    <form action="options.php" method="post">
		<?php
  settings_fields("amrod_settings");
  do_settings_sections("amrod_page");
  ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e(
            "Save"
        ); ?>"/>
    </form>
	<?php
}

function amrodFetchProduct()
{
    $header = [
        "Authorization" => "Bearer " . get_option(AMROD_TOKEN_KEY),
    ];
    $responseBody = wp_remote_get(AMROD_GET_PRODUCT_BRANDING_ENDPOINT, [
        "headers" => $header,
    ]);
    $statusCode = $responseBody["response"]["code"];
    if ($statusCode === 401) {
        // Remove the token from the wp_option table
        delete_option(AMROD_TOKEN_KEY);
        add_action("admin_notices", "tokenExpired");
        do_action("admin_notices");
        // Redirect to admin page
        wp_redirect(admin_url("options-general.php?page=amrod_api"));
    } elseif ($statusCode === 200) {
        // Save the response to a file
        file_put_contents(AMROD_DATA_RESPONSE, $responseBody["body"]);
    }
}

function amrodFetchPrices()
{
   $header= array (
    "Authorization" => "Bearer " . get_option(AMROD_TOKEN_KEY)
   );
   $response = wp_remote_get(AMROD_GET_PRICES, [
       "headers" => $header,
   ]);
   if(is_wp_error($response) === false){
         $responseBody = json_decode($response["body"]);
         // Save the response to a file

         foreach($responseBody as $item){
             try{
	             $productId = wc_get_product_id_by_sku($item->simplecode);
	             $product = wc_get_product($productId);

	             $newPrice = (string)(40 * $item->price) / 100;
                 if($product !== false){
	                 var_dump($product->get_name('view'));
	                 $product->set_sale_price($item->price);
                     $product->set_regular_price($newPrice);
	                 $product->save();
	                 // Print to command line that price is saved
	                 fwrite(STDOUT, "Price for {$item->simplecode} has been updated to {$newPrice}\n");
                 }

             }
             catch(Exception $e){
                    fwrite(STDOUT, "Failed {$e->getMessage()}\n");
             }


         }
   }
}

/**
 * @throws WC_Data_Exception
 */
function amrodProcessResponse()
{
    // Load the Amrod JSON Data file
    if (file_exists(AMROD_DATA_RESPONSE)) {
        // Convert to Woocommerce product
        $amrodData = json_decode(file_get_contents(AMROD_DATA_RESPONSE));
        // Loop through the data and create a product

        foreach ($amrodData as $productObject) {
            // Set the Product information
            $product = (array) $productObject;
            // Check that the product already exists
            $productExists = wc_get_product_id_by_sku($product["simpleCode"]);
            if ($productExists) {
                continue;
            }
            $variantWCProduct = new WC_Product_Simple();
            $variantWCProduct->set_name($product["productName"]);
            $variantWCProduct->set_sku($product["simpleCode"]);
            $variantWCProduct->set_description($product["description"]);
            $variantWCProduct->set_stock_quantity($product["minimum"]);
            $parentId = $variantWCProduct->save();
            // Create the category for the product
            $category = $product["categories"][0];
            $term = term_exists($category->name, "product_cat");
            if ($term !== 0 && $term !== null) {
                $termId = $term["term_id"];
            } else {
                $termId = wp_insert_term($category->name, "product_cat");
            }
            $variantWCProduct->set_category_ids([$termId]);

            // Set the images for the product
            $image = $product["images"][0];
            // Download the image and set it as the product image
            $upload_dir = wp_upload_dir();
            $filename = download_url($image->urls[0]->url);
            if (is_wp_error($filename) === false) {
                $imageId = media_handle_sideload(
                    [
                        "tmp_name" => $filename,
                        "name" => $image->name . ".jpg",
                    ],
                    $parentId
                );
                set_post_thumbnail($parentId, $imageId);
                $variantWCProduct->set_image_id($imageId);
            }

            // Save the Data
            $variantWCProduct->save();

            // Set the variation for each of the Products
            $subVariant = new WC_Product_Variation();
            $variants = $product["variants"];
            foreach ($variants as $variantObject) {
                $variant = (array) $variantObject;

                $subVariant->set_weight($variant["productDimension"]->weight);
                $subVariant->set_length($variant["productDimension"]->length);
                $subVariant->set_width($variant["productDimension"]->width);
                $subVariant->set_manage_stock(true);
                $subVariant->set_stock_status("instock");
                $subVariant->set_backorders("no");
                $subVariant->set_category_ids([$termId]);
                $subVariant->set_parent_id($parentId);
                // Set Attributes for the Variant
                $subVariant->set_attributes([
                    "Carton Size Length" =>
                        $variant["packagingAndDimension"]->cartonSizeDimensionL,
                    "Carton Size Width" =>
                        $variant["packagingAndDimension"]->cartonSizeDimensionW,
                    "Carton Size Height" =>
                        $variant["packagingAndDimension"]->cartonSizeDimensionH,
                    "Pieces Per Carton" =>
                        $variant["packagingAndDimension"]->piecesPerCarton,
                    "Carton Weight" =>
                        $variant["packagingAndDimension"]->cartonWeight,
                ]);
                $subVariant->save();
            }
	        fwrite(STDOUT, "Price for {$variantWCProduct->get_name('view')} has been saved \n");
        }
    }
}

function renderToken()
{
    $token = get_option(AMROD_TOKEN_KEY);
    echo $token
        ? "<h3>Amrod Token has been set</h3><hr/>"
        : "<h3>Token does not exist</h3><hr/>";
    echo "<br/>";
    if (!$token) {
        echo "<a class='button button-primary' href='?page=amrod_api&amrod_mode=authenticate'> Authenicate</a>";
        if (
            isset($_GET["amrod_mode"]) &&
            $_GET["amrod_mode"] === "authenticate"
        ) {
            // Debug the GET from the Request
            amrodLogin();
        }
    } else {
        echo "<a class='button button-primary' href='?page=amrod_api&amrod_mode=fetch-product'> Fetch Product from Amrod</a>";
        if (
            isset($_GET["amrod_mode"]) &&
            $_GET["amrod_mode"] === "fetch-product"
        ) {
            // Debug the GET from the Request
            amrodFetchProduct();
        }
    }
    echo "<a class='button button-primary' href='?page=amrod_api&amrod_mode=view-product'> View Amrod Product</a>";
    if (isset($_GET["amrod_mode"]) && $_GET["amrod_mode"] === "view-product") {
        // Debug the GET from the Request
        amrodProcessResponse();
    }
}

function register_settings()
{
    register_setting("amrod_settings", "amrod_option_page");
    add_settings_section(
        "api_settings",
        "API Settings",
        function () {
            // Check if the token exists
            renderToken();
        },
        "amrod_page"
    );

    add_settings_field(
        "amrod_username",
        "Amrod Username",
        "render_settings",
        "amrod_page",
        "api_settings",
        "username"
    );
    add_settings_field(
        "anrod_password",
        "Amrod Password",
        "render_settings",
        "amrod_page",
        "api_settings",
        "password"
    );
    add_settings_field(
        "anrod_customercode",
        "Amrod Customer Code",
        "render_settings",
        "amrod_page",
        "api_settings",
        "customercode"
    );
    add_settings_field(
        "amrod_endpoint",
        "Amrod Endpoint",
        "render_settings",
        "amrod_page",
        "api_settings",
        "endpoint"
    );
}

function render_section()
{
    echo "<p>Intro text for our settings section</p>";
}

// ------------------------------------------------------------------
// Callback function for our example setting
// ------------------------------------------------------------------
//
// creates a checkbox true/false option. Other types are surely possible
//

function render_settings($args)
{
    $smallLabelText = "<small>Enter the {$args} for the Amroid API</small>";
    switch ($args) {
        case "endpoint":
        case "customercode":
        case "username": ?><input type="text" value="<?= get_option(
    "amrod_option_page"
)[$args] ?? "" ?>"
                     name="<?= "amrod_option_page[{$args}]" ?>"/><br/>
            <small><?= $smallLabelText ?></small><?php break;case "password": ?><input type="password" value="<?= get_option(
    "amrod_option_page"
)["password"] ?>"
                     name="amrod_option_page[password]"/>
            <br/>
            <small><?= $smallLabelText ?></small><?php break;}
}

function tokenExpired()
{
    ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e(
            "Amrod Token has expired, kindly re-authenticate!",
            "amrod-api"
        ); ?></p>
    </div>
	<?php
}

// Add to the Administration menu

add_action("admin_menu", "render_option_page");
add_action("admin_init", "register_settings");

function setup_cronjob()
{
    if (!wp_next_scheduled("amrod_process_response")) {
        wp_schedule_event(time(), "weekly", "amrod_process_response");
    }

    if (!wp_next_scheduled("amrod_fetch_product")) {
        wp_schedule_event(time(), "weekly", "amrod_fetch_product");
    }

    if (!wp_next_scheduled("amrod_fetch_prices")) {
        wp_schedule_event(time(), "weekly", "amrod_fetch_prices");
    }
}

function clean_hooks()
{
    wp_clear_scheduled_hook("amrod_process_response");
    wp_clear_scheduled_hook("amrod_fetch_product");
    wp_clear_scheduled_hook("amrod_fetch_prices");
}

register_activation_hook(__FILE__, "setup_cronjob");
register_deactivation_hook(__FILE__, "clean_hooks");

add_action("amrod_fetch_product", "amrodFetchProduct");
add_action("amrod_process_response", "amrodProcessResponse");
add_action("amrod_fetch_prices", "amrodFetchPrices");
