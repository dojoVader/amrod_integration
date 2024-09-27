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
require(__DIR__.DIRECTORY_SEPARATOR.'AmrodCategoryImporter.php');
require(__DIR__.DIRECTORY_SEPARATOR.'AmroidAPI.php');
require(__DIR__.DIRECTORY_SEPARATOR.'AmrodProductImporter.php');
require(__DIR__.DIRECTORY_SEPARATOR.'AmrodStocksImporter.php');
require(__DIR__.DIRECTORY_SEPARATOR.'AmrodPriceImporter.php');
require(__DIR__.DIRECTORY_SEPARATOR.'AmrodBrandingImporter.php');


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
	$token = get_option(AMROD_TOKEN_KEY);
	if($token === null){
		throw new \Exception("Token is not authorized");
	}
	$amrodAPI = new AmroidAPI($token);
	$response = $amrodAPI->getPrices();
	$amrodData = json_decode($response);
	// Loop through the data and create a product

	foreach ($amrodData as $productObject) {
		$productImporter = new AmrodPriceImporter($productObject);
		$productImporter->handle();
	}
}

/**
 * @throws Exception
 */
function amrodCategories(){
	    $token = get_option(AMROD_TOKEN_KEY);
        if($token === null){
            throw new \Exception("Token is not authorized");
        }
        $amrodAPI = new AmroidAPI($token);
        $response = $amrodAPI->getCategories();
		$amrodData = json_decode($response);
       // fwrite(STDOUT,"Response has been fetched... processing....");
		foreach ($amrodData as $categoryObject) {
			$category = (array) $categoryObject;
            $importer = new AmrodAPICategoryImporter($category);
            $importer->handle();


		}
//        foreach ($amrodData as $productObject) {
//			$product = (array) $productObject;
//            $importer = new AmrodCategoryImporter($product["simpleCode"]);
//            $importer->processPath($product['categories'][0]->path);
//			//fwrite(STDOUT, "Category for {$product['categories'][0]->name} has been processed \n");
//
//		}

}

/**
 * @throws WC_Data_Exception
 */
function amrodProductImport()
{
    // Load the Amrod JSON Data file

        $token = get_option(AMROD_TOKEN_KEY);
        if($token === null){
            throw new \Exception("Token is not authorized");
        }
        $amrodAPI = new AmroidAPI($token);
        $response = $amrodAPI->getProducts();
        $amrodData = json_decode($response);
        // Loop through the data and create a product

        foreach ($amrodData as $productObject) {
	        $productImporter = new AmrodProductImporter($productObject);
            $productImporter->handleProductImport();
        }

}

function amrodProcessStocks(){
	$token = get_option(AMROD_TOKEN_KEY);
	if($token === null){
		throw new \Exception("Token is not authorized");
	}
	$amrodAPI = new AmroidAPI($token);
	$response = $amrodAPI->getStocks();
	$amrodData = json_decode($response);
	// Loop through the data and create a product

	foreach ($amrodData as $productObject) {
		$productImporter = new AmrodStocksImporter($productObject);
		$productImporter->handle();
	}
}

function amrodProductImageImport()
{
	// Load the Amrod JSON Data file

	$token = get_option(AMROD_TOKEN_KEY);
	if($token === null){
		throw new \Exception("Token is not authorized");
	}
	$amrodAPI = new AmroidAPI($token);
	$response = $amrodAPI->getProducts();
	$amrodData = json_decode($response);
	// Loop through the data and create a product

	foreach ($amrodData as $productObject) {
		$productImporter = new AmrodProductImporter($productObject);
		$productImporter->handleProductImageImport();
	}

}

function amrodProcessBrands()
{
	// Load the Amrod JSON Data file

	$token = get_option(AMROD_TOKEN_KEY);
	if($token === null){
		throw new \Exception("Token is not authorized");
	}
	$amrodAPI = new AmroidAPI($token);
	$response = $amrodAPI->getBrandingPrice();
	$amrodData = json_decode($response);
	// Loop through the data and create a product

	foreach ($amrodData as $branding) {
		$productImporter = new AmrodBrandingImporter($branding);
		$productImporter->handle();
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
	    echo "<a class='button button-primary' href='?page=amrod_api&amrod_mode=categories'> Process Categories</a>";
	    if (
		    isset($_GET["amrod_mode"]) &&
		    $_GET["amrod_mode"] === "categories"
	    ) {
		    // Debug the GET from the Request
		    amrodCategories();
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

function create_brand_database(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'amrod_brand_pricing';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        brand_method varchar(100) NOT NULL,
        brand_code varchar(100) NOT NULL,
        data longtext NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

function setup_cronjob()
{
    if (!wp_next_scheduled("amrod_product_import")) {
        wp_schedule_event(time(), "weekly", "amrod_product_import");
    }
	if (!wp_next_scheduled("amrod_image_import")) {
		wp_schedule_event(time(), "weekly", "amrod_image_import");
	}

    if (!wp_next_scheduled("amrod_fetch_product")) {
        wp_schedule_event(time(), "weekly", "amrod_fetch_product");
    }

    if (!wp_next_scheduled("amrod_fetch_prices")) {
        wp_schedule_event(time(), "weekly", "amrod_fetch_prices");
    }

	if (!wp_next_scheduled("amrod_process_categories")) {
		wp_schedule_event(time(), "weekly", "amrod_process_categories");
	}
	if (!wp_next_scheduled("amrod_process_stocks")) {
		wp_schedule_event(time(), "weekly", "amrod_process_stocks");
	}

    if (!wp_next_scheduled("amrod_process_brands")) {
		wp_schedule_event(time(), "weekly", "amrod_process_brands");
	}
    
    

    create_brand_database();

}

function clean_hooks()
{
    wp_clear_scheduled_hook("amrod_process_response");
    wp_clear_scheduled_hook("amrod_fetch_product");
    wp_clear_scheduled_hook("amrod_fetch_prices");
    wp_clear_scheduled_hook("amrod_product_import");
    wp_clear_scheduled_hook("amrod_image_import");
    wp_clear_scheduled_hook("amrod_process_stocks");
    wp_clear_scheduled_hook("amrod_process_brands");
}

register_activation_hook(__FILE__, "setup_cronjob");
register_deactivation_hook(__FILE__, "clean_hooks");

add_action("amrod_fetch_product", "amrodFetchProduct");
add_action("amrod_product_import", "amrodProductImport");
add_action("amrod_image_import", "amrodProductImageImport");
add_action("amrod_fetch_prices", "amrodFetchPrices");
add_action("amrod_process_categories", "amrodCategories");
add_action("amrod_process_stocks", "amrodProcessStocks");
add_action("amrod_process_brands", "amrodProcessBrands");
