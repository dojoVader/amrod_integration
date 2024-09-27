<?php

class AmrodStocksImporter {

	private $productObject = null;
	public function __construct($product){
		$this->productObject = $product;
	}

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