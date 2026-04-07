# Amrod Integration for WooCommerce

A WordPress plugin that integrates the [Amrod](https://www.amrod.co.za) vendor API with WooCommerce, enabling automated synchronisation of products, categories, prices, stock levels, and branding data.

## Features

- **Authentication** – Authenticate with the Amrod Vendor API using your username, password, and customer code.
- **Product Import** – Create or update WooCommerce simple products with variants, attributes, descriptions, and branding metadata.
- **Image Import** – Download and attach product images and category thumbnails from Amrod.
- **Category Import** – Build a full hierarchical WooCommerce product category tree from Amrod category data.
- **Price Sync** – Update regular prices on existing WooCommerce products from the Amrod price list.
- **Stock Sync** – Update stock quantities on existing WooCommerce products from Amrod stock data.
- **Branding Pricing** – Store Amrod branding/decoration method pricing in a dedicated database table.
- **Scheduled Automation** – All sync operations are scheduled as weekly WP-Cron jobs that run automatically.

## Requirements

| Requirement | Minimum version |
|---|---|
| WordPress | 5.2 |
| PHP | 7.2 |
| WooCommerce | Any compatible version |

## Installation

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings → Amrod API** to configure your credentials.

## Configuration

Go to **Settings → Amrod API** and fill in the following fields:

| Field | Description |
|---|---|
| **Amrod Username** | Your Amrod vendor account username |
| **Amrod Password** | Your Amrod vendor account password |
| **Amrod Customer Code** | Your Amrod customer code |
| **Amrod Endpoint** | The authentication API endpoint provided by Amrod |

After saving, click **Authenticate** to obtain and store a bearer token. The token status is displayed at the top of the settings page.

## Usage

### Manual Triggers (Admin UI)

Once authenticated, the settings page exposes the following action buttons:

| Button | Action |
|---|---|
| **Fetch Product from Amrod** | Fetches the full product + branding dataset and caches it locally |
| **Process Categories** | Imports all product categories from the Amrod API into WooCommerce |

### Automated Cron Jobs

The following WordPress cron events are registered on plugin activation and run **weekly**:

| Cron Hook | Description |
|---|---|
| `amrod_fetch_product` | Fetches products and branding from the API |
| `amrod_product_import` | Imports/updates products in WooCommerce |
| `amrod_image_import` | Downloads and attaches product images |
| `amrod_fetch_prices` | Syncs product prices |
| `amrod_process_categories` | Syncs product category hierarchy |
| `amrod_process_stocks` | Syncs stock quantities |
| `amrod_process_brands` | Imports branding pricing data |

All cron jobs are cleared automatically when the plugin is deactivated.

## Architecture

```
amrod-integration.php       Main plugin file – settings page, cron setup, WordPress hooks
AmroidAPI.php               HTTP client wrapping all Amrod REST API calls
AmrodProductImporter.php    Creates/updates WooCommerce products and product variants
AmrodCategoryImporter.php   Builds the WooCommerce category tree from Amrod data
AmrodPriceImporter.php      Updates product regular prices
AmrodStocksImporter.php     Updates product stock quantities
AmrodBrandingImporter.php   Stores branding/decoration pricing in a custom DB table
amrod-data/                 Local cache for raw API responses (amrod_data.json)
```

### Amrod API Endpoints Used

| Endpoint | Purpose |
|---|---|
| *(configurable)* | Authentication – returns a bearer token |
| `GET /api/v1/Categories/` | Product category hierarchy |
| `GET /api/v1/Products/GetProductsAndBranding` | Products with branding info |
| `GET /api/v1/Stock/` | Stock levels |
| `GET /api/v1/Prices/` | Product pricing |
| `GET /api/v1/BrandingPrices/` | Branding/decoration pricing |

### Custom Database Table

On activation the plugin creates the `{prefix}_amrod_brand_pricing` table:

| Column | Type | Description |
|---|---|---|
| `id` | mediumint | Auto-increment primary key |
| `brand_method` | varchar(100) | Branding method name |
| `brand_code` | varchar(100) | Branding code identifier |
| `data` | longtext | JSON-encoded pricing data |

## Deduplication

Each importer checks product metadata before processing to avoid duplicate work:

- **Products** – skipped if the `completed` meta is `"done"`.
- **Images** – skipped if the `image_processed` meta is `"done"`.
- **Prices** – skipped if the `regular-{fullCode}` meta is `"true"`.
- **Stocks** – skipped if the `stock-{fullCode}` meta is `"true"`.

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](http://www.gnu.org/licenses/gpl-2.0.txt).

## Author

**Okeowo Aderemi** – [okeowoaderemi.com](https://okeowoaderemi.com)  
© 2024 Retani Consults
