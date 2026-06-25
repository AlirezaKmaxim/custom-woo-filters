<?php
defined('ABSPATH') || exit;

class WCLF_Query_Handler {

    /**
     * Constructor.
     */
    public function __construct() {
        // Hook to Elementor custom query
        add_action('elementor/query/custom_loop_filters', array($this, 'apply_custom_query_filters'));

        // Hook to WooCommerce default shop product query
        add_action('woocommerce_product_query', array($this, 'apply_woocommerce_query_filters'));

        // Add sorting options to WooCommerce catalog orderby
        add_filter('woocommerce_catalog_orderby', array($this, 'add_custom_sorting_options'));
    }

    /**
     * Register custom sorting options in WooCommerce.
     *
     * @param array $options WooCommerce catalog sorting options.
     * @return array
     */
    public function add_custom_sorting_options($options) {
        $custom_options = array(
            'discount'   => 'بیشترین تخفیف',
            'popularity' => 'پربازدیدترین',
            'date'       => 'جدیدترین',
            'sales'      => 'پرفروش‌ترین',
            'price'      => 'ارزان‌ترین',
            'price-desc' => 'گران‌ترین',
        );
        return $custom_options + $options;
    }

    /**
     * Handle query modification for Elementor custom loop filters.
     *
     * @param WP_Query $query WP_Query instance.
     */
    public function apply_custom_query_filters($query) {
        $this->apply_filters_and_sorting($query);
    }

    /**
     * Handle query modification for WooCommerce product query.
     *
     * @param WP_Query $query WP_Query instance.
     */
    public function apply_woocommerce_query_filters($query) {
        // Apply only on frontend and main query
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Apply only on shop archives
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }

        $this->apply_filters_and_sorting($query);
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
                'type'    => 'NUMERIC'
            );

            $query->set('meta_query', $meta_query);
        }

        // 2. Category Filter
        if (isset($_GET['product_cat_filter']) && !empty($_GET['product_cat_filter'])) {
            $cat_slug = sanitize_text_field($_GET['product_cat_filter']);
            $tax_query = $query->get('tax_query') ?: array();

            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $cat_slug,
            );

            $query->set('tax_query', $tax_query);
        }

        // 2b. Attribute Filters
        foreach ($_GET as $key => $value) {
            if (strpos($key, 'filter_') === 0 && !empty($value)) {
                $attribute_name = substr($key, 7);
                $taxonomy = 'pa_' . $attribute_name;
                if (taxonomy_exists($taxonomy)) {
                    $terms = explode(',', sanitize_text_field($value));
                    $tax_query = $query->get('tax_query') ?: array();
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => $terms,
                        'operator' => 'IN',
                    );
                    $query->set('tax_query', $tax_query);
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
                $meta_query = $query->get('meta_query') ?: array();
                $meta_query[] = array(
                    'relation' => 'OR',
                    'wclf_discount' => array(
                        'key'     => '_custom_discount_percentage',
                        'compare' => 'EXISTS',
                        'type'    => 'NUMERIC',
                    ),
                    array(
                        'key'     => '_custom_discount_percentage',
                        'compare' => 'NOT EXISTS',
                    )
                );
                $query->set('meta_query', $meta_query);
                $query->set('orderby', array(
                    'wclf_discount' => 'DESC',
                    'date'          => 'DESC'
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
                    )
                );
                $query->set('meta_query', $meta_query);
                $query->set('orderby', array(
                    'wclf_views' => 'DESC',
                    'date'       => 'DESC'
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
}
