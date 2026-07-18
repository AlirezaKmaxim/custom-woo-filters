<?php
defined('ABSPATH') || exit;

/**
 * Shared catalog query helpers: page product IDs, price bounds, term counts.
 *
 * Used by shortcodes (price/brand/category facets) to avoid duplicate WP_Query
 * work per request. Prefer this class for any new facet that depends on the
 * current archive / search result set.
 *
 * Request-level caching avoids duplicate WP_Query / SQL work across filters.
 */
class WCLF_Query_Helper {

    /**
     * Cached product ID sets keyed by scope signature.
     *
     * @var array<string, int[]|true>
     */
    private static $product_ids_cache = array();

    /**
     * Cached term-count maps keyed by taxonomy + scope + parent.
     *
     * @var array<string, array<int,int>>
     */
    private static $term_counts_cache = array();

    /**
     * Cached price ranges keyed by scope signature.
     *
     * @var array<string, array{min:float,max:float}>
     */
    private static $price_range_cache = array();

    /**
     * Get product IDs for the current page catalog context.
     *
     * @param array $options {
     *     @type bool $exclude_price   Ignore min_price/max_price GET params.
     *     @type bool $exclude_brand   Ignore product_brand_filter GET param.
     *     @type bool $exclude_category_filter Prefer archive category over product_cat_filter.
     *     @type string|string[] $exclude_attribute Attribute slug(s) whose filter_* GET param to ignore.
     *     @type bool $allow_unscoped_shop Return true instead of loading all shop IDs.
     * }
     * @return int[]|true Empty array = no products; true = unscoped full shop.
     */
    public static function get_page_product_ids($options = array()) {
        $options = self::normalize_options($options);
        $cache_key = self::scope_cache_key($options);

        if (array_key_exists($cache_key, self::$product_ids_cache)) {
            return self::$product_ids_cache[$cache_key];
        }

        $args = self::build_product_query_args($options);

        // No catalog constraints at all: only unscoped shop may use the full-shop sentinel.
        if (empty($args['tax_query']) && empty($args['meta_query']) && empty($args['s'])) {
            if ($options['allow_unscoped_shop'] && self::is_shop_context()) {
                self::$product_ids_cache[$cache_key] = true;
                return true;
            }
            // Tag / search / custom tax without resolvable constraints => no facet products.
            self::$product_ids_cache[$cache_key] = array();
            return array();
        }

        $query = new WP_Query($args);
        $ids = empty($query->posts) ? array() : array_map('intval', $query->posts);

        self::$product_ids_cache[$cache_key] = $ids;
        return $ids;
    }

    /**
     * Min/max prices for the current page (or an explicit product ID set).
     *
     * @param int[]|true|null $product_ids Null = resolve via price scope (exclude price filter).
     * @return array{min:float,max:float}
     */
    public static function get_min_max_prices($product_ids = null) {
        if (null === $product_ids) {
            $product_ids = self::get_page_product_ids(
                array(
                    'exclude_price'        => true,
                    'exclude_brand'        => false,
                    'allow_unscoped_shop'  => true,
                )
            );
        }

        $cache_key = is_array($product_ids)
            ? 'ids:' . md5(implode(',', $product_ids))
            : 'scope:' . (true === $product_ids ? 'shop' : 'empty');

        if (isset(self::$price_range_cache[$cache_key])) {
            return self::$price_range_cache[$cache_key];
        }

        if (empty($product_ids) && true !== $product_ids) {
            $range = array('min' => 0.0, 'max' => 0.0);
            self::$price_range_cache[$cache_key] = $range;
            return $range;
        }

        $range = self::query_price_range_for_products($product_ids);
        self::$price_range_cache[$cache_key] = $range;
        return $range;
    }

    /**
     * Count taxonomy terms among a product ID set (page-local counts).
     *
     * @param string     $taxonomy    Taxonomy name.
     * @param int[]|true $product_ids Product IDs or true for unscoped shop (uses DB term counts).
     * @param array      $args {
     *     @type int|null $parent Only terms with this parent term_id.
     *     @type int[]    $include Limit to these term IDs.
     * }
     * @return array<int,int> term_id => count, sorted DESC by count.
     */
    public static function get_term_counts_for_products($taxonomy, $product_ids, $args = array()) {
        $taxonomy = sanitize_key($taxonomy);
        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            return array();
        }

        $args = wp_parse_args(
            $args,
            array(
                'parent'  => null,
                'include' => array(),
            )
        );

        if (true === $product_ids) {
            return self::get_global_term_counts($taxonomy, $args);
        }

        $product_ids = array_values(array_filter(array_map('intval', (array) $product_ids)));
        if (empty($product_ids)) {
            return array();
        }

        $cache_key = md5(
            $taxonomy . '|' . implode(',', $product_ids) . '|' . wp_json_encode($args)
        );
        if (isset(self::$term_counts_cache[$cache_key])) {
            return self::$term_counts_cache[$cache_key];
        }

        $counts = self::query_term_counts_sql($taxonomy, $product_ids, $args);
        self::$term_counts_cache[$cache_key] = $counts;
        return $counts;
    }

    /**
     * Detect WooCommerce shop page context.
     *
     * @return bool
     */
    public static function is_shop_context() {
        if (function_exists('is_shop') && is_shop()) {
            return true;
        }

        if (function_exists('wc_get_page_id')) {
            $shop_page_id = wc_get_page_id('shop');
            return $shop_page_id > 0 && is_page($shop_page_id);
        }

        return false;
    }

    /**
     * Product search results (e.g. /?s=glue&post_type=product).
     *
     * @return bool
     */
    public static function is_product_search_context() {
        if (!is_search()) {
            return false;
        }

        if (isset($_GET['post_type'])) {
            $post_type = wp_unslash($_GET['post_type']);
            if (is_array($post_type) && in_array('product', $post_type, true)) {
                return true;
            }
            if ('product' === $post_type) {
                return true;
            }
        }

        global $wp_query;
        if ($wp_query instanceof WP_Query) {
            $post_type = $wp_query->get('post_type');
            if (is_array($post_type) && in_array('product', $post_type, true)) {
                return true;
            }
            if ('product' === $post_type) {
                return true;
            }
            // WC product search often sets wc_query.
            if ($wp_query->get('wc_query') === 'product_query') {
                return true;
            }
        }

        return false;
    }

    /**
     * Any catalog surface where dynamic price/brand filters should follow the page products.
     * Shop, category, tag, brand/custom product taxonomies, and product search.
     *
     * @return bool
     */
    public static function is_catalog_context() {
        if (self::is_shop_context()) {
            return true;
        }

        if (function_exists('is_product_category') && is_product_category()) {
            return true;
        }

        if (function_exists('is_product_tag') && is_product_tag()) {
            return true;
        }

        if (self::is_product_search_context()) {
            return true;
        }

        if (is_tax()) {
            $queried = get_queried_object();
            if ($queried && !is_wp_error($queried) && !empty($queried->taxonomy)) {
                return in_array($queried->taxonomy, get_object_taxonomies('product'), true);
            }
        }

        return false;
    }

    /**
     * Resolve the active search keyword for product search pages.
     *
     * @return string
     */
    public static function get_product_search_keyword() {
        if (!empty($_GET['s'])) {
            return sanitize_text_field(wp_unslash($_GET['s']));
        }

        if (function_exists('get_search_query')) {
            $keyword = get_search_query(false);
            if (is_string($keyword) && '' !== $keyword) {
                return sanitize_text_field($keyword);
            }
        }

        global $wp_query;
        if ($wp_query instanceof WP_Query) {
            $keyword = $wp_query->get('s');
            if (is_string($keyword) && '' !== $keyword) {
                return sanitize_text_field($keyword);
            }
        }

        return '';
    }

    /**
     * Brand taxonomy candidates in priority order.
     *
     * @return string[]
     */
    public static function get_brand_taxonomy_candidates() {
        return array('product_brand', 'pwb-brand', 'yith_product_brand', 'brand');
    }

    /**
     * First existing brand taxonomy.
     *
     * @return string
     */
    public static function get_brand_taxonomy() {
        foreach (self::get_brand_taxonomy_candidates() as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                return $taxonomy;
            }
        }
        return '';
    }

    /**
     * @param array $options Options.
     * @return array
     */
    private static function normalize_options($options) {
        $normalized = wp_parse_args(
            $options,
            array(
                'exclude_price'            => false,
                'exclude_brand'            => false,
                'exclude_category_filter'  => false,
                'exclude_attribute'        => array(),
                'allow_unscoped_shop'      => true,
            )
        );

        if (is_string($normalized['exclude_attribute']) && '' !== $normalized['exclude_attribute']) {
            $normalized['exclude_attribute'] = array(sanitize_key($normalized['exclude_attribute']));
        } elseif (!is_array($normalized['exclude_attribute'])) {
            $normalized['exclude_attribute'] = array();
        } else {
            $normalized['exclude_attribute'] = array_values(
                array_filter(array_map('sanitize_key', $normalized['exclude_attribute']))
            );
        }

        return $normalized;
    }

    /**
     * @param array $options Normalized options.
     * @return string
     */
    private static function scope_cache_key($options) {
        $signature = array(
            'opt' => $options,
            'get' => self::relevant_get_signature($options),
            'q'   => self::queried_object_signature(),
        );
        return md5(wp_json_encode($signature));
    }

    /**
     * @param array $options Options.
     * @return array
     */
    private static function relevant_get_signature($options) {
        $parts = array();

        if (!$options['exclude_price']) {
            if (!empty($_GET['min_price'])) {
                $parts['min_price'] = floatval($_GET['min_price']);
            }
            if (!empty($_GET['max_price'])) {
                $parts['max_price'] = floatval($_GET['max_price']);
            }
        }

        if (!$options['exclude_category_filter'] && !empty($_GET['product_cat_filter'])) {
            $parts['cat'] = self::sanitize_term_value($_GET['product_cat_filter']);
        }

        if (!$options['exclude_brand'] && !empty($_GET['product_brand_filter'])) {
            $parts['brand'] = self::sanitize_term_value($_GET['product_brand_filter']);
        }

        if (!empty($_GET['stock_filter']) && 'instock' === $_GET['stock_filter']) {
            $parts['stock'] = 'instock';
        }

        $attrs = array();
        foreach ($_GET as $key => $value) {
            if (0 === strpos($key, 'filter_') && '' !== $value) {
                $attribute_name = sanitize_key(substr($key, 7));
                if (in_array($attribute_name, $options['exclude_attribute'], true)) {
                    continue;
                }
                $attrs[$key] = sanitize_text_field(wp_unslash($value));
            }
        }
        if (!empty($attrs)) {
            ksort($attrs);
            $parts['attrs'] = $attrs;
        }

        if (!empty($_GET['s'])) {
            $parts['s'] = sanitize_text_field(wp_unslash($_GET['s']));
        } elseif (self::is_product_search_context()) {
            $keyword = self::get_product_search_keyword();
            if ('' !== $keyword) {
                $parts['s'] = $keyword;
            }
        }

        return $parts;
    }

    /**
     * @return string
     */
    private static function queried_object_signature() {
        $queried = get_queried_object();
        if ($queried && !is_wp_error($queried) && !empty($queried->taxonomy) && !empty($queried->term_id)) {
            return $queried->taxonomy . ':' . (int) $queried->term_id;
        }
        if (self::is_product_search_context()) {
            return 'search:' . self::get_product_search_keyword();
        }
        return self::is_shop_context() ? 'shop' : '';
    }

    /**
     * Build WP_Query args for the requested scope.
     *
     * @param array $options Options.
     * @return array
     */
    private static function build_product_query_args($options) {
        $args = array(
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );

        $tax_query = array();

        $cat_term = null;
        if (!$options['exclude_category_filter'] && !empty($_GET['product_cat_filter'])) {
            $cat_term = self::resolve_product_cat_term($_GET['product_cat_filter']);
        }
        if (!$cat_term && function_exists('is_product_category') && is_product_category()) {
            $queried = get_queried_object();
            if ($queried && !is_wp_error($queried)) {
                $cat_term = $queried;
            }
        }

        if ($cat_term) {
            $tax_query[] = array(
                'taxonomy'         => 'product_cat',
                'field'            => 'term_id',
                'terms'            => array((int) $cat_term->term_id),
                'include_children' => true,
            );
        } elseif (function_exists('is_product_tag') && is_product_tag()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->term_id)) {
                $tax_query[] = array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'term_id',
                    'terms'    => array((int) $term->term_id),
                );
            }
        } elseif (is_tax()) {
            $queried = get_queried_object();
            $brand_taxonomy = self::get_brand_taxonomy();
            if (
                $queried
                && !is_wp_error($queried)
                && !empty($queried->taxonomy)
                && !empty($queried->term_id)
                && $queried->taxonomy !== $brand_taxonomy
                && in_array($queried->taxonomy, get_object_taxonomies('product'), true)
            ) {
                $tax_query[] = array(
                    'taxonomy' => $queried->taxonomy,
                    'field'    => 'term_id',
                    'terms'    => array((int) $queried->term_id),
                );
            } elseif ($brand_taxonomy && is_tax($brand_taxonomy) && $queried && !empty($queried->term_id)) {
                // Brand archive: still scope products to this brand for price/other facets.
                if (!$options['exclude_brand']) {
                    $tax_query[] = array(
                        'taxonomy' => $brand_taxonomy,
                        'field'    => 'term_id',
                        'terms'    => array((int) $queried->term_id),
                    );
                }
            }
        }

        if (!$options['exclude_brand'] && !empty($_GET['product_brand_filter'])) {
            $brand_taxonomy = self::get_brand_taxonomy();
            if ($brand_taxonomy) {
                $tax_query[] = self::build_tax_clause(
                    $brand_taxonomy,
                    self::sanitize_term_value($_GET['product_brand_filter'])
                );
            }
        }

        foreach ($_GET as $key => $value) {
            if (0 === strpos($key, 'filter_') && '' !== $value) {
                $attribute_name = sanitize_key(substr($key, 7));
                if (in_array($attribute_name, $options['exclude_attribute'], true)) {
                    continue;
                }
                $attr_taxonomy = 'pa_' . $attribute_name;
                if (taxonomy_exists($attr_taxonomy)) {
                    $tax_query[] = array(
                        'taxonomy' => $attr_taxonomy,
                        'field'    => 'slug',
                        'terms'    => explode(',', sanitize_text_field(wp_unslash($value))),
                        'operator' => 'IN',
                    );
                }
            }
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $meta_query = array();

        if (!$options['exclude_price']) {
            $min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
            $max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : PHP_INT_MAX;
            if ($min_price > 0 || $max_price < PHP_INT_MAX) {
                $meta_query[] = array(
                    'key'     => '_price',
                    'value'   => array($min_price, $max_price),
                    'compare' => 'BETWEEN',
                    'type'    => 'NUMERIC',
                );
            }
        }

        if (isset($_GET['stock_filter']) && 'instock' === $_GET['stock_filter']) {
            $meta_query[] = array(
                'key'     => '_stock_status',
                'value'   => 'instock',
                'compare' => '=',
            );
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $search_keyword = '';
        if (!empty($_GET['s'])) {
            $search_keyword = sanitize_text_field(wp_unslash($_GET['s']));
        } elseif (self::is_product_search_context()) {
            $search_keyword = self::get_product_search_keyword();
        }

        if ('' !== $search_keyword) {
            $args['s'] = $search_keyword;
        }

        return $args;
    }

    /**
     * SQL min/max via lookup table + post__in (chunked for large sets).
     *
     * @param int[]|true $product_ids Product IDs or full-shop sentinel.
     * @return array{min:float,max:float}
     */
    private static function query_price_range_for_products($product_ids) {
        global $wpdb;

        $lookup = !empty($wpdb->wc_product_meta_lookup)
            ? $wpdb->wc_product_meta_lookup
            : $wpdb->prefix . 'wc_product_meta_lookup';
        $use_lookup = !empty($wpdb->wc_product_meta_lookup);

        if (true === $product_ids) {
            if ($use_lookup) {
                $row = $wpdb->get_row(
                    "SELECT MIN(min_price) AS min_price, MAX(max_price) AS max_price FROM {$lookup}"
                );
            } else {
                $row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT MIN(CAST(meta_value AS DECIMAL(15,2))) AS min_price,
                                MAX(CAST(meta_value AS DECIMAL(15,2))) AS max_price
                         FROM {$wpdb->postmeta}
                         WHERE meta_key = %s AND meta_value != '' AND meta_value > 0",
                        '_price'
                    )
                );
            }

            return array(
                'min' => ($row && '' !== $row->min_price) ? floatval($row->min_price) : 0.0,
                'max' => ($row && '' !== $row->max_price) ? floatval($row->max_price) : 0.0,
            );
        }

        $product_ids = array_values(array_filter(array_map('intval', (array) $product_ids)));
        if (empty($product_ids)) {
            return array('min' => 0.0, 'max' => 0.0);
        }

        $min = null;
        $max = null;
        foreach (array_chunk($product_ids, 2000) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));

            if ($use_lookup) {
                $sql = $wpdb->prepare(
                    "SELECT MIN(min_price) AS min_price, MAX(max_price) AS max_price
                     FROM {$lookup}
                     WHERE product_id IN ({$placeholders})",
                    $chunk
                );
            } else {
                $sql = $wpdb->prepare(
                    "SELECT MIN(CAST(meta_value AS DECIMAL(15,2))) AS min_price,
                            MAX(CAST(meta_value AS DECIMAL(15,2))) AS max_price
                     FROM {$wpdb->postmeta}
                     WHERE meta_key = %s
                       AND meta_value != ''
                       AND meta_value > 0
                       AND post_id IN ({$placeholders})",
                    array_merge(array('_price'), $chunk)
                );
            }

            $row = $wpdb->get_row($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if (!$row) {
                continue;
            }

            if ('' !== $row->min_price && null !== $row->min_price) {
                $chunk_min = floatval($row->min_price);
                $min = (null === $min) ? $chunk_min : min($min, $chunk_min);
            }
            if ('' !== $row->max_price && null !== $row->max_price) {
                $chunk_max = floatval($row->max_price);
                $max = (null === $max) ? $chunk_max : max($max, $chunk_max);
            }
        }

        return array(
            'min' => null !== $min ? $min : 0.0,
            'max' => null !== $max ? $max : 0.0,
        );
    }

    /**
     * Efficient term counts using term_taxonomy_id + object_id IN (...).
     *
     * @param string $taxonomy    Taxonomy.
     * @param int[]  $product_ids Product IDs.
     * @param array  $args        parent / include.
     * @return array<int,int>
     */
    private static function query_term_counts_sql($taxonomy, $product_ids, $args) {
        global $wpdb;

        $counts = array();
        $parent_sql = '';
        $prepare_args = array($taxonomy);

        if (null !== $args['parent'] && '' !== $args['parent']) {
            $parent_sql = ' AND tt.parent = %d ';
            $prepare_args[] = (int) $args['parent'];
        }

        $include_sql = '';
        $include = array_filter(array_map('intval', (array) $args['include']));
        if (!empty($include)) {
            $include_sql = ' AND tt.term_id IN (' . implode(',', array_fill(0, count($include), '%d')) . ') ';
            $prepare_args = array_merge($prepare_args, $include);
        }

        foreach (array_chunk($product_ids, 2000) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = $wpdb->prepare(
                "SELECT tt.term_taxonomy_id, tt.term_id, COUNT(DISTINCT tr.object_id) AS product_count
                 FROM {$wpdb->term_relationships} AS tr
                 INNER JOIN {$wpdb->term_taxonomy} AS tt
                    ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 WHERE tt.taxonomy = %s
                   {$parent_sql}
                   {$include_sql}
                   AND tr.object_id IN ({$placeholders})
                 GROUP BY tt.term_taxonomy_id, tt.term_id
                 HAVING product_count > 0",
                array_merge($prepare_args, $chunk)
            );

            $rows = $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if (empty($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                $term_id = (int) $row->term_id;
                $count = (int) $row->product_count;
                $counts[$term_id] = isset($counts[$term_id]) ? $counts[$term_id] + $count : $count;
            }
        }

        if (!empty($counts)) {
            arsort($counts, SORT_NUMERIC);
        }

        return $counts;
    }

    /**
     * Global term counts (unscoped shop) with optional parent filter.
     *
     * @param string $taxonomy Taxonomy.
     * @param array  $args     parent / include.
     * @return array<int,int>
     */
    private static function get_global_term_counts($taxonomy, $args) {
        $term_args = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'pad_counts' => true,
        );

        if (null !== $args['parent'] && '' !== $args['parent']) {
            $term_args['parent'] = (int) $args['parent'];
            $term_args['hide_empty'] = false;
        }

        if (!empty($args['include'])) {
            $term_args['include'] = array_map('intval', (array) $args['include']);
            $term_args['hide_empty'] = false;
        }

        $terms = get_terms($term_args);
        if (empty($terms) || is_wp_error($terms)) {
            return array();
        }

        $counts = array();
        foreach ($terms as $term) {
            $count = isset($term->count) ? (int) $term->count : 0;
            if ($count > 0) {
                $counts[(int) $term->term_id] = $count;
            }
        }

        arsort($counts, SORT_NUMERIC);
        return $counts;
    }

    /**
     * @param mixed $value Raw GET value.
     * @return WP_Term|null
     */
    private static function resolve_product_cat_term($value) {
        $value = self::sanitize_term_value($value);
        if ('' === $value) {
            return null;
        }

        if (is_numeric($value)) {
            $term = get_term((int) $value, 'product_cat');
        } else {
            $term = get_term_by('slug', $value, 'product_cat');
        }

        return ($term && !is_wp_error($term)) ? $term : null;
    }

    /**
     * @param string $taxonomy Taxonomy.
     * @param string $value    Term ID or slug.
     * @return array
     */
    private static function build_tax_clause($taxonomy, $value) {
        if (is_numeric($value)) {
            return array(
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => array((int) $value),
            );
        }

        $slug = sanitize_title($value);
        $term = get_term_by('slug', $slug, $taxonomy);
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
     * @param mixed $value Raw value.
     * @return string
     */
    private static function sanitize_term_value($value) {
        $value = wp_unslash($value);
        if (is_numeric($value)) {
            return (string) (int) $value;
        }
        return sanitize_text_field($value);
    }
}
