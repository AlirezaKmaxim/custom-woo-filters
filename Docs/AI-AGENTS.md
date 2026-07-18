# AI AGENTS — Full Project Guide

## WooCommerce Custom Loop Filters (WCLF)

This file is a complete reference for AI agents working on this codebase. It covers every aspect of the project: what it does, how every file works, how components interact, conventions to follow, and common tasks.

---

## 1. What This Project Is

A **WordPress/WooCommerce plugin** (version 2.9.5) that adds AJAX-powered product filters to WooCommerce product listings. It works with both **Elementor Loop Grid** widgets and **native WooCommerce shop/category archives**. All filtering happens without page reloads. The plugin is built entirely in vanilla PHP and JavaScript with zero external dependencies.

**Core idea:** The plugin hooks into WordPress query pipelines to modify product queries based on URL parameters, and provides 7 shortcodes that render filter UI widgets (price slider, category radio, brand radio, stock toggle, attribute radio, sorting dropdown, product count). A JavaScript AJAX engine fetches the full HTML page and swaps DOM containers to update both products and filters simultaneously.

---

## 2. Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP (WordPress/WooCommerce) |
| Frontend | Vanilla JavaScript (ES5/ES6), no frameworks |
| Styling | Inline CSS (admin), JS-injected overlay (frontend) |
| Database | WordPress `wp_posts`, `wp_postmeta`, `wp_term_relationships`, `wp_term_taxonomy`, `wc_product_meta_lookup` |
| External Dependencies | **None** — zero npm, zero composer packages |
| Build Step | **None** — files are used as-is |

---

## 3. File-by-File Breakdown

### Root Files

#### `woo-custom-loop-filters.php` — Main Bootstrap
- Defines the `WCLF_Bootstrap` singleton class
- Sets constants: `WCLF_PLUGIN_DIR`, `WCLF_PLUGIN_URL`, `WCLF_VERSION`
- Includes all PHP files from `includes/`
- Instantiates all components on `plugins_loaded` (ensures WooCommerce is loaded)
- Registers deactivation hook to clear the `wclf_viewed_products` cookie

#### `update-discounts.php` — Bulk Discount Script
- Standalone script, callable via CLI (`php update-discounts.php`) or browser (admin-only)
- Calls `WCLF_Product_Meta::recalculate_all_discounts()`
- Blocks non-admin web access with 403 response

#### `uninstall.php` — Clean Uninstall
- Removes only `wclf_*` transients and options from the database
- **Intentionally preserves** `discount_percentage` and `post_views` post meta (may be used by themes)
- Clears the `wclf_viewed_products` cookie

### PHP Includes (`includes/`)

#### `class-wclf-product-meta.php` — `WCLF_Product_Meta`
**Purpose:** Manages product metadata.

**What it does:**
- On every `save_post` for products: calculates and stores `discount_percentage` post meta
  - Formula: `round(((regular_price - sale_price) / regular_price) * 100)`
  - For variable products: stores the **maximum** discount across all variations
- On `save_post`: clears price-range transients for affected archives
- On `delete_post` / `untrash_post`: clears price-range transients
- Tracks page views via `post_views` meta, using the `wclf_viewed_products` cookie
  - One view per product per browser session
  - Excludes bots (user-agent check) and administrators
- Provides `recalculate_all_discounts()` for bulk recalculation

**Hooks into:** `save_post`, `delete_post`, `untrash_post`

---

#### `class-wclf-query-helper.php` — `WCLF_Query_Helper`
**Purpose:** Shared static utility for resolving product sets and computing aggregate data.

**What it does:**
- Resolves the current page's product ID set (shop, category, brand, tag, search)
- Computes min/max price range for the current product set
- Counts taxonomy terms among those products
- Uses request-level static caching (`$product_ids_cache`, `$term_counts_cache`, `$price_range_cache`) to avoid redundant queries
- Performs direct SQL for performance:
  - Price range: uses `wc_product_meta_lookup` table when available, falls back to `postmeta`
  - Term counts: joins `term_relationships`/`term_taxonomy` tables, chunked in batches of 2,000

**Key methods:**
- `get_current_product_ids()` — returns array of product IDs for current scope
- `get_price_range()` — returns `['min' => ..., 'max' => ...]`
- `get_term_counts($taxonomy, $product_ids)` — returns `['term_id' => count, ...]`
- `reset_cache()` — resets static caches (used by scenario tester between tests)

**Cache key:** Derived from GET parameters + queried object, so different filter combinations get separate cache entries.

---

#### `class-wclf-query-handler.php` — `WCLF_Query_Handler`
**Purpose:** Core engine that modifies `WP_Query` to apply filters.

**What it does:**
Hooks into three WordPress query paths:

1. **`elementor/query/custom_loop_filters`** — When Elementor Loop Grid uses Query ID = `custom_loop_filters`
   - Forces `post_type=product`
   - Applies taxonomy context from the current archive
   - Applies all GET-parameter filters

2. **`woocommerce_product_query`** — Native WooCommerce shop/category pages
   - Applies all GET-parameter filters to WC's existing product query

3. **`pre_get_posts` at priority 99999** — Elementor "Products" widget without Query ID
   - Detects if the main query is Elementor's product grid
   - Applies all filters

**Filter application logic:**

| GET Parameter | Query Modification |
|---|---|
| `min_price` / `max_price` | `meta_query` with `_price BETWEEN min AND max` |
| `product_cat_filter` | `tax_query` with `product_cat` = term_id |
| `product_brand_filter` | `tax_query` with `product_brand` = term_id |
| `stock_filter=instock` | `meta_query` with `_stock_status = instock` |
| `filter_{attr}` | `tax_query` with `pa_{attr}` = slug |
| `orderby` | Sets `orderby`, `order`, `meta_key` based on value |
| `s` + `post_type=product` | Product search |

**Sorting options:**
- `discount` → `meta_value_num` on `discount_percentage`, DESC
- `discount-asc` → `meta_value_num` on `discount_percentage`, ASC
- `popularity` → `meta_value_num` on `post_views`, DESC
- `date` → `date`, DESC
- `sales` → `meta_value_num` on `total_sales`, DESC
- `price` → `meta_value_num` on `_price`, ASC
- `price-desc` → `meta_value_num` on `_price`, DESC

---

#### `class-wclf-shortcodes.php` — `WCLF_Shortcodes`
**Purpose:** Renders all 7 filter shortcodes and manages frontend script enqueueing.

**Shortcodes registered:**

| Shortcode | What it renders | Parameters |
|---|---|---|
| `[elementor_price_filter]` | Dual-handle price range slider | none |
| `[elementor_category_filter]` | Category radio buttons | `contextual` (yes/no, default yes) |
| `[elementor_brand_filter]` | Brand radio buttons | `taxonomy` (default auto-detect) |
| `[elementor_stock_filter]` | In-stock toggle switch | none |
| `[elementor_attribute_filter]` | Generic attribute radio buttons | `attribute` (required, e.g., "color") |
| `[beban_product_filters]` | Sorting dropdown | none |
| `[wclf_product_count]` | Dynamic product count text | `show_total`, `label_filtered`, `label_both`, `label_total` |

**Additional responsibilities:**
- Enqueues all JS files (price, category, brand, stock, attribute filters + AJAX core)
- Uses `wp_localize_script()` to pass `WP_DEBUG` flag and filter wrapper selectors to JS
- Adds body classes for brand/leaf-category archives
- Adds inline CSS to hide filter placeholders when no data exists
- Resolves contextual categories based on archive type
- Auto-detects brand taxonomy from known brand plugins

---

#### `class-wclf-admin.php` — `WCLF_Admin`
**Purpose:** Admin settings page under WP menu ("Custom Elementor Filters" in Persian).

**What it provides:**
1. Setup documentation (Elementor Theme Builder integration steps)
2. Shortcode reference table
3. Contextual category behavior table
4. URL parameter reference
5. Discount recalculation tool (triggers `admin_post` action)
6. Integration test runner (triggers `admin_post` action)
7. Technical notes and maintenance documentation

**Admin UI:** RTL-first (Persian), card-based layout with color-coded borders. All text in Persian.

---

#### `class-wclf-scenario-tester.php` — `WCLF_Scenario_Tester`
**Purpose:** Integration test suite with 7 test scenarios.

**Test scenarios:**
1. Shop page (default filters)
2. Parent category archive
3. Leaf category archive (no children)
4. Brand archive
5. Product tag archive
6. Product search results
7. Product count sensitivity (verifies counts change with filters)

**Run methods:**
- Admin UI button (via `admin_post` action)
- WP-CLI: `wp wclf test-scenarios`

**Implementation:** Uses `go_to()` to simulate page loads, then validates price ranges, term counts, and brand facets. Resets `WCLF_Query_Helper` static caches between tests via reflection.

---

### JavaScript Files (`assets/js/`)

#### `wclf-ajax-core.js` — Core AJAX Engine
**The most important frontend file.**

**Exports:** `window.wclf_apply_filters(url)`

**Flow:**
1. Finds the product container (tries multiple selectors: `#cprf-products-area`, `.elementor-widget-loop-grid`, `.elementor-loop-container`, `.products`)
2. Shows loading overlay with spinner (injected into DOM)
3. Pushes new URL to browser history via `history.pushState()`
4. Fetches the full page HTML via `fetch(url)`
5. Parses response with `DOMParser`
6. Replaces product container innerHTML
7. Replaces all 7 filter wrapper innerHTMLs (so filters reflect new context)
8. Re-initializes all filter JS (clone-and-replace pattern)
9. Hides loading overlay

**Also handles:**
- Sorting link click handlers
- `popstate` event (browser back/forward → full page reload)
- Debug logging (controlled by `WP_DEBUG`, `?wclf_debug=1`, or localStorage `wclf_debug`)

**Selectors tried for product container:**
1. `#cprf-products-area`
2. `.elementor-widget-loop-grid`
3. `.elementor-loop-container`
4. `.products`
5. Fallback: full page reload

---

#### `wclf-price-filter.js` — Price Range Slider
- Dual-handle range slider with step of 1,000
- Updates labels on `input` event (live preview while dragging)
- Applies filter on `change` event (drag release)
- Labels formatted with Persian locale (`Intl.NumberFormat`)

#### `wclf-category-filter.js` — Category Radio Filter
- Listens for `change` on `cat_filter_radio` inputs
- Calls `wclf_apply_filters()` with new URL

#### `wclf-brand-filter.js` — Brand Radio Filter
- Same pattern as category filter, using `brand_filter_radio`

#### `wclf-stock-filter.js` — Stock Toggle Switch
- Checkbox toggle: checked → `stock_filter=instock`, unchecked → removes parameter

#### `wclf-attribute-filter.js` — Generic Attribute Filter
- Uses `data-attribute` attribute on the wrapper element to determine URL parameter key
- Same radio-button pattern as category/brand filters

---

## 4. How Filters Work (End-to-End)

### Step 1: User Changes a Filter
A user moves the price slider, selects a category radio button, or toggles the stock switch.

### Step 2: JS Builds a New URL
The filter's JS handler reads the current URL, modifies/adds the relevant GET parameter, and calls `window.wclf_apply_filters(newUrl)`.

### Step 3: AJAX Fetch
`wclf-ajax-core.js` fetches the **entire HTML page** at the new URL (not a JSON endpoint).

### Step 4: Server Processes the Request
WordPress loads the page. `WCLF_Query_Handler` detects the catalog context and modifies the product query based on the URL parameters. `WCLF_Shortcodes` render all filter widgets with updated data from `WCLF_Query_Helper`.

### Step 5: DOM Swap
The JS parses the response HTML and replaces:
- The product container (showing filtered products)
- All 7 filter wrapper containers (showing updated counts/ranges)

### Step 6: Re-initialization
Each filter JS is re-initialized by cloning elements and replacing them (removes old event listeners, attaches new ones).

### Cross-Filtering
Because the full page is re-rendered, changing one filter automatically updates all others. For example, selecting a category updates the price range to show only prices within that category, and updates brand counts to show only brands within that category.

---

## 5. URL Parameters Reference

| Parameter | Example | Description |
|---|---|---|
| `min_price` | `min_price=50000` | Minimum price (Toman, step 1000) |
| `max_price` | `max_price=200000` | Maximum price (Toman, step 1000) |
| `product_cat_filter` | `product_cat_filter=15` | Category term ID |
| `product_brand_filter` | `product_brand_filter=22` | Brand term ID |
| `stock_filter` | `stock_filter=instock` | In-stock only |
| `filter_color` | `filter_color=red` | Attribute filter (generic) |
| `orderby` | `orderby=discount` | Sort method |
| `s` | `s=keyword&post_type=product` | Product search |

---

## 6. Caching Strategy

| Layer | What | How | When Invalidated |
|---|---|---|---|
| Request-level | Product IDs, term counts, price ranges | Static properties in `WCLF_Query_Helper` | End of PHP request |
| Transient | Price ranges per archive+filter combo | `set_transient()` with 24h TTL | Product create/update/delete/trash/untrash |
| Post meta | `discount_percentage` | Stored on `save_post` | Recalculated on every save |
| Post meta | `post_views` | Incremented on page view | Never (permanent) |
| Cookie | `wclf_viewed_products` | Browser session cookie | Browser session end |

---

## 7. Security

- **Input sanitization:** All `$_GET` values sanitized with `intval()`, `floatval()`, `sanitize_text_field()`, `sanitize_key()`, `sanitize_title()`, `wc_clean()`, `wp_unslash()`
- **SQL injection:** All queries use `$wpdb->prepare()` with placeholders
- **CSRF:** Admin actions use `check_admin_referer()` nonces
- **Authorization:** Admin actions require `current_user_can('manage_options')`
- **Directory traversal:** `ABSPATH` check at top of every PHP file
- **CLI protection:** `update-discounts.php` returns 403 for non-admin web requests
- **Bot filtering:** View tracking skips known bot user agents

---

## 8. Dependencies

| Dependency | Required? | Used For |
|---|---|---|
| WordPress | Yes | Core APIs |
| WooCommerce | Yes | Product queries, price meta, tax queries |
| Elementor | No | Loop Grid integration (optional) |
| WP-CLI | No | Test runner CLI command (optional) |
| Brand plugins | No | Auto-detects brand taxonomies |
| npm/composer | No | Zero build dependencies |

---

## 9. Admin Page

The admin page is accessible under the WordPress admin menu. All UI is in **Persian (RTL)**.

**Sections:**
1. Setup instructions (Elementor Theme Builder integration)
2. Shortcode reference (all 7 shortcodes with parameters)
3. Contextual category behavior table
4. URL parameter reference
5. Discount recalculation button
6. Integration test runner button (7 scenarios)
7. Technical notes
8. Maintenance tools documentation

---

## 10. Common Tasks for AI Agents

### Adding a New Filter Type
1. Create a new JS file in `assets/js/` for the filter UI
2. Add a new shortcode in `class-wclf-shortcodes.php` that renders the filter HTML
3. Add query modification logic in `class-wclf-query-handler.php` for the new GET parameter
4. Register the JS file in `class-wclf-shortcodes.php` (enqueue + localize)
5. Add the new filter wrapper selector to `wclf-ajax-core.js` swap list
6. Add a test scenario in `class-wclf-scenario-tester.php`

### Adding a New Sort Option
1. Add the option HTML in the `beban_product_filters` shortcode handler in `class-wclf-shortcodes.php`
2. Add the `orderby` case in `WCLF_Query_Handler::apply_filters()`

### Modifying Price Filter Behavior
1. UI changes: `assets/js/wclf-price-filter.js`
2. Query changes: `class-wclf-query-handler.php` (meta_query for `_price`)
3. Price range calculation: `class-wclf-query-helper.php` (`get_price_range()`)

### Modifying Category Filter Context
1. Context resolution: `class-wclf-shortcodes.php` (category filter shortcode handler)
2. Category hiding logic: inline CSS added by `class-wclf-shortcodes.php`

### Debugging AJAX Issues
1. Enable debug: add `?wclf_debug=1` to URL, or set `WP_DEBUG` to true, or set `localStorage.wclf_debug = '1'`
2. Check browser console for WCLF debug logs
3. Verify product container selector exists on the page
4. Check if all filter wrapper selectors exist

### Running Tests
- Admin UI: click the test runner button on the WCLF admin page
- WP-CLI: `wp wclf test-scenarios`
- Manual: check price ranges, term counts, and brand facets after each filter change

---

## 11. Key Conventions

- **No comments in code** unless explicitly requested
- **No external dependencies** — all JS is vanilla
- **URL-as-state** — never store filter state in JS variables; always read from/write to URL
- **Full-page fetch** — never create a separate AJAX API endpoint
- **Clone-and-replace** — when re-initializing JS after DOM swap, clone elements to remove old listeners
- **Request-level caching** — use static properties in `WCLF_Query_Helper` for per-request caching
- **Persian/RTL** — admin UI is in Persian with RTL layout
- **Sanitize everything** — never trust `$_GET`, `$_POST`, or `$_REQUEST` values
- **Transient invalidation** — clear price transients whenever products change

---

## 12. Known Limitations

1. Full-page fetch means larger AJAX payloads (trade-off for consistency)
2. No WooCommerce REST API filtering
3. No Gutenberg/Block editor integration
4. No multi-currency support
5. No filter analytics/tracking
6. Mobile filter UI depends on theme CSS
7. Admin UI is Persian-only (no i18n for admin)
8. Price step is hardcoded to 1,000 (Toman)
9. View tracking uses cookies (can be cleared, not server-side)
10. Discount percentage is stored as post meta (not real-time computed)
