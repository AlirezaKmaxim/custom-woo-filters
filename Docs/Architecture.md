# Architecture Document

## WooCommerce Custom Loop Filters (WCLF)

---

## 1. System Overview

WCLF is a WordPress plugin that intercepts WooCommerce product queries, applies URL-based filters, and renders updated filter UI via shortcodes. The frontend uses AJAX to fetch full HTML pages and surgically swap DOM containers, ensuring all server-rendered content remains consistent.

```
┌─────────────────────────────────────────────────────────┐
│                    BROWSER (Frontend)                    │
│                                                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐    │
│  │ Price Slider │  │ Category    │  │ Brand       │    │
│  │ (JS)         │  │ Filter (JS) │  │ Filter (JS) │    │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘    │
│         │                 │                 │            │
│         ▼                 ▼                 ▼            │
│  ┌─────────────────────────────────────────────────┐    │
│  │           wclf_apply_filters(url)               │    │
│  │           (wclf-ajax-core.js)                   │    │
│  └──────────────────────┬──────────────────────────┘    │
│                         │ fetch(url)                     │
│                         ▼                               │
├─────────────────────────┼───────────────────────────────┤
│                    SERVER (PHP)                          │
│                         │                               │
│  ┌──────────────────────▼──────────────────────────┐    │
│  │         WordPress Query Pipeline                │    │
│  │                                                 │    │
│  │  Elementor: elementor/query/custom_loop_filters │    │
│  │  WC:        woocommerce_product_query           │    │
│  │  Generic:   pre_get_posts (prio 99999)          │    │
│  │                      │                           │    │
│  │              ┌───────▼────────┐                  │    │
│  │              │ WCLF_Query_    │                  │    │
│  │              │ Handler        │                  │    │
│  │              │ (applies meta_ │                  │    │
│  │              │  query, tax_   │                  │    │
│  │              │  query, order) │                  │    │
│  │              └───────┬────────┘                  │    │
│  │                      │                           │    │
│  │              ┌───────▼────────┐                  │    │
│  │              │ WCLF_Query_    │                  │    │
│  │              │ Helper         │                  │    │
│  │              │ (shared cache: │                  │    │
│  │              │  price ranges, │                  │    │
│  │              │  term counts,  │                  │    │
│  │              │  product IDs)  │                  │    │
│  │              └───────┬────────┘                  │    │
│  │                      │                           │    │
│  │              ┌───────▼────────┐                  │    │
│  │              │ WCLF_Shortcodes│                  │    │
│  │              │ (renders HTML  │                  │    │
│  │              │  for all 7     │                  │    │
│  │              │  filter widgets│                  │    │
│  │              └────────────────┘                  │    │
│  └─────────────────────────────────────────────────┘    │
│                         │                               │
│                    Full HTML response                    │
│                    (DOM swap on frontend)                │
└─────────────────────────────────────────────────────────┘
```

---

## 2. Component Architecture

### 2.1 PHP Components

| Component | File | Responsibility |
|---|---|---|
| `WCLF_Bootstrap` | `woo-custom-loop-filters.php` | Singleton entry point. Defines constants, includes files, instantiates components |
| `WCLF_Product_Meta` | `includes/class-wclf-product-meta.php` | Manages `discount_percentage` and `post_views` meta. Hooks product save/delete events |
| `WCLF_Query_Handler` | `includes/class-wclf-query-handler.php` | Modifies WP_Query for Elementor, WC archives, and generic product queries |
| `WCLF_Query_Helper` | `includes/class-wclf-query-helper.php` | Static utility: resolves product IDs, computes price ranges, counts taxonomy terms. Request-level caching |
| `WCLF_Shortcodes` | `includes/class-wclf-shortcodes.php` | Renders 7 filter shortcodes. Handles script enqueueing and contextual term resolution |
| `WCLF_Admin` | `includes/class-wclf-admin.php` | Admin settings page, documentation, maintenance tools |
| `WCLF_Scenario_Tester` | `includes/class-wclf-scenario-tester.php` | 7 integration test scenarios (admin UI + WP-CLI) |

### 2.2 JavaScript Components

| Component | File | Responsibility |
|---|---|---|
| `wclf-ajax-core.js` | `assets/js/wclf-ajax-core.js` | Core AJAX engine: fetch, parse, DOM swap, history, re-init filters |
| `wclf-price-filter.js` | `assets/js/wclf-price-filter.js` | Dual-handle range slider UI |
| `wclf-category-filter.js` | `assets/js/wclf-category-filter.js` | Category radio button handler |
| `wclf-brand-filter.js` | `assets/js/wclf-brand-filter.js` | Brand radio button handler |
| `wclf-stock-filter.js` | `assets/js/wclf-stock-filter.js` | Stock toggle switch handler |
| `wclf-attribute-filter.js` | `assets/js/wclf-attribute-filter.js` | Generic attribute radio handler |

---

## 3. Initialization Flow

