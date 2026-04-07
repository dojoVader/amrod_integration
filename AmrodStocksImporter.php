<?php

/**
 * Class AmrodStocksImporter
 *
 * Handles synchronising stock quantities fetched from the Amrod API into
 * WooCommerce. For each stock record returned by the API, this importer
 * locates the matching WooCommerce product by SKU and updates its managed
 * stock quantity. A product-level meta flag (`stock-{fullCode}`) prevents a
 * product from being updated more than once per sync cycle.
 */
class AmrodStocksImporter {

	/** @var object|null The raw stock object received from the Amrod API. */
	private $productObject = null;

	/**
	 * AmrodStocksImporter constructor.
	 *
	 * @param object $product A single stock record from the Amrod Stock API
	 *                        response. Expected properties: simpleCode, fullCode, stock.
	 */
	public function __construct($product){
		$this->productObject = $product;
	}

	/**
	 * Executes the stock-quantity update for the associated product.
	 *
	 * Locates the WooCommerce product by its simple code (SKU). If found and
	 * not already updated (checked via meta flag), it sets the product's
	 * stock quantity and marks it as updated to avoid duplicate processing.
	 *
	 * @return void
	 */
	public function handle(){
		$productId = wc_get_product_id_by_sku($this->productObject->simpleCode);
		if($productId){

			$product = wc_get_product($productId);
			$fullCode = $this->productObject->fullCode;
			$metadata = $product->get_meta("stock-${fullCode}");
			var_dump($product->get_meta_data());
			if($metadata === 'true'){
				fwrite(STDOUT, "Stock for {$this->productObject->simpleCode} has already been updated \n");
				return;
			}
			$stock = (int) $this->productObject->stock;
			$product->set_stock_quantity($stock);
			fwrite(STDOUT, "Stock for {$this->productObject->simpleCode} has been updated to {$stock} \n");

			$product->update_meta_data("stock-${fullCode}", 'true');
			$product->save();
		}

	}

}