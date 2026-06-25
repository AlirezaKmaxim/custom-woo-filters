<?php
defined('ABSPATH') || exit;

class WCLF_Shortcodes {

    /**
     * Constructor.
     */
    public function __construct() {
        add_shortcode('elementor_price_filter', array($this, 'price_filter_shortcode'));
        add_shortcode('elementor_category_filter', array($this, 'category_filter_shortcode'));
        add_shortcode('elementor_stock_filter', array($this, 'stock_filter_shortcode'));
        add_shortcode('elementor_attribute_filter', array($this, 'attribute_filter_shortcode'));
        add_shortcode('beban_product_filters', array($this, 'sorting_filters_shortcode'));
        add_shortcode('wclf_product_count', array($this, 'product_count_shortcode'));

        // Register scripts for enqueueing
        add_action('wp_enqueue_scripts', array($this, 'register_filter_scripts'));
    }

    /**
     * Register scripts.
     */
    public function register_filter_scripts() {
        wp_register_script(
            'wclf-ajax-core',
            WCLF_PLUGIN_URL . 'assets/js/wclf-ajax-core.js',
            array(),
            '1.3.1',
            true
        );
        wp_register_script(
            'wclf-price-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-price-filter.js',
            array('wclf-ajax-core'),
            '1.3.1',
            true
        );
        wp_register_script(
            'wclf-category-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-category-filter.js',
            array('wclf-ajax-core'),
            '1.3.1',
            true
        );
        wp_register_script(
            'wclf-stock-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-stock-filter.js',
            array('wclf-ajax-core'),
            '1.3.1',
            true
        );
        wp_register_script(
            'wclf-attribute-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-attribute-filter.js',
            array('wclf-ajax-core'),
            '1.3.1',
            true
        );
    }

    /**
     * Price Filter Shortcode [elementor_price_filter]
     *
     * @return string
     */
    public function price_filter_shortcode() {
        if (!class_exists('WooCommerce')) {
            return '<p>' . esc_html__('ووکامرس فعال نیست!', 'woo-custom-loop-filters') . '</p>';
        }

        // Fetch prices from cache if available, otherwise fetch from DB
        $prices = get_transient('wclf_min_max_prices');

        if (false === $prices) {
            global $wpdb;

            // Fetch min/max prices from db
            $min_price_db = $wpdb->get_var("
                SELECT MIN(CAST(meta_value AS DECIMAL(15,2))) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_price' 
                AND meta_value != '' 
                AND meta_value > 0
            ");

            $max_price_db = $wpdb->get_var("
                SELECT MAX(CAST(meta_value AS DECIMAL(15,2))) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_price' 
                AND meta_value != ''
            ");

            if (!$max_price_db || $max_price_db <= 0) {
                $max_price_db = $wpdb->get_var("
                    SELECT MAX(CAST(meta_value AS DECIMAL(15,2))) 
                    FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_regular_price' 
                    AND meta_value != ''
                ");
            }

            $max_variation_price = $wpdb->get_var("
                SELECT MAX(CAST(meta_value AS DECIMAL(15,2))) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_price' 
                AND meta_value != ''
                AND post_id IN (
                    SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product_variation'
                )
            ");

            if ($max_variation_price && $max_variation_price > $max_price_db) {
                $max_price_db = $max_variation_price;
            }

            $min_price = $min_price_db ? intval(ceil($min_price_db)) : 0;
            $max_price = $max_price_db ? intval(ceil($max_price_db)) : 10000000;

            $prices = array(
                'min' => $min_price,
                'max' => $max_price,
            );

            // Store in transient cache for 24 hours
            set_transient('wclf_min_max_prices', $prices, DAY_IN_SECONDS);
        } else {
            $min_price = $prices['min'];
            $max_price = $prices['max'];
        }

        $current_min = isset($_GET['min_price']) ? intval($_GET['min_price']) : $min_price;
        $current_max = isset($_GET['max_price']) ? intval($_GET['max_price']) : $max_price;

        $current_min = max($min_price, min($current_min, $max_price));
        $current_max = max($min_price, min($current_max, $max_price));

        // Round to nearest 1000
        $min_price = floor($min_price / 1000) * 1000;
        $max_price = ceil($max_price / 1000) * 1000;
        $current_min = floor($current_min / 1000) * 1000;
        $current_max = ceil($current_max / 1000) * 1000;

        // Enqueue registered script
        wp_enqueue_script('wclf-price-filter');

        ob_start();
        ?>
        <div class="custom-price-filter-wrapper" id="priceFilterWrapper">
            <div class="price-filter-dropdown">
                <button class="dropdown-toggle" type="button" id="priceToggle">
                    <span>فیلتر بر اساس قیمت</span>
                    <svg class="arrow-icon" width="16" height="8" viewBox="0 0 16 8" fill="none">
                        <path d="M2 2L8 6L14 2" stroke="#e7a439" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="dropdown-content open" id="priceFilterContent">
                    <div class="range-slider-container">
                        <div class="slider-track"></div>
                        <div class="range-fill" id="rangeFill"></div>
                        <input type="range" 
                               min="<?php echo esc_attr($min_price); ?>" 
                               max="<?php echo esc_attr($max_price); ?>" 
                               value="<?php echo esc_attr($current_min); ?>" 
                               id="sliderMin" 
                               step="1000">
                        <input type="range" 
                               min="<?php echo esc_attr($min_price); ?>" 
                               max="<?php echo esc_attr($max_price); ?>" 
                               value="<?php echo esc_attr($current_max); ?>" 
                               id="sliderMax" 
                               step="1000">
                    </div>
                    <div class="price-labels">
                        <span class="price-label" id="priceMin"><?php echo number_format($current_min); ?> تومان</span>
                        <span class="price-label" id="priceMax"><?php echo number_format($current_max); ?> تومان</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Category Filter Shortcode [elementor_category_filter]
     *
     * @return string
     */
    public function category_filter_shortcode() {
        if (!class_exists('WooCommerce')) {
            return '<p>' . esc_html__('ووکامرس فعال نیست!', 'woo-custom-loop-filters') . '</p>';
        }

        // Get categories that have products
        $terms = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }

        $current_cat = isset($_GET['product_cat_filter']) ? sanitize_text_field($_GET['product_cat_filter']) : '';

        // Enqueue registered script
        wp_enqueue_script('wclf-category-filter');

        ob_start();
        ?>
        <div class="custom-category-filter-wrapper" id="categoryFilterWrapper">
            <div class="cat-filter-dropdown">
                <button class="dropdown-toggle" type="button" id="catToggle">
                    <span>فیلتر بر اساس دسته‌بندی</span>
                    <svg class="arrow-icon" width="16" height="8" viewBox="0 0 16 8" fill="none">
                        <path d="M2 2L8 6L14 2" stroke="#e7a439" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="dropdown-content open" id="catFilterContent">
                    <div class="category-list">
                        <label class="cat-radio-label">
                            <input type="radio" name="cat_filter_radio" value="" <?php checked($current_cat, ''); ?>>
                            <span>همه محصولات</span>
                        </label>
                        
                        <?php foreach ($terms as $term) : ?>
                            <label class="cat-radio-label">
                                <input type="radio" 
                                       name="cat_filter_radio" 
                                       value="<?php echo esc_attr($term->slug); ?>" 
                                       <?php checked($current_cat, $term->slug); ?>>
                                <span><?php echo esc_html($term->name); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Stock Status Filter Shortcode [elementor_stock_filter]
     *
     * @return string
     */
    public function stock_filter_shortcode() {
        if (!class_exists('WooCommerce')) {
            return '<p>' . esc_html__('ووکامرس فعال نیست!', 'woo-custom-loop-filters') . '</p>';
        }

        $is_checked = isset($_GET['stock_filter']) && $_GET['stock_filter'] === 'instock';

        // Enqueue registered script
        wp_enqueue_script('wclf-stock-filter');

        ob_start();
        ?>
        <div class="custom-stock-filter-wrapper" id="stockFilterWrapper">
            <label class="stock-toggle-label">
                <span>فقط کالاهای موجود</span>
                <input type="checkbox" 
                       id="stockToggle" 
                       class="stock-toggle-input" 
                       value="instock" 
                       <?php checked($is_checked, true); ?>>
                <span class="toggle-track">
                    <span class="toggle-knob"></span>
                </span>
            </label>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Sorting Filters Shortcode [beban_product_filters]
     *
     * @return string
     */
    public function sorting_filters_shortcode() {
        if (!class_exists('WooCommerce')) {
            return '<p>' . esc_html__('ووکامرس فعال نیست!', 'woo-custom-loop-filters') . '</p>';
        }

        // Keep orderby clean and empty if not set in GET params
        $current_orderby = isset($_GET['orderby']) ? wc_clean(wp_unslash($_GET['orderby'])) : '';

        $sorting_options = array(
            'discount'   => 'بیشترین تخفیف',
            'popularity' => 'پربازدیدترین',
            'date'       => 'جدیدترین',
            'sales'      => 'پرفروش‌ترین',
            'price'      => 'ارزان‌ترین',
            'price-desc' => 'گران‌ترین',
        );

        // Check if any filters or sorting parameters are active
        $has_active_filters = false;
        foreach ($_GET as $key => $val) {
            if (in_array($key, array('min_price', 'max_price', 'product_cat_filter', 'stock_filter', 'orderby'), true) || strpos($key, 'filter_') === 0) {
                if (!empty($val)) {
                    $has_active_filters = true;
                    break;
                }
            }
        }

        $reset_url = remove_query_arg(array('min_price', 'max_price', 'product_cat_filter', 'stock_filter', 'orderby'));
        foreach ($_GET as $key => $val) {
            if (strpos($key, 'filter_') === 0) {
                $reset_url = remove_query_arg($key, $reset_url);
            }
        }

        // Enqueue AJAX core scripts since sorting links use it
        wp_enqueue_script('wclf-ajax-core');

        ob_start();
        ?>
        <div class="beban-product-filters">
            <div class="beban-filters-header">
                <img src="https://salamatfarivar3.faramoujdev.ir/wp-content/uploads/2026/06/Sort-From-Top-To-Bottom.svg" alt="مرتب سازی" class="beban-sort-icon">
                <span class="beban-sort-title">مرتب سازی :</span>
            </div>
            <div class="beban-filters-list">
                <a href="<?php echo esc_url($reset_url); ?>" class="beban-filter-item <?php echo !$has_active_filters ? 'active' : ''; ?>">
                    همه
                </a>

                <?php foreach ($sorting_options as $key => $label) : 
                    $active_class = ($current_orderby === $key) ? 'active' : '';
                    $url = ($current_orderby === $key) ? remove_query_arg('orderby') : add_query_arg('orderby', $key);
                ?>
                    <a href="<?php echo esc_url($url); ?>" class="beban-filter-item <?php echo esc_attr($active_class); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Attribute Filter Shortcode [elementor_attribute_filter attribute="color"]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function attribute_filter_shortcode($atts) {
        if (!class_exists('WooCommerce')) {
            return '<p>' . esc_html__('ووکامرس فعال نیست!', 'woo-custom-loop-filters') . '</p>';
        }

        $atts = shortcode_atts(array(
            'attribute' => '',
        ), $atts, 'elementor_attribute_filter');

        $attribute_slug = sanitize_key($atts['attribute']);
        if (empty($attribute_slug)) {
            return '<p>' . esc_html__('ویژگی مشخص نشده است!', 'woo-custom-loop-filters') . '</p>';
        }

        $taxonomy = 'pa_' . $attribute_slug;
        if (!taxonomy_exists($taxonomy)) {
            return '<p>' . sprintf(esc_html__('ویژگی "%s" یافت نشد!', 'woo-custom-loop-filters'), esc_html($attribute_slug)) . '</p>';
        }

        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
        ));

        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }

        $param_key = 'filter_' . $attribute_slug;
        $current_value = isset($_GET[$param_key]) ? sanitize_text_field($_GET[$param_key]) : '';

        // Enqueue JS
        wp_enqueue_script('wclf-attribute-filter');

        $tax_obj = get_taxonomy($taxonomy);
        $tax_label = $tax_obj ? $tax_obj->labels->singular_name : $attribute_slug;

        ob_start();
        ?>
        <div class="custom-attribute-filter-wrapper" id="attributeFilterWrapper" data-attribute="<?php echo esc_attr($attribute_slug); ?>">
            <div class="attr-filter-dropdown">
                <button class="dropdown-toggle" type="button" id="attrToggle-<?php echo esc_attr($attribute_slug); ?>">
                    <span>فیلتر بر اساس <?php echo esc_html($tax_label); ?></span>
                    <svg class="arrow-icon" width="16" height="8" viewBox="0 0 16 8" fill="none">
                        <path d="M2 2L8 6L14 2" stroke="#e7a439" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="dropdown-content open" id="attrFilterContent-<?php echo esc_attr($attribute_slug); ?>">
                    <div class="attribute-list">
                        <label class="attr-radio-label">
                            <input type="radio" name="attr_filter_radio_<?php echo esc_attr($attribute_slug); ?>" value="" <?php checked($current_value, ''); ?>>
                            <span>همه</span>
                        </label>
                        
                        <?php foreach ($terms as $term) : ?>
                            <label class="attr-radio-label">
                                <input type="radio" 
                                       name="attr_filter_radio_<?php echo esc_attr($attribute_slug); ?>" 
                                       value="<?php echo esc_attr($term->slug); ?>" 
                                       <?php checked($current_value, $term->slug); ?>>
                                <span><?php echo esc_html($term->name); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Product Count Shortcode [wclf_product_count]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function product_count_shortcode($atts) {
        if (!class_exists('WooCommerce')) {
            return '';
        }

        $atts = shortcode_atts(array(
            'show_total'     => 'yes',
            'label_filtered' => 'نمایش {filtered} محصول',
            'label_both'     => 'نمایش {filtered} محصول از {total} محصول',
            'label_total'    => 'نمایش {total} محصول',
        ), $atts, 'wclf_product_count');

        // Check if any filters are active
        $has_active_filters = false;
        foreach ($_GET as $key => $val) {
            if (in_array($key, array('min_price', 'max_price', 'product_cat_filter', 'stock_filter'), true) || strpos($key, 'filter_') === 0) {
                if (!empty($val)) {
                    $has_active_filters = true;
                    break;
                }
            }
        }

        // Build base query arguments to fetch product IDs
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        // Respect search keyword if present
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $args['s'] = sanitize_text_field($_GET['s']);
        }

        // Respect the current taxonomy archive if applicable
        if (is_tax() || is_category() || is_tag()) {
            $queried_object = get_queried_object();
            if ($queried_object && isset($queried_object->taxonomy) && isset($queried_object->slug)) {
                $args['tax_query'] = array(
                    array(
                        'taxonomy' => $queried_object->taxonomy,
                        'field'    => 'slug',
                        'terms'    => $queried_object->slug,
                    )
                );
            }
        }

        // Total count (for current archive page, before filters are applied)
        $total_query = new WP_Query($args);
        $total_count = $total_query->found_posts;

        // Apply active filters to get filtered count
        // 1. Price Filter
        $min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
        $max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : PHP_INT_MAX;

        if ($min_price > 0 || $max_price < PHP_INT_MAX) {
            $args['meta_query'] = isset($args['meta_query']) ? $args['meta_query'] : array();
            $args['meta_query'][] = array(
                'key'     => '_price',
                'value'   => array($min_price, $max_price),
                'compare' => 'BETWEEN',
                'type'    => 'NUMERIC'
            );
        }

        // 2. Category Filter
        if (isset($_GET['product_cat_filter']) && !empty($_GET['product_cat_filter'])) {
            $cat_slug = sanitize_text_field($_GET['product_cat_filter']);
            $args['tax_query'] = isset($args['tax_query']) ? $args['tax_query'] : array();
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $cat_slug,
            );
        }

        // 2b. Attribute Filters
        foreach ($_GET as $key => $value) {
            if (strpos($key, 'filter_') === 0 && !empty($value)) {
                $attribute_name = substr($key, 7);
                $taxonomy = 'pa_' . $attribute_name;
                if (taxonomy_exists($taxonomy)) {
                    $terms = explode(',', sanitize_text_field($value));
                    $args['tax_query'] = isset($args['tax_query']) ? $args['tax_query'] : array();
                    $args['tax_query'][] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => $terms,
                        'operator' => 'IN',
                    );
                }
            }
        }

        // 3. Stock Status Filter
        if (isset($_GET['stock_filter']) && $_GET['stock_filter'] === 'instock') {
            $args['meta_query'] = isset($args['meta_query']) ? $args['meta_query'] : array();
            $args['meta_query'][] = array(
                'key'     => '_stock_status',
                'value'   => 'instock',
                'compare' => '=',
            );
        }

        $filtered_query = new WP_Query($args);
        $filtered_count = $filtered_query->found_posts;

        // Choose template based on state
        if ($has_active_filters && $atts['show_total'] === 'yes') {
            $output_text = str_replace(
                array('{filtered}', '{total}'),
                array($filtered_count, $total_count),
                $atts['label_both']
            );
        } elseif ($has_active_filters && $atts['show_total'] !== 'yes') {
            $output_text = str_replace('{filtered}', $filtered_count, $atts['label_filtered']);
        } else {
            $output_text = str_replace('{total}', $total_count, $atts['label_total']);
        }

        // Enqueue AJAX core scripts since we need it for updates
        wp_enqueue_script('wclf-ajax-core');

        ob_start();
        ?>
        <div class="wclf-product-count-wrapper" id="wclfProductCountWrapper">
            <span class="wclf-product-count-text"><?php echo esc_html($output_text); ?></span>
        </div>
        <?php
        return ob_get_clean();
    }
}