```
plugins_loaded
    │
    ▼
WCLF_Bootstrap::get_instance()
    │
    ├── Check class_exists('WooCommerce')
    │
    ├── new WCLF_Product_Meta()
    │       └── Hooks: save_post, delete_post, untrash_post
    │
    ├── new WCLF_Query_Handler()
    │       └── Hooks: elementor/query/{query_id}, woocommerce_product_query, pre_get_posts
    │
    ├── new WCLF_Shortcodes()
    │       └── Registers: 7 shortcodes, enqueues frontend scripts
    │
    ├── new WCLF_Admin()
    │       └── Hooks: admin_menu, admin_post actions
    │
    └── new WCLF_Scenario_Tester()
            └── Registers: WP-CLI command (if available)
```

---

## 4. Query Modification Pipeline

### 4.1 Entry Points (Three Hook Paths)

```
Path 1: Elementor with Query ID = "custom_loop_filters"
    └── elementor/query/custom_loop_filters (WCLF_Query_Handler)
        └── Forces post_type=product, applies all filters

Path 2: Native WooCommerce archives
    └── woocommerce_product_query (WCLF_Query_Handler)
        └── Applies all filters to WC's existing product query

Path 3: Elementor Products widget without Query ID
    └── pre_get_posts @ priority 99999 (WCLF_Query_Handler)
        └── Detects if main query is Elementor's product grid
        └── Applies all filters
```

### 4.2 Filter Application Logic

For each GET parameter, the Query Handler builds a `meta_query` or `tax_query` clause:

| Parameter | Query Type | Clause |
|---|---|---|
| `min_price` / `max_price` | `meta_query` | `_price BETWEEN min AND max` |
| `product_cat_filter` | `tax_query` | `product_cat` = term_id |
| `product_brand_filter` | `tax_query` | `product_brand` = term_id |
| `stock_filter=instock` | `meta_query` | `_stock_status = instock` |
| `filter_{attr}` | `tax_query` | `pa_{attr}` = slug |
| `orderby` | `$query->set('orderby')` | Varies: `meta_value_num`, `date`, `title`, etc. |

### 4.3 Request-Level Caching

`WCLF_Query_Helper` uses static class properties to cache results within a single request:

```
$product_ids_cache[$scope_signature]  →  array of product IDs
$term_counts_cache[$scope_signature]  →  array of term_id => count
$price_range_cache[$scope_signature]  →  ['min' => ..., 'max' => ...]
```

The `$scope_signature` is derived from:
- Current GET parameters (non-price filters)
- Queried object (archive page context)

This ensures multiple shortcodes on the same page share cached results without redundant `WP_Query` calls.

---

## 5. AJAX Data Flow

```
User changes filter (slider, radio, toggle)
    │
    ▼
Filter JS builds URL with GET parameters
    │
    ▼
window.wclf_apply_filters(url)
    │
    ├── 1. Find product container
    │       tries: #cprf-products-area → .elementor-widget-loop-grid
    │       → .elementor-loop-container → .products
    │
    ├── 2. Show loading overlay + spinner
    │
    ├── 3. history.pushState(state, '', url)
    │
    ├── 4. fetch(url) → full HTML page
    │
    ├── 5. DOMParser.parseFromString(html, 'text/html')
    │
    ├── 6. Swap product container innerHTML
    │
    ├── 7. Swap all 7 filter wrapper innerHTMLs
    │       (price, category, brand, stock, attribute, sorting, count)
    │
    ├── 8. Re-initialize all filter JS
    │       (clone-and-replace pattern to remove old listeners)
    │
    └── 9. Hide loading overlay
```

### 5.1 Why Full-Page Fetch (Not JSON)

The plugin fetches the entire HTML page rather than returning a JSON endpoint. This ensures:
- All server-rendered content (Elementor widgets, third-party plugins) is always consistent
- No need to maintain a separate API endpoint
- Cross-filtering works automatically (server re-renders all filters with updated counts)
- Theme-specific markup and styling is preserved

Trade-off: larger payloads, but guarantees correctness across any theme/Elementor setup.

---

## 6. Contextual Category System

The category filter is the most complex component due to its context-aware behavior:

```
Current Page Context          →  Categories Shown
─────────────────────────────────────────────────
/shop/                        →  Top-level categories (parent=0) with products
/product-category/pen/        →  Direct children of "pen"
/brand/nike/                  →  Categories that Nike products belong to
Leaf category (no children)   →  Filter hidden entirely
contextual="no" attribute     →  All categories site-wide
```

### 6.1 Context Resolution Flow

```
1. Determine current page type (shop, category, brand, tag, search)
2. If category archive:
   a. Get child terms of current category
   b. If no children → hide filter (CSS :has() collapse)
3. If brand archive:
   a. Get all products in current brand
   b. Get unique categories those products belong to
4. If shop:
   a. Get top-level categories with products
5. Count products per category within current filter scope
6. Render radio buttons with counts
```

### 6.2 Cross-Filtering

After any filter change, all filter widgets are re-rendered with updated counts:
- Changing price range → category/brand counts update to reflect only products in that price range
- Changing category → price range recalculates for products in that category only

This is achieved because the full HTML page is fetched with all new filter parameters, and the server re-renders each filter widget against the new product set.

---

## 7. Caching Strategy

| Layer | Mechanism | TTL | Invalidation |
|---|---|---|---|
| **Request-level** | Static properties in `WCLF_Query_Helper` | Single request | Automatic (PHP process ends) |
| **Price range** | WordPress transients | 24 hours | Product create/update/delete/trash/untrash via `clear_price_transient()` |
| **Product meta** | `discount_percentage` post meta | Permanent | Recalculated on every `save_post` |
| **View tracking** | `post_views` post meta + `wclf_viewed_products` cookie | Session | Cookie expires with browser session |

### 7.1 Price Transient Key Structure

```
wclf_price_range_{scope_hash}
```

The scope hash includes:
- Current archive context (shop/category/brand/tag)
- Active non-price filters (to ensure separate price ranges per filter combination)

### 7.2 Bulk Discount Recalculation

Available via:
1. Admin UI button (`class-wclf-admin.php`)
2. CLI script (`php update-discounts.php`)
3. WP-CLI (`wp wclf test-scenarios` runs tests; discount is via admin_post action)

Formula: `round(((regular_price - sale_price) / regular_price) * 100)`
For variable products: stores the **maximum** discount across all variations.

---

## 8. Security Architecture

| Control | Implementation |
|---|---|
| **Input sanitization** | `intval()`, `floatval()`, `sanitize_text_field()`, `sanitize_key()`, `sanitize_title()`, `wc_clean()`, `wp_unslash()` |
| **SQL injection prevention** | `$wpdb->prepare()` with proper placeholders |
| **CSRF protection** | `check_admin_referer()` nonces on all admin_post actions |
| **Authorization** | `current_user_can('manage_options')` on admin actions |
| **Directory traversal** | `ABSPATH` guard at top of every PHP file |
| **CLI script protection** | `update-discounts.php` blocks non-admin web access with 403 response |
| **Bot filtering** | View tracking excludes known bot user agents |

---

## 9. File Structure

```
woo-custom-loop-filters/
├── woo-custom-loop-filters.php        # Main bootstrap (singleton)
├── update-discounts.php               # Bulk discount recalculation CLI/browser script
├── uninstall.php                      # Clean uninstall handler
├── 180-ring-with-bg.svg              # Logo asset
├── includes/
│   ├── class-wclf-product-meta.php    # Product meta management
│   ├── class-wclf-query-helper.php    # Shared catalog query utility
│   ├── class-wclf-query-handler.php   # Core query modification engine
│   ├── class-wclf-shortcodes.php      # Shortcode renderers + script enqueue
│   ├── class-wclf-admin.php           # Admin settings page
│   └── class-wclf-scenario-tester.php # Integration test suite
├── assets/
│   ├── js/
│   │   ├── wclf-ajax-core.js          # Core AJAX engine
│   │   ├── wclf-price-filter.js       # Price range slider
│   │   ├── wclf-category-filter.js    # Category radio filter
│   │   ├── wclf-brand-filter.js       # Brand radio filter
│   │   ├── wclf-stock-filter.js       # Stock toggle switch
│   │   └── wclf-attribute-filter.js   # Generic attribute filter
│   └── css/
│       └── .gitkeep                   # Empty (all CSS is inline/generated)
├── PRD.md
├── Architecture.md
└── AI-AGENTS.md
```

---

## 10. Key Design Decisions

| Decision | Rationale |
|---|---|
| **Full-page fetch + DOM swap** | Guarantees consistency with any theme/Elementor setup. No separate API endpoint needed |
| **URL-as-state** | Filters are shareable, survive page reload, work with browser back/forward |
| **Clone-and-replace for event listeners** | Prevents memory leaks and duplicate handlers after DOM swap |
| **Shared static query helper** | Avoids redundant WP_Query calls when multiple filters are on the same page |
| **No external JS dependencies** | Zero build step, no npm/composer, instant deployment |
| **Transient caching with SQL invalidation** | 24h price range cache, cleared on product changes via SQL DELETE with LIKE patterns |
| **Chunked SQL batching (2,000 items)** | Handles large catalogs without memory exhaustion |
| **Three hook paths for query modification** | Covers Elementor with Query ID, Elementor without Query ID, and native WC archives |
| **Intentional uninstall safety** | Preserves `discount_percentage` and `post_views` meta that may be used by themes |

---

## 11. Extension Points

| Extension | Method |
|---|---|
| Add new filter type | Create new JS file, add new shortcode in `class-wclf-shortcodes.php`, add query modification in `class-wclf-query-handler.php` |
| Add new sort option | Add case in `beban_product_filters` shortcode handler in `class-wclf-shortcodes.php` |
| Add new brand taxonomy | Add taxonomy slug to detection list in `class-wclf-query-helper.php` |
| Add new admin tool | Add form + `admin_post` handler in `class-wclf-admin.php` |
| Add new integration test | Add method in `class-wclf-scenario-tester.php` and register in `run_all()` |
