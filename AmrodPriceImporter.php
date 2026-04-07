<?php

/**
 * Class AmrodPriceImporter
 *
 * Handles synchronising product prices fetched from the Amrod API into
 * WooCommerce. For each price record returned by the API, this importer
 * looks up the matching WooCommerce product by SKU and updates its regular
 * price. A product-level meta flag (`regular-{fullCode}`) is used to prevent
 * a product from being updated more than once per sync cycle.
 */
class AmrodPriceImporter {

	/** @var object|null The raw price object received from the Amrod API. */
	private $productObject = null;

	/**
	 * AmrodPriceImporter constructor.
	 *
	 * @param object $product A single price record from the Amrod Prices API
	 *                        response. Expected properties: simplecode, fullCode, price.
	 */
	public function __construct($product){
		$this->productObject = $product;
	}

	/**
	 * Executes the price update for the associated product.
	 *
	 * Locates the WooCommerce product by its simple code (SKU). If found and
	 * not already updated (checked via meta flag), it sets the product's
	 * regular price and marks it as updated to avoid duplicate processing.
	 *
	 * @return void
	 */
	public function handle(){
		$productId = wc_get_product_id_by_sku($this->productObject->simplecode);
		if($productId){

			$product = wc_get_product($productId);
			$fullCode = $this->productObject->fullCode;
			$metadata = $product->get_meta("regular-${fullCode}");
			if($metadata === 'true'){
				fwrite(STDOUT, "Price for {$this->productObject->simplecode} has already been updated \n");
				return;
			}
			$price = (int) $this->productObject->price;
			$product->set_price($price);
			$product->set_regular_price($this->productObject->price);
			fwrite(STDOUT, "Price for {$this->productObject->simplecode} has been updated to {$price} \n");

			$product->update_meta_data("regular-${fullCode}", 'true');
			$product->delete_meta_data("price-${fullCode}");
			$product->save();
		}

	}

}