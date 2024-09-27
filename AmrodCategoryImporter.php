<?php

class AmrodCategoryImporter {

	private $lastCategoryId = null;
	private $productSku = null;

	public function __construct( $sku ) {
		$this->productSku = $sku;
	}

	public function getLastCategoryId() {
		return $this->lastCategoryId;
	}

	public function processPath( string $path ) {
		$splitPaths = explode( "/", $path );
		if ( count( $splitPaths ) > 0 ) {
			try {
				$this->handleCategorize( $splitPaths );
			} catch ( Exception $e ) {
				fwrite( STDOUT, $e->getTraceAsString() );
			}

		}
	}

	private function handleCategorize( array $categories ) {
		$count = count( $categories );
		foreach ( $categories as $index => $categoryFound ) {
			$category = ucwords( $categoryFound );

			if ( $this->lastCategoryId === null ) {
				// This is the first top level
				$existingTerm = $this->hasCategory( $category );
			} else {
				$term         = $this->hasCategory( $category, true );
				$existingTerm = $term;
			}


			if ( $existingTerm === null ) {
				if ( $index === 0 ) {
					$termId               = wp_insert_term( $category, 'product_cat' );
					$this->lastCategoryId = $termId['term_id'];
				} else {
					if ( $index !== $count ) {
						$termId               = wp_insert_term( $category, 'product_cat', [ 'parent' => $this->lastCategoryId ] );
						$this->lastCategoryId = $termId['term_id'];
					}

				}

			} else {
				$termId = (int) $existingTerm['term_id'];

				if ( $index < $count ) {
					$this->lastCategoryId = $termId;

				}

			}
		}
		$this->assignProduct();
	}


	private function assignProduct() {
		try {
			$productId     = wc_get_product_id_by_sku( $this->productSku );
			$simpleProduct = wc_get_product( $productId );
			if ( $simpleProduct !== false ) {
				$simpleProduct->set_category_ids( [ $this->lastCategoryId ] );
				$simpleProduct->save();
			}

		} catch ( Exception $e ) {
			// fwrite( STDOUT, $e->getTraceAsString() );
		}

	}

	public function hasCategory( $category_name, $hasParent = false ) {
		if ( ! $hasParent ) {
			return term_exists( $category_name, 'product_cat' );
		} else {
			return term_exists( $category_name, 'product_cat', $this->lastCategoryId );
		}

	}


}

class AmrodAPICategoryImporter {

	private $categoryObject = null;

	public function __construct( $categoryObject ) {
		$this->categoryObject = $categoryObject;
	}

	public function handle() {

		$this->walkChildren( $this->categoryObject, null );
	}


	private function walkChildren( $category, $parent_id = null ) {
		$children_count = count( $category['children'] );
		if ( $parent_id !== null ) {
			$termExist = term_exists( $category['categoryName'], $parent_id );
			if ( $termExist === null ) {
				$term   = wp_insert_term( $category['categoryName'], 'product_cat', [ 'parent' => $parent_id ] );
				$termId = $term['term_id'];
				if ( $children_count > 0 ) {
					foreach ( $category['children'] as $subcategories ) {
						$subCategory = (array) $subcategories;
						$this->walkChildren( $subCategory, $termId );
					}
				}
			}
		} else {
			$categoryExists = term_exists( $this->categoryObject['categoryName'], 'product_cat' );
			if ( $categoryExists !== null ) {
				$termId = $categoryExists['term_id'];
			} else {
				$term   = wp_insert_term( $this->categoryObject['categoryName'], 'product_cat' );
				$termId = $term['term_id'];
			}
			if ( $children_count > 0 ) {
				foreach ( $category['children'] as $subcategories ) {
					$subCategory = (array) $subcategories;
					$this->walkChildren( $subCategory, $termId );
				}
			}

		}
	}
}