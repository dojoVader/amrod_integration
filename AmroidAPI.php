<?php

/**
 * Class AmroidAPI
 *
 * A lightweight HTTP client that wraps all authenticated calls to the Amrod
 * Vendor REST API (https://vendorapi.amrod.co.za). Each public method targets
 * a specific API resource and returns the raw JSON response body as a string,
 * which callers are responsible for decoding.
 *
 * Authentication is performed via a Bearer token that must be obtained
 * beforehand (see `amrodLogin()` in the main plugin file) and passed to the
 * constructor. The token is attached as an `Authorization` header on every
 * request.
 */
class AmroidAPI {
	/** @var string Bearer token used to authenticate every API request. */
	private $token;

	/**
	 * AmroidAPI constructor.
	 *
	 * @param string $token A valid Amrod API bearer token.
	 */
	public function __construct($token){
		$this->token = $token;
	}

	/**
	 * Placeholder for re-authenticating with the Amrod API.
	 *
	 * @return void
	 */
	public function authenticate(){

	}

	/**
	 * Retrieves the full product category hierarchy from the Amrod API.
	 *
	 * Calls `GET /api/v1/Categories/` and returns the raw JSON response body
	 * containing a nested tree of category objects.
	 *
	 * @return string Raw JSON response body.
	 */
	public function getCategories(){
		$url = 'https://vendorapi.amrod.co.za/api/v1/Categories/';
		return $this->get($url);
	}

	/**
	 * Retrieves all products together with their branding information.
	 *
	 * Calls `GET /api/v1/Products/GetProductsAndBranding` and returns the raw
	 * JSON response body containing an array of product objects (including
	 * variants, images, colour images, branding templates, and branding positions).
	 *
	 * @return string Raw JSON response body.
	 */
	public function getProducts(){
		$url = 'https://vendorapi.amrod.co.za/api/v1/Products/GetProductsAndBranding';
		return $this->get($url);
	}

	/**
	 * Retrieves current stock levels for all products.
	 *
	 * Calls `GET /api/v1/Stock/` and returns the raw JSON response body
	 * containing an array of stock objects (simpleCode, fullCode, stock).
	 *
	 * @return string Raw JSON response body.
	 */
	public function getStocks(){
		$url = 'https://vendorapi.amrod.co.za/api/v1/Stock/';
		return $this->get($url);
	}

	/**
	 * Retrieves current prices for all products.
	 *
	 * Calls `GET /api/v1/Prices/` and returns the raw JSON response body
	 * containing an array of price objects (simplecode, fullCode, price).
	 *
	 * @return string Raw JSON response body.
	 */
	public function getPrices(){
		$url = 'https://vendorapi.amrod.co.za/api/v1/Prices/';
		return $this->get($url);
	}

	/**
	 * Retrieves branding/decoration method pricing from the Amrod API.
	 *
	 * Calls `GET /api/v1/BrandingPrices/` and returns the raw JSON response
	 * body containing an array of branding price objects (brandingMethod,
	 * brandingCode, data).
	 *
	 * @return string Raw JSON response body.
	 */
	public function getBrandingPrice(){
		$url = 'https://vendorapi.amrod.co.za/api/v1/BrandingPrices/';
		return $this->get($url);
	}

	/**
	 * Executes an authenticated GET request to the given URL.
	 *
	 * Attaches the stored bearer token as the `Authorization` header and uses
	 * the WordPress `wp_remote_get()` helper to perform the request.
	 *
	 * @param  string $url The fully-qualified API endpoint URL.
	 * @return string      The raw response body string.
	 */
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