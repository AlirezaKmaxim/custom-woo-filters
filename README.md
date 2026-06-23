# WooCommerce Custom Loop Filters

A comprehensive OOP WordPress plugin to filter and sort products in **Elementor Loop Grid** and **WooCommerce default shop archives** using a single Query ID and URL parameters.

**Version:** 1.1.0  
**Author:** [AlirezaKMaxim](https://github.com/AlirezaKmaxim)  
**License:** GPLv2  
**Text Domain:** `woo-custom-loop-filters`

---

## Features

- **Unified filtering system** — works with both Elementor Loop Grid / Product Grid widgets and default WooCommerce shop, category, and tag archives.
- **Price range slider** — dual-handle range slider with Persian number formatting, apply/reset buttons.
- **Category filter** — radio buttons for each non-empty product category.
- **Stock filter** — toggle switch to show only in-stock products.
- **Advanced sorting** — 6 options: biggest discount, most viewed, newest, best selling, cheapest, most expensive.
- **Automatic discount percentage** — calculated and stored as product meta on save.
- **Product view tracking** — counts single product page views with bot detection and cookie-based duplicate prevention.
- **Price cache** — min/max prices cached in a 24-hour transient, automatically busted on product changes.
- **Zero external dependencies** — pure PHP + vanilla JavaScript. No build tools, no AJAX, no REST API needed.
- **Persian (Farsi) UI** — all labels and admin text are in Persian.

---

## Requirements

- WordPress 5.0+
- WooCommerce 4.0+
- Elementor 3.0+ (for Loop Grid / Product Grid widgets)

---

## Installation

1. Download the plugin and upload the `woo-custom-loop-filters` folder to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins** → **Installed Plugins**.
3. Go to **Elementor Custom Filters** in the WordPress admin menu for setup instructions.

---

## Usage

### With Elementor Loop Grid / Product Grid

1. Add a **Loop Grid** or **Product Grid** widget to your page.
2. In the widget settings, set **Query ID** to `custom_loop_filters`.
3. Use the shortcodes below anywhere on the page to render filter controls.
4. When the user applies a filter, the page reloads with URL parameters, and the grid updates automatically.

### With Default WooCommerce Archives

The plugin automatically hooks into `woocommerce_product_query`, so filters work on native shop, category, and tag pages — no configuration needed.

### Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[elementor_price_filter]` | Dual-handle price range slider with apply/reset |
| `[elementor_category_filter]` | Radio list of product categories |
| `[elementor_stock_filter]` | Toggle switch for in-stock filtering |
| `[beban_product_filters]` | Horizontal sort links (discount, popularity, date, sales, price) |

### URL Parameters

| Parameter | Values | Effect |
|-----------|--------|--------|
| `min_price` | number | Minimum price filter |
| `max_price` | number | Maximum price filter |
| `product_cat_filter` | category slug | Filter by category |
| `stock_filter` | `instock` | Show only in-stock products |
| `orderby` | `discount`, `popularity`, `date`, `sales`, `price`, `price-desc` | Sort order |

---

## Architecture

```
woo-custom-loop-filters/
├── woo-custom-loop-filters.php          # Bootstrap (singleton)
├── assets/
│   ├── css/                             # Placeholder (theme handles styling)
│   └── js/
│       ├── wclf-price-filter.js         # Price slider UI
│       ├── wclf-category-filter.js      # Category radio UI
│       └── wclf-stock-filter.js         # Stock toggle UI
└── includes/
    ├── class-wclf-product-meta.php      # Product meta management
    ├── class-wclf-query-handler.php     # WP_Query modification engine
    ├── class-wclf-shortcodes.php        # Shortcode definitions
    └── class-wclf-admin.php             # Admin settings page
```

### Classes

| Class | File | Responsibility |
|-------|------|----------------|
| `WCLF_Bootstrap` | `woo-custom-loop-filters.php` | Singleton entry point, loads all components |
| `WCLF_Product_Meta` | `includes/class-wclf-product-meta.php` | Discount %, view count, price cache |
| `WCLF_Query_Handler` | `includes/class-wclf-query-handler.php` | Applies URL filters to WP_Query |
| `WCLF_Shortcodes` | `includes/class-wclf-shortcodes.php` | Renders filter UI, enqueues JS |
| `WCLF_Admin` | `includes/class-wclf-admin.php` | Admin documentation page |

### Hooks

| Hook | Context | Description |
|------|---------|-------------|
| `elementor/query/custom_loop_filters` | Elementor | Filters Elementor Loop Grid queries |
| `woocommerce_product_query` | WooCommerce | Filters default shop archives |
| `woocommerce_catalog_orderby` | WooCommerce | Adds custom sort options |
| `woocommerce_update_product` | Product save | Updates discount %, busts price cache |
| `template_redirect` | Front-end | Tracks product views |
| `admin_menu` | Admin | Creates settings page |

---

## Styling

The plugin ships **no CSS**. All filter UI styling must be provided by your theme or custom CSS. This keeps the plugin lightweight and flexible.

---

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

---

## License

GPLv2 or later. See [LICENSE](LICENSE) for details.
