# Product Requirements Document (PRD)

## WooCommerce Custom Loop Filters (WCLF)

**Version:** 2.9.18
**Author:** AlirezaKMaxim
**License:** GPL2

---

## 1. Product Overview

WooCommerce Custom Loop Filters is a WordPress/WooCommerce plugin that provides AJAX-powered product filtering (price, category, brand, stock, attribute, sorting) for both Elementor Loop Grid widgets and native WooCommerce shop archives. The entire filter state is URL-based, enabling shareable links, browser history navigation, and cross-filtering between widgets.

---

## 2. Problem Statement

WooCommerce's default product listing page offers no built-in AJAX filtering. Merchants with large catalogs need real-time, contextual filters (price range, categories, brands, attributes, stock status) that update product results without page reloads. Elementor's Loop Grid widget lacks native query filtering beyond what WooCommerce archives provide. There is a gap between what Elementor Loop Grid can display and what customers need to navigate a product catalog effectively.

---

## 3. Goals & Objectives

| Goal | Metric |
|---|---|
| Zero page-reload filtering | All filter interactions use AJAX |
| Shareable filter URLs | Every filter state lives in the URL query string |
| Cross-filtering | Changing one filter updates the options in all other filters |
| Dual integration | Works with Elementor Loop Grid and native WC archives |
| Zero external JS dependencies | Vanilla JS only, no npm/composer build step |
| Extensible shortcode system | 7 shortcodes for granular widget placement |
| Contextual intelligence | Filters adapt based on archive context (shop, category, brand) |

---

## 4. User Personas

### 4.1 Store Owner
- Uses Elementor to build product pages
- Needs filterable product grids without writing code
- Wants filters to work consistently across shop, category, and brand pages

### 4.2 Developer
- Builds custom Elementor-based WooCommerce themes
- Needs a reliable AJAX filter engine that integrates with Loop Grid
- Wants a shortcode API for placing individual filter widgets anywhere

### 4.3 Shopper
- Browses a catalog with hundreds/thousands of products
- Needs to narrow results by price, category, brand, attribute, or stock
- Expects instant results without page reloads

---

## 5. Functional Requirements

### 5.1 Price Filter
- **FR-01:** Dual-handle range slider with step of 1,000 (Toman)
- **FR-02:** Auto-detects min/max price from current product set
- **FR-03:** Updates URL with `min_price` and `max_price` parameters
- **FR-04:** Labels display in Persian locale format

### 5.2 Category Filter
- **FR-05:** Radio button selector for product categories
- **FR-06:** Contextual mode (`contextual="yes"`, default): shows only relevant categories based on archive context (shop top-level; category children; brand/tag/search categories from page products)
- **FR-07:** Non-contextual mode (`contextual="no"`): shows all site-wide categories
- **FR-08:** Auto-hides on leaf categories (categories with no children)
- **FR-09:** Updates URL with `product_cat_filter` parameter (term ID)

### 5.3 Brand Filter
- **FR-10:** Radio button selector for product brands
- **FR-11:** Auto-detects brand taxonomy (`product_brand`, `pwb-brand`, `yith_product_brand`, `brand`)
- **FR-12:** Auto-hides on brand archive pages
- **FR-13:** Updates URL with `product_brand_filter` parameter (term ID)

### 5.4 Stock Filter
- **FR-14:** Toggle switch for "in stock only"
- **FR-15:** Updates URL with `stock_filter=instock` or removes the parameter

### 5.5 Attribute Filter
- **FR-16:** Generic radio filter for any WooCommerce product attribute (color, size, etc.)
- **FR-17:** Parameterized via `attribute="color"` shortcode attribute
- **FR-18:** Updates URL with `filter_{attribute}` parameter
- **FR-18b:** Terms are scoped to products in the current catalog / active filter set (cross-filtering)

### 5.6 Sorting
- **FR-19:** Dropdown with 7 sort options: Discount, Discount (asc), Popularity, Date, Sales, Price, Price (desc), All
- **FR-20:** Updates URL with `orderby` parameter
- **FR-20b:** "همه" clears only `orderby` (other filters stay active)
- **FR-20c:** `orderby=discount` / `discount-asc` sorts by `discount_percentage` without hiding products that have no discount

### 5.7 Product Count
- **FR-21:** Dynamic product count display: "Showing X of Y products"
- **FR-22:** Customizable labels with `{filtered}` and `{total}` placeholders

### 5.8 AJAX Engine
- **FR-23:** Full-page fetch + DOM swap (not JSON endpoint)
- **FR-24:** Replaces product container and all 7 filter wrapper innerHTMLs
- **FR-25:** Full-page loading overlay with blur + centered spinner during fetch
- **FR-26:** Browser history push/popstate support
- **FR-27:** Graceful fallback to full page reload if no product container found
- **FR-27d:** Filtered URL responses send no-cache / no-store headers and set `DONOTCACHEPAGE` for page-cache plugins

### 5.8b Dependencies
- **FR-27b:** Plugin header declares `Requires Plugins: woocommerce`
- **FR-27c:** If WooCommerce is inactive, components do not boot and an admin error notice is shown

### 5.9 Product Meta
- **FR-28:** Auto-calculate `discount_percentage` on product save (simple, variable, variation)
- **FR-29:** Track per-product page views via cookie (once per session, excluding bots and admins)
- **FR-30:** Bulk discount recalculation via admin UI or CLI

### 5.10 Admin Panel
- **FR-31:** Setup documentation (Elementor Theme Builder integration)
- **FR-32:** Shortcode reference table
- **FR-33:** Contextual category behavior table
- **FR-34:** URL parameter reference
- **FR-35:** Discount recalculation tool
- **FR-36:** Integration test runner (7 scenarios)

---

## 6. Non-Functional Requirements

| Requirement | Details |
|---|---|
| **Performance** | Request-level static caching, transient caching for price ranges (24h), optimized SQL queries with chunked batching (2,000 items) |
| **Security** | All `$_GET` inputs sanitized (`intval`, `floatval`, `sanitize_text_field`, `sanitize_key`, `wc_clean`, `wp_unslash`). SQL uses `$wpdb->prepare()`. Admin actions verify capabilities and nonces |
| **Compatibility** | WordPress 5.8+, PHP 7.4+, WooCommerce (required via `Requires Plugins` + runtime check), Elementor (optional), WP-CLI (optional) |
| **Accessibility** | RTL-first admin UI (Persian), standard form controls |
| **Browser Support** | Modern browsers with `fetch()` and `DOMParser` support |
| **No Build Step** | Vanilla JS, zero npm/composer dependencies |

---

## 7. Integration Points

| System | Integration Method |
|---|---|
| **Elementor Loop Grid (Recommended)** | Set Query Source to "Products", Include to "Current Query". Plugin hooks into `pre_get_posts` at priority 99999 |
| **Elementor Loop Grid (Alternative)** | Set Query ID to `custom_loop_filters`, Source to "Posts". Plugin hooks into `elementor/query/custom_loop_filters` |
| **Native WC Archives** | Plugin hooks into `woocommerce_product_query` |
| **Brand Plugins** | Auto-detects `product_brand`, `pwb-brand`, `yith_product_brand`, `brand` taxonomies |
| **WP-CLI** | `wp wclf test-scenarios` command |

---

## 8. URL Parameters

| Parameter | Type | Description |
|---|---|---|
| `min_price` | Integer | Minimum price bound (step 1,000) |
| `max_price` | Integer | Maximum price bound (step 1,000) |
| `product_cat_filter` | Term ID | Category filter |
| `product_brand_filter` | Term ID | Brand filter |
| `stock_filter` | `instock` | Stock availability filter |
| `filter_{attribute}` | Slug | WooCommerce attribute filter |
| `orderby` | String | Sorting method |
| `s` + `post_type=product` | String | Product search keyword |

---

## 9. Shortcodes

| Shortcode | Parameters | Description |
|---|---|---|
| `[elementor_price_filter]` | none | Dual-handle price range slider |
| `[elementor_category_filter]` | `contextual` (yes/no) | Smart category filter |
| `[elementor_brand_filter]` | `taxonomy` | Brand filter |
| `[elementor_stock_filter]` | none | In-stock toggle |
| `[elementor_attribute_filter]` | `attribute` | Generic attribute filter |
| `[beban_product_filters]` | none | Sorting dropdown |
| `[wclf_product_count]` | `show_total`, `label_filtered`, `label_both`, `label_total` | Dynamic product count |

---

## 10. Success Criteria

- All 7 filter types function correctly via AJAX without page reloads
- Filter state persists across URL sharing and browser navigation
- Cross-filtering works: changing one filter updates all other filter widgets
- Plugin works with both Elementor Loop Grid and native WC archives
- Zero JavaScript errors in console
- All 7 integration test scenarios pass
- No external JS dependencies; vanilla JS only

---

## 11. Out of Scope

- WooCommerce REST API filtering
- Gutenberg/Block editor integration
- Multi-currency support
- Filter analytics/tracking
- Mobile-specific filter UI (responsive CSS handled by theme)
- AJAX add-to-cart from filtered results
