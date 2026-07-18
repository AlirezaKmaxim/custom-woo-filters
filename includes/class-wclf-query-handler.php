<?php
defined('ABSPATH') || exit;

class WCLF_Query_Handler {

    /**
     * Elementor Query ID used in Loop Grid widgets.
     */
    const QUERY_ID = 'custom_loop_filters';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('elementor/query/' . self::QUERY_ID, array($this, 'apply_custom_query_filters'));

        add_action('woocommerce_product_query', array($this, 'apply_woocommerce_query_filters'));

        add_filter('woocommerce_catalog_orderby', array($this, 'add_custom_sorting_options'));

        // Fallback for Elementor Loop Grid with Source = Products (no Query ID field).
        add_action('pre_get_posts', array($this, 'apply_elementor_product_loop_filters'), 99999);

        // Avoid serving stale filtered HTML from page cache / CDN.
        add_action('init', array($this, 'maybe_mark_request_uncacheable'), 1);
        add_action('send_headers', array($this, 'maybe_send_no_cache_headers'));
    }

    /**
     * Whether the current request carries WCLF filter / sort query args.
     *
     * @return bool
     */
    private function request_has_filter_params() {
        $keys = array(
            'min_price',
            'max_price',
            'product_cat_filter',
            'product_brand_filter',
            'stock_filter',
            'orderby',
        );

        foreach ($keys as $key) {
            if (isset($_GET[$key]) && '' !== (string) wp_unslash($_GET[$key])) {
                return true;
            }
        }

        foreach ($_GET as $key => $value) {
            if (0 === strpos((string) $key, 'filter_') && '' !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tell popular page-cache plugins not to cache filtered catalog responses.
     */
    public function maybe_mark_request_uncacheable() {
        if (is_admin() || !$this->request_has_filter_params()) {
            return;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        if (!defined('DONOTCACHEOBJECT')) {
            define('DONOTCACHEOBJECT', true);
        }
        if (!defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }
    }

    /**
     * Emit no-store headers for filtered URLs (browsers, reverse proxies, CDNs).
     */
    public function maybe_send_no_cache_headers() {
        if (is_admin() || !$this->request_has_filter_params()) {
            return;
        }

        if (!headers_sent()) {
            nocache_headers();
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true);
            header('Pragma: no-cache', true);
            header('Expires: 0', true);
            header('CDN-Cache-Control: no-store', true);
            header('Cloudflare-CDN-Cache-Control: no-store', true);
        }
    }

    /**
     * Register custom sorting options in WooCommerce.
     *
     * @param array $options WooCommerce catalog sorting options.
     * @return array
     */
    public function add_custom_sorting_options($options) {
        $custom_options = array(
            'discount'      => 'بیشترین تخفیف',
            'discount-asc'  => 'کمترین تخفیف',
            'popularity'    => 'پربازدیدترین',
            'date'          => 'جدیدترین',
            'sales'         => 'پرفروش‌ترین',
            'price'         => 'ارزان‌ترین',
            'price-desc'    => 'گران‌ترین',
        );

        return $custom_options + $options;
    }

    /**
     * Handle query modification for Elementor custom Query ID.
     *
     * @param WP_Query $query WP_Query instance.
     */
    public function apply_custom_query_filters($query) {
        $this->ensure_product_query($query);
        $this->apply_archive_taxonomy_context($query);
        $this->apply_filters_and_sorting($query);
        $this->mark_query_as_filtered($query);
    }

    /**
     * Handle query modification for WooCommerce native archive pages.
     *
     * @param WP_Query $query WP_Query instance.
     */
    public function apply_woocommerce_query_filters($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if (!$this->is_catalog_context()) {
            return;
        }

        $this->apply_filters_and_sorting($query);
        $this->mark_query_as_filtered($query);
    }

    /**
     * Apply filters to Elementor secondary product loops (Products source, no Query ID).
     *
     * @param WP_Query $query WP_Query instance.
     */
    public function apply_elementor_product_loop_filters($query) {
        if (is_admin() || $this->is_query_already_filtered($query)) {
            return;
        }

        if (!$this->has_active_filter_params()) {
            return;
        }

        if (!$this->should_apply_to_product_loop($query)) {
            return;
        }

        if (!$query->is_main_query()) {
            $this->apply_archive_taxonomy_context($query);
        }

        $this->apply_filters_and_sorting($query);
        $this->mark_query_as_filtered($query);
    }

    /**
     * Determine whether filters should be applied to a product loop query.
     *
     * Only product queries are touched. Never rewrite Elementor Theme Builder /
     * library / menu queries to post_type=product — that blanks the archive
     * template (header+footer only).
     *
     * @param WP_Query $query WP_Query instance.
     * @return bool
     */
    private function should_apply_to_product_loop($query) {
        if (
            !$this->is_catalog_context()
            && !$this->is_shop_page_context()
            && !$this->is_product_search_context()
        ) {
            return false;
        }

        return $this->is_product_query($query);
    }

    /**
     * Force Elementor custom queries to load WooCommerce products.
     *
     * @param WP_Query $query WP_Query instance.
     */
    private function ensure_product_query($query) {
        $query->set('post_type', 'product');
        $query->set('post_status', 'publish');

        if (function_exists('WC') && WC()->query) {
            $wc_tax_query = WC()->query->get_tax_query();
            if (!empty($wc_tax_query)) {
                $existing_tax_query = $this->normalize_tax_query($query->get('tax_query') ?: array());
                $query->set('tax_query', $this->normalize_tax_query(array_merge($existing_tax_query, $wc_tax_query)));
            }
        }
    }

    /**
     * Keep the current archive taxonomy / search when Loop Grid uses Posts + Query ID.
     *
     * @param WP_Query $query WP_Query instance.
     */
    private function apply_archive_taxonomy_context($query) {
        if (is_product_category() && empty($_GET['product_cat_filter'])) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $this->append_tax_query($query, 'product_cat', $term->slug);
            }
        }

        if (is_product_tag()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $this->append_tax_query($query, 'product_tag', $term->slug);
            }
        }

        $brand_taxonomy = $this->get_brand_taxonomy();
        if ($brand_taxonomy && is_tax($brand_taxonomy) && empty($_GET['product_brand_filter'])) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $this->set_tax_query_clause($query, $brand_taxonomy, $term->slug);
            }
            $this->apply_search_context_to_query($query);
            return;
        }

        if ($this->is_product_tax_archive()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->taxonomy) && !empty($term->slug)) {
                $this->append_tax_query($query, $term->taxonomy, $term->slug);
            }
        }

        $this->apply_search_context_to_query($query);
    }

    /**
     * Propagate the current product search keyword onto Elementor / secondary loops.
     *
     * @param WP_Query $query WP_Query instance.
     */
    private function apply_search_context_to_query($query) {
        if (!$this->is_product_search_context()) {
            return;
        }

        $keyword = class_exists('WCLF_Query_Helper')
            ? WCLF_Query_Helper::get_product_search_keyword()
            : (isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '');

        if ('' === $keyword) {
            return;
        }

        $existing = $query->get('s');
        if (empty($existing)) {
            $query->set('s', $keyword);
        }

        $post_type = $query->get('post_type');
        if (empty($post_type)) {
            $query->set('post_type', 'product');
        }
    }

    /**
     * Append a taxonomy clause without duplicating existing archive constraints.
     *
     * @param WP_Query $query    WP_Query instance.
     * @param string   $taxonomy Taxonomy name.
     * @param string   $term     Term slug.
     */
    private function append_tax_query($query, $taxonomy, $term) {
        $this->set_tax_query_clause($query, $taxonomy, $term, false);
    }

    /**
     * Set or replace a taxonomy clause on the query.
     *
     * @param WP_Query $query    WP_Query instance.
     * @param string   $taxonomy Taxonomy name.
     * @param string   $term     Term slug.
     * @param bool     $replace  Replace existing clause for taxonomy.
     */
    private function set_tax_query_clause($query, $taxonomy, $term, $replace = true) {
        $tax_query = $this->normalize_tax_query($query->get('tax_query') ?: array());

        if ($replace) {
            foreach ($tax_query as $index => $clause) {
                if (is_array($clause) && isset($clause['taxonomy']) && $clause['taxonomy'] === $taxonomy) {
                    unset($tax_query[$index]);
                }
            }
        } else {
            foreach ($tax_query as $clause) {
                if (is_array($clause) && isset($clause['taxonomy']) && $clause['taxonomy'] === $taxonomy) {
                    return;
                }
            }
        }

        $tax_query[] = $this->build_tax_query_clause($taxonomy, $term);

        $query->set('tax_query', $this->normalize_tax_query($tax_query));
    }

    /**
     * Build a taxonomy clause from a term ID or slug.
     *
     * @param string       $taxonomy Taxonomy name.
     * @param string|int   $value    Term ID or slug.
     * @return array
     */
    private function build_tax_query_clause($taxonomy, $value) {
        $value = is_scalar($value) ? wp_unslash($value) : '';

        if (is_numeric($value)) {
            return array(
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => array((int) $value),
            );
        }

        $slug = sanitize_title($value);
        if (empty($slug)) {
            $slug = sanitize_text_field($value);
        }

        $term = get_term_by('slug', $slug, $taxonomy);
        if ((!$term || is_wp_error($term)) && !empty($value)) {
            $term = get_term_by('name', sanitize_text_field($value), $taxonomy);
        }

        if ($term && !is_wp_error($term)) {
            return array(
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => array((int) $term->term_id),
            );
        }

        return array(
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => $slug,
        );
    }

    /**
     * Read a taxonomy filter value from the query string.
     *
     * @param string $param GET parameter name.
     * @return string
     */
    private function get_tax_filter_value($param) {
        if (!isset($_GET[$param]) || '' === $_GET[$param]) {
            return '';
        }

        $value = wp_unslash($_GET[$param]);

        if (is_numeric($value)) {
            return (string) (int) $value;
        }

        return sanitize_text_field($value);
    }

    /**
     * Ensure tax_query always has a valid AND relation.
     *
     * @param array $tax_query Tax query array.
     * @return array
     */
    private function normalize_tax_query($tax_query) {
        if (empty($tax_query)) {
            return $tax_query;
        }

        $normalized = array();
        foreach ($tax_query as $key => $clause) {
            if ('relation' === $key) {
                continue;
            }
            if (is_array($clause)) {
                $normalized[] = $clause;
            }
        }

        if (count($normalized) > 1) {
            $normalized['relation'] = 'AND';
        }

        return $normalized;
    }

    /**
     * Detect the active brand taxonomy.
     *
     * @return string
     */
    private function get_brand_taxonomy() {
        $candidates = array('product_brand', 'pwb-brand', 'yith_product_brand', 'brand');

        foreach ($candidates as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                return $taxonomy;
            }
        }

        return '';
    }

    /**
     * Apply all active filters and sorting options to the query.
     *
     * @param WP_Query $query WP_Query instance.
     */
    private function apply_filters_and_sorting($query) {
        // 1. Price Filter
        $min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
        $max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : PHP_INT_MAX;

        if ($min_price > 0 || $max_price < PHP_INT_MAX) {
            $meta_query = $query->get('meta_query') ?: array();

            $meta_query[] = array(
                'key'     => '_price',
                'value'   => array($min_price, $max_price),
                'compare' => 'BETWEEN',
                'type'    => 'NUMERIC',
            );

            $query->set('meta_query', $meta_query);
        }

        // 2. Category Filter
        if (isset($_GET['product_cat_filter']) && !empty($_GET['product_cat_filter'])) {
            $this->set_tax_query_clause(
                $query,
                'product_cat',
                $this->get_tax_filter_value('product_cat_filter')
            );
        }

        // 2a. Brand Filter
        if (isset($_GET['product_brand_filter']) && !empty($_GET['product_brand_filter'])) {
            $brand_taxonomy = $this->get_brand_taxonomy();
            if (!empty($brand_taxonomy)) {
                $this->set_tax_query_clause(
                    $query,
                    $brand_taxonomy,
                    $this->get_tax_filter_value('product_brand_filter')
                );
            }
        }

        // 2b. Attribute Filters
        foreach ($_GET as $key => $value) {
            if (strpos($key, 'filter_') === 0 && !empty($value)) {
                $attribute_name = substr($key, 7);
                $taxonomy = 'pa_' . $attribute_name;
                if (taxonomy_exists($taxonomy)) {
                    $tax_query = $this->normalize_tax_query($query->get('tax_query') ?: array());
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => explode(',', sanitize_text_field($value)),
                        'operator' => 'IN',
                    );
                    $query->set('tax_query', $this->normalize_tax_query($tax_query));
                }
            }
        }

        // 3. Stock Status Filter
        if (isset($_GET['stock_filter']) && $_GET['stock_filter'] === 'instock') {
            $meta_query = $query->get('meta_query') ?: array();

            $meta_query[] = array(
                'key'     => '_stock_status',
                'value'   => 'instock',
                'compare' => '=',
            );

            $query->set('meta_query', $meta_query);
        }

        // 4. Sorting Logic
        $orderby = isset($_GET['orderby']) ? wc_clean(wp_unslash($_GET['orderby'])) : '';

        if (empty($orderby)) {
            return;
        }

        switch ($orderby) {
            case 'discount':
            case 'discount-asc':
                // Sort by discount only — do not hide products without a discount.
                $meta_query = $query->get('meta_query') ?: array();
                $meta_query[] = array(
                    'relation' => 'OR',
                    'wclf_discount' => array(
                        'key'     => 'discount_percentage',
                        'compare' => 'EXISTS',
                        'type'    => 'NUMERIC',
                    ),
                    array(
                        'key'     => 'discount_percentage',
                        'compare' => 'NOT EXISTS',
                    ),
                );
                $query->set('meta_query', $meta_query);
                $query->set('orderby', array(
                    'wclf_discount' => ('discount-asc' === $orderby) ? 'ASC' : 'DESC',
                    'date'          => 'DESC',
                ));
                break;

            case 'popularity':
                $meta_query = $query->get('meta_query') ?: array();
                $meta_query[] = array(
                    'relation' => 'OR',
                    'wclf_views' => array(
                        'key'     => 'post_views',
                        'compare' => 'EXISTS',
                        'type'    => 'NUMERIC',
                    ),
                    array(
                        'key'     => 'post_views',
                        'compare' => 'NOT EXISTS',
                    ),
                );
                $query->set('meta_query', $meta_query);
                $query->set('orderby', array(
                    'wclf_views' => 'DESC',
                    'date'       => 'DESC',
                ));
                break;

            case 'date':
                $query->set('orderby', 'date');
                $query->set('order', 'DESC');
                $query->set('meta_key', '');
                break;

            case 'sales':
                $query->set('meta_key', 'total_sales');
                $query->set('orderby', 'meta_value_num');
                $query->set('order', 'DESC');
                break;

            case 'price':
                $query->set('meta_key', '_price');
                $query->set('orderby', 'meta_value_num');
                $query->set('order', 'ASC');
                break;

            case 'price-desc':
                $query->set('meta_key', '_price');
                $query->set('orderby', 'meta_value_num');
                $query->set('order', 'DESC');
                break;
        }
    }

    /**
     * Check whether the query targets WooCommerce products.
     *
     * @param WP_Query $query WP_Query instance.
     * @return bool
     */
    private function is_product_query($query) {
        $post_type = $query->get('post_type');

        if (is_array($post_type)) {
            if (in_array('elementor_library', $post_type, true) || in_array('page', $post_type, true)) {
                return false;
            }
            return in_array('product', $post_type, true);
        }

        if (in_array($post_type, array('elementor_library', 'page', 'nav_menu_item', 'revision', 'attachment'), true)) {
            return false;
        }

        return ('product' === $post_type || (empty($post_type) && $this->is_catalog_context() && $query->is_main_query()));
    }

    /**
     * Detect WooCommerce catalog archive pages, including custom product taxonomies and product search.
     *
     * @return bool
     */
    private function is_catalog_context() {
        if (class_exists('WCLF_Query_Helper')) {
            return WCLF_Query_Helper::is_catalog_context();
        }

        if (is_shop() || is_product_category() || is_product_tag()) {
            return true;
        }

        return $this->is_product_tax_archive() || $this->is_product_search_context();
    }

    /**
     * Product search results page.
     *
     * @return bool
     */
    private function is_product_search_context() {
        if (class_exists('WCLF_Query_Helper')) {
            return WCLF_Query_Helper::is_product_search_context();
        }

        return is_search() && isset($_GET['post_type']) && 'product' === $_GET['post_type'];
    }

    /**
     * Detect custom product taxonomy archives.
     *
     * @return bool
     */
    private function is_product_tax_archive() {
        if (!is_tax()) {
            return false;
        }

        $queried_object = get_queried_object();
        if (!$queried_object || empty($queried_object->taxonomy)) {
            return false;
        }

        return in_array($queried_object->taxonomy, get_object_taxonomies('product'), true);
    }

    /**
     * Detect the WooCommerce shop page when it is built with Elementor.
     *
     * @return bool
     */
    private function is_shop_page_context() {
        if (!function_exists('wc_get_page_id')) {
            return false;
        }

        $shop_page_id = wc_get_page_id('shop');

        return $shop_page_id > 0 && is_page($shop_page_id);
    }

    /**
     * Check whether any catalog filter/sort parameter is active.
     *
     * @return bool
     */
    private function has_active_filter_params() {
        if (!empty($_GET['min_price']) || !empty($_GET['max_price'])) {
            return true;
        }

        if (!empty($_GET['product_cat_filter'])) {
            return true;
        }

        if (!empty($_GET['product_brand_filter'])) {
            return true;
        }

        if (!empty($_GET['stock_filter'])) {
            return true;
        }

        if (!empty($_GET['orderby'])) {
            return true;
        }

        foreach ($_GET as $key => $value) {
            if (0 === strpos($key, 'filter_') && !empty($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prevent applying filters twice on the same query.
     *
     * @param WP_Query $query WP_Query instance.
     * @return bool
     */
    private function is_query_already_filtered($query) {
        return (bool) $query->get('wclf_filters_applied');
    }

    /**
     * Mark a query as already processed by this plugin.
     *
     * @param WP_Query $query WP_Query instance.
     */
    private function mark_query_as_filtered($query) {
        $query->set('wclf_filters_applied', true);
    }
}
