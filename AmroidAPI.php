<?php




class AmroidAPI {
	private $token;


	public function __construct($token){
		$this->token = $token;
	}

	public function authenticate(){

	}

	public function getCategories(){
		$url = 'https://vendorapi.amrod.co.za/api/v1/Categories/';
		return $this->get($url);
	}

	public function getProducts(){
		$url = 'https://vendorapi.amrod.co.za/api/v1/Products/GetProductsAndBranding';
		return $this->get($url);
	}
	public function getStocks(){
		$url = 'https://vendorapi.amrod.co.za/api/v1/Stock/';
		return $this->get($url);
	}

	public function getPrices(){
		$url = 'https://vendorapi.amrod.co.za/api/v1/Prices/';
		return $this->get($url);
	}

	public function getBrandingPrice(){
		$url = 'https://vendorapi.amrod.co.za/api/v1/BrandingPrices/';
		return $this->get($url);
	}

	private function get($url){

		$header = [
			"Authorization" => "Bearer " . $this->token,
		];
		$responseBody = wp_remote_get($url, [
			"headers" => $header,
		]);

		return $responseBody['body'];
	}

}