<?php

/**
 * Class AmrodBrandingImporter
 *
 * Handles persisting branding/decoration pricing data fetched from the Amrod
 * API into the custom `{prefix}_amrod_brand_pricing` database table. Each
 * record represents a specific branding method (e.g. embroidery, screen
 * printing) with its associated pricing data stored as JSON. The table is
 * created on plugin activation via `create_brand_database()`.
 */
class AmrodBrandingImporter {

	/** @var array|null The branding record received from the Amrod API, cast to an array. */
	private $brandData = null;

	/**
	 * AmrodBrandingImporter constructor.
	 *
	 * @param object $brand A single branding pricing record from the Amrod
	 *                      BrandingPrices API response. Expected properties:
	 *                      brandingMethod, brandingCode, data.
	 */
	public function __construct($brand){
		$this->brandData = (array) $brand;
	}

	/**
	 * Persists the branding record into the custom database table.
	 *
	 * Inserts a row containing the branding method, branding code, and
	 * JSON-encoded pricing data into the `{prefix}_amrod_brand_pricing` table.
	 *
	 * @return void
	 */
	public function handle(){
		global $wpdb;
		$table_name = $wpdb->prefix . 'amrod_brand_pricing';

		// Insert branding data
		$recordStatus = $wpdb->insert($table_name,[
			'brand_method' => $this->brandData['brandingMethod'],
			'brand_code' => $this->brandData['brandingCode'],
			'data' => json_encode($this->brandData['data']),
		]);
		if($recordStatus){
			fwrite(STDOUT, "Branding data inserted successfully\n");
		}


	}

}