<?php

class AmrodBrandingImporter {

	private $brandData = null;

	public function __construct($brand){
		$this->brandData = (array) $brand;
	}

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