<?php

/**
 * Class AmrodCategoryImporter
 *
 * Builds a hierarchical WooCommerce product category tree from a slash-delimited
 * category path string (e.g. "Clothing/T-Shirts/Polo") and assigns the
 * deepest resulting category to a WooCommerce product identified by its SKU.
 *
 * This class is used during the product import process when a product carries
 * category path metadata from the Amrod API. It walks each segment of the path
 * top-to-bottom, creating any missing `product_cat` terms as nested children,
 * and finally associates the product with the leaf category term.
 */
class AmrodCategoryImporter {

	/** @var int|null The term ID of the last (deepest) category processed. */
	private $lastCategoryId = null;

	/** @var string|null The SKU of the product to be assigned to the resolved category. */
	private $productSku = null;

	/**
	 * AmrodCategoryImporter constructor.
	 *
	 * @param string $sku The SKU (simple code) of the WooCommerce product that
	 *                    will be assigned to the resolved category.
	 */
	public function __construct( $sku ) {
		$this->productSku = $sku;
	}

	/**
	 * Returns the term ID of the deepest category resolved during the last
	 * call to {@see processPath()}.
	 *
	 * @return int|null The WooCommerce term ID, or null if no path has been processed yet.
	 */
	public function getLastCategoryId() {
		return $this->lastCategoryId;
	}

	/**
	 * Processes a slash-delimited category path and creates any missing
	 * WooCommerce `product_cat` terms before assigning the product.
	 *
	 * Example path: "Bags/Laptop Bags/Backpacks"
	 *
	 * @param string $path Slash-delimited category path from the Amrod API.
	 * @return void
	 */
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

	/**
	 * Iterates over the category path segments, creating or reusing
	 * `product_cat` terms for each level and tracking the deepest term ID.
	 * Calls {@see assignProduct()} after all segments are processed.
	 *
	 * @param  array $categories Ordered array of category name segments.
	 * @return void
	 */
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


	/**
	 * Assigns the WooCommerce product (identified by {@see $productSku}) to
	 * the deepest category resolved during path processing.
	 *
	 * @return void
	 */
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

	/**
	 * Checks whether a WooCommerce `product_cat` term with the given name
	 * already exists, optionally scoped to the last resolved parent category.
	 *
	 * @param  string $category_name The category name to look up.
	 * @param  bool   $hasParent     When true, the lookup is scoped to
	 *                               {@see $lastCategoryId} as the parent term.
	 * @return array|null            Term data array on match, or null if not found.
	 */
	public function hasCategory( $category_name, $hasParent = false ) {
		if ( ! $hasParent ) {
			return term_exists( $category_name, 'product_cat' );
		} else {
			return term_exists( $category_name, 'product_cat', $this->lastCategoryId );
		}

	}


}

/**
 * Class AmrodAPICategoryImporter
 *
 * Imports the full category tree returned by the Amrod Categories API
 * (`GET /api/v1/Categories/`) into WooCommerce `product_cat` taxonomy terms.
 *
 * Unlike {@see AmrodCategoryImporter} (which works from a flat path string),
 * this class accepts a structured category object with a `categoryName` field
 * and a `children` array, and recursively walks the tree — creating any
 * missing parent and child category terms while preserving the hierarchy.
 */
class AmrodAPICategoryImporter {

	/** @var array|null The root category object from the Amrod Categories API response. */
	private $categoryObject = null;

	/**
	 * AmrodAPICategoryImporter constructor.
	 *
	 * @param array $categoryObject A single top-level category record from the
	 *                              Amrod Categories API response. Expected keys:
	 *                              categoryName (string), children (array).
	 */
	public function __construct( $categoryObject ) {
		$this->categoryObject = $categoryObject;
	}

	/**
	 * Triggers the recursive category tree import starting from the root
	 * category object supplied at construction time.
	 *
	 * @return void
	 */
	public function handle() {

		$this->walkChildren( $this->categoryObject, null );
	}


	/**
	 * Recursively walks a category node and its children, creating
	 * WooCommerce `product_cat` terms as needed.
	 *
	 * When a parent ID is supplied the category is created (or skipped if
	 * already present) as a child of that term. At the root level the method
	 * reuses an existing top-level term if one matches the category name.
	 *
	 * @param  array    $category  The current category node. Expected keys:
	 *                             categoryName (string), children (array).
	 * @param  int|null $parent_id The WooCommerce term ID of the parent category,
	 *                             or null when processing the root node.
	 * @return void
	 */
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