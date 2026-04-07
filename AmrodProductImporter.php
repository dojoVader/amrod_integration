<?php

require_once 'AmrodCategoryImporter.php';

/**
 * Class AmrodProductImporter
 *
 * Handles importing and updating WooCommerce products from data returned by
 * the Amrod Products API (`GET /api/v1/Products/GetProductsAndBranding`).
 *
 * Responsibilities are split between two public entry points:
 *
 * - {@see handleProductImport()}  – Creates or updates the WooCommerce simple
 *   product and its child variation records, along with categories, branding
 *   metadata, template URLs, colour-image references, and branding position
 *   information. A `completed` meta flag prevents reprocessing.
 *
 * - {@see handleProductImageImport()} – Downloads and attaches product images
 *   (main image + gallery) and category thumbnail images for an already-imported
 *   product. An `image_processed` meta flag prevents reprocessing.
 */
class AmrodProductImporter {

	/** @var int[] IDs of the gallery images uploaded for the current product. */
	private $galleryImageIds = [];

	/** @var int|null Attachment ID of the default (featured) product image. */
	private $activeImageId = null;

	/**
	 * Accumulated product meta data to be persisted on the WooCommerce product.
	 * Keys include branding guide URLs, template positions, colour image info,
	 * and branding position/method mappings.
	 *
	 * @var array
	 */
	private $metadata = [];

	/** @var object|null The raw product object received from the Amrod API. */
	private $productObject = null;

	/**
	 * AmrodProductImporter constructor.
	 *
	 * @param object $productObject A single product record from the Amrod
	 *                              Products API response.
	 */
	public function __construct( $productObject ) {
		$this->productObject = $productObject;
	}

	/**
	 * Downloads an image from the given URL and sets it as the thumbnail
	 * (category image) for the specified WooCommerce product category term.
	 *
	 * @param  string $image  Fully-qualified URL of the category image.
	 * @param  int    $termId The WooCommerce term ID of the product category.
	 * @return void
	 */
	private function setCategoryImage( $image, $termId ) {

		$filename = download_url( $image );

		if ( is_wp_error( $filename ) === false ) {


			$imageId = media_handle_sideload(
				[
					"tmp_name" => $filename,
					"name"     => basename( $image ),
				]
			);
			update_term_meta( $termId, 'thumbnail_id', $imageId );
			fwrite( STDOUT, "Category Image for {$termId} has been saved \n" );

		}


	}

	/**
	 * Downloads an image from the Amrod CDN and attaches it to a WooCommerce
	 * product post. If the image is marked as the default (`$isActive`), its
	 * attachment ID is stored as the featured image; otherwise it is appended
	 * to the gallery collection.
	 *
	 * @param  object $image     Image object from the API. Expected properties:
	 *                           urls (array of objects with `url`), name (string),
	 *                           isDefault (bool).
	 * @param  int    $parent_id The WooCommerce product post ID to attach the image to.
	 * @param  bool   $isActive  True if this image should be set as the featured image.
	 * @return void
	 */
	private function setProductImage( $image, $parent_id, $isActive = false ) {
		$upload_dir = wp_upload_dir();
		$filename   = download_url( $image->urls[0]->url );
		if ( is_wp_error( $filename ) === false ) {
			$imageId = media_handle_sideload(
				[
					"tmp_name" => $filename,
					"name"     => $image->name . ".jpg",
				],
				$parent_id
			);
			set_post_thumbnail( $parent_id, $imageId );

			if ( $isActive ) {
				$this->activeImageId = $imageId;
			} else {
				$this->galleryImageIds[] = $imageId;
			}


		}
		fwrite( STDOUT, "Product Image for {$parent_id} has been saved \n" );
	}

	/**
	 * Iterates over the accumulated {@see $metadata} array and persists each
	 * key/value pair as WooCommerce product meta data on the given product.
	 *
	 * @param  WC_Product $variantWCProduct The WooCommerce product object to update.
	 * @return void
	 */
	private function saveMeta( $variantWCProduct ) {
		foreach ( $this->metadata as $key => $value ) {
			$variantWCProduct->update_meta_data( $key, $value );
		}
	}
	/**
	 * Downloads and attaches images for an already-imported WooCommerce product.
	 *
	 * Looks up the product by SKU. If the `image_processed` meta is already set
	 * to `"done"`, the method returns early to prevent duplicate uploads. Otherwise
	 * it downloads each product image (setting the default one as the featured image
	 * and the rest as gallery images), downloads category thumbnail images, updates
	 * the category assignments, and marks the product as fully processed.
	 *
	 * @return void
	 */
	public function handleProductImageImport() {
		$product = (array) $this->productObject;
		// Check that the product already exists
		$productId = wc_get_product_id_by_sku($product["simpleCode"]);
		if($productId){
			$variantWCProduct = wc_get_product($productId);
			$metadata = $variantWCProduct->get_meta('image_processed');
			if($metadata === "done"){
				fwrite(STDOUT,"Image for {$variantWCProduct->get_sku()} has already been processed \n");
				return;
			}
			$parentId = $variantWCProduct->get_id();
			$categories = $product["categories"];
			$images     = $product['images'];
			// Set the images for the category
			$productCategories = [];
			foreach ( $categories as $category ) {
				$categoryPath = $category->path;

				$categoryImporter = new AmrodCategoryImporter( $product['simpleCode'] );
				$categoryImporter->processPath( $categoryPath );
				// Set the category image per product
				$this->setCategoryImage( $category->image, $categoryImporter->getLastCategoryId() );
				$productCategories[] = $categoryImporter->getLastCategoryId();
			}

			// Set the categories to the product
			$variantWCProduct->set_category_ids( $productCategories );

			foreach ( $images as $image ) {
				$isActiveImage = $image->isDefault === true;
				$this->setProductImage( $image, $parentId, $isActiveImage );
			}

			// Set the Product Image
			$variantWCProduct->set_image_id( $this->activeImageId );
			fwrite( STDOUT, "Product Image for {$variantWCProduct->get_sku()} has been saved \n" );
			// Set the Gallery for the product
			$variantWCProduct->set_gallery_image_ids( $this->galleryImageIds );
			$variantWCProduct->update_meta_data("image_processed","done");
			$variantWCProduct->save();

		}




	}



	/**
	 * Creates or updates a WooCommerce product and its variants from an Amrod
	 * product record.
	 *
	 * - If the product (matched by SKU / simple code) does not yet exist, a new
	 *   `WC_Product_Simple` is created with name, description, and zero initial
	 *   stock.
	 * - If the product already exists and its `completed` meta is `"done"`, the
	 *   method returns early to avoid reprocessing.
	 * - Category terms are created/reused via {@see AmrodCategoryImporter}.
	 * - Each variant from the API is imported as a `WC_Product_Variation` with
	 *   physical dimensions, packaging attributes, and stock settings.
	 * - Branding guide URLs, template positions, colour image references, and
	 *   branding position/method mappings are stored as product meta data.
	 * - On completion the `completed` meta is set to `"done"`.
	 *
	 * @return void
	 * @throws WC_Data_Exception If setting product data fails.
	 */
	public function handleProductImport() {
		// Set the Product information
		$product = (array) $this->productObject;
		// Check that the product already exists
		$productExists = wc_get_product_id_by_sku( $product["simpleCode"] );


		// Check the product already exists in the Database
		if ( $productExists !== 0 ) {
			$variantWCProduct = wc_get_product( $productExists );
			// Get the Metadata so we don't process already processed code
			if ( $variantWCProduct !== false ) {
				$metadata = $variantWCProduct->get_meta( 'completed' );
				// If the Category is already set then continue

				if ( $metadata === "done" ) {
					fwrite( STDOUT, "Product for {$variantWCProduct->get_sku()} has already been processed \n" );
					return;
				}


			}

			// Check the completed status in the metadata
			$variantWCProduct->set_short_description( $product["description"] );

		} else {
			$variantWCProduct = new WC_Product_Simple();
			$variantWCProduct->set_sku( $product["simpleCode"] );
			$variantWCProduct->set_name( $product["productName"] );
			$variantWCProduct->set_short_description( $product["description"] );
			$variantWCProduct->set_description( $product["description"] );
			$variantWCProduct->set_stock_quantity( 0 );
		}


		//Set the branding guideline

		$this->metadata['full'] = $product['fullBrandingGuide'];
		//Find all the branding guides lines
		if ( $product['brandingTemplates'] !== null ) {
			foreach ( $product['brandingTemplates'] as $key => $template ) {
				$this->metadata[ "template_" . $template->position ] = $template->url;
			}
		}

		if ( $product['colourImages'] !== null ) {
			foreach ( $product['colourImages'] as $key => $color ) {
				if ( count( $color->images ) > 0 ) {
					$this->metadata[ $color->name ] = [
						'name'       => $color->name,
						'image_name' => $color->images[0]->name,
						'url'        => $color->images[0]->urls[0]->url
					];
				} else {
					continue;
				}

			}
		}

		if ( $product['brandings'] !== null ) {
			foreach ( $product['brandings'] as $key => $branding ) {
				$this->metadata[ "position_" . $branding->positionCode ] = [ 'methods' => [] ];
				foreach ( $branding->method as $method ) {
					$this->metadata[ "position_" . $branding->positionCode ]['methods'][] = $method->brandingName;
				}

			}
		}




		// Reuse the existing ID if the ID already exists
		if ($variantWCProduct ) {
			$parentId = $variantWCProduct->save();
		} else {
			$parentId = $variantWCProduct->get_id();
		}

		// Create the category for the product
		$categories = $product["categories"];
		$images     = $product['images'];
		//Handle the categories
		$productCategories = [];
		foreach ( $categories as $category ) {
			$categoryPath = $category->path;

			$categoryImporter = new AmrodCategoryImporter( $product['simpleCode'] );
			$categoryImporter->processPath( $categoryPath );
			// Set the category image per product
			//$this->setCategoryImage( $category->image, $categoryImporter->getLastCategoryId() );
			$productCategories[] = $categoryImporter->getLastCategoryId();
		}

		// Set the categories to the product
		$variantWCProduct->set_category_ids( $productCategories );





		// Save the Data
		$variantWCProduct->save();
		// Set the variation for each of the Products
		$subVariant = new WC_Product_Variation();
		$variants   = $product["variants"];
		foreach ( $variants as $variantObject ) {
			$variant = (array) $variantObject;

			// If the variant already exists just fetch it

			$variantExists = wc_get_product_id_by_sku( $variant["simpleCode"] );
			if ( $variantExists !== null ) {
				$subVariant = wc_get_product( $variantExists );
			} else {
				$subVariant = new WC_Product_Variation();
				$subVariant->set_sku( $variant['simpleCode'] );
			}

			$subVariant->set_weight( $variant["productDimension"]->weight );
			$subVariant->set_length( $variant["productDimension"]->length );
			$subVariant->set_width( $variant["productDimension"]->width );
			$subVariant->set_manage_stock( true );
			$subVariant->set_stock_status( "instock" );
			$subVariant->set_backorders( "no" );
			$subVariant->set_parent_id( $parentId );

			// Set Attributes for the Variant
			$subVariant->set_attributes( [
				"Carton Size Length" =>
					$variant["packagingAndDimension"]->cartonSizeDimensionL,
				"Carton Size Width"  =>
					$variant["packagingAndDimension"]->cartonSizeDimensionW,
				"Carton Size Height" =>
					$variant["packagingAndDimension"]->cartonSizeDimensionH,
				"Pieces Per Carton"  =>
					$variant["packagingAndDimension"]->piecesPerCarton,
				"Carton Weight"      =>
					$variant["packagingAndDimension"]->cartonWeight,
				"length"             => $variant["productDimension"]->length,
				"width"              => $variant["productDimension"]->width,
				"weight"             => $variant["productDimension"]->weight,
			] );


			$subVariant->save();
		}
		$this->metadata['completed'] = "done";
		$variantWCProduct->set_meta_data( $this->metadata );
		$this->saveMeta( $variantWCProduct );
		$variantWCProduct->save_meta_data();
		$variantWCProduct->save();
		fwrite( STDOUT, "Product for {$variantWCProduct->get_sku()} has been saved \n" );
	}


}