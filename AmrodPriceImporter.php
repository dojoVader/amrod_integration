<?php

class AmrodPriceImporter {

	private $productObject = null;
	public function __construct($product){
		$this->productObject = $product;
	}

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