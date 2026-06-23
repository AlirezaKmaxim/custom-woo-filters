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
        add_shortcode('beban_product_filters', array($this, 'sorting_filters_shortcode'));

        // Register scripts for enqueueing
        add_action('wp_enqueue_scripts', array($this, 'register_filter_scripts'));
    }

    /**
     * Register scripts.
     */
    public function register_filter_scripts() {
        wp_register_script(
            'wclf-price-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-price-filter.js',
            array(),
            '1.0.0',
            true
        );
        wp_register_script(
            'wclf-category-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-category-filter.js',
            array(),
            '1.0.0',
            true
        );
        wp_register_script(
            'wclf-stock-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-stock-filter.js',
            array(),
            '1.0.0',
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
                    <div class="filter-actions">
                        <button type="button" class="apply-filter-btn" id="applyFilterBtn">اعمال فیلتر</button>
                        <?php if (isset($_GET['min_price']) || isset($_GET['max_price'])): ?>
                            <button type="button" class="reset-filter-btn" id="resetFilterBtn">حذف فیلتر</button>
                        <?php endif; ?>
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

        $current_orderby = isset($_GET['orderby']) ? 
            wc_clean(wp_unslash($_GET['orderby'])) : 
            ((is_product_category() || is_shop()) ? 'popularity' : apply_filters('woocommerce_default_catalog_orderby', get_option('woocommerce_default_catalog_orderby')));

        $sorting_options = array(
            'discount'   => 'بیشترین تخفیف',
            'popularity' => 'پربازدیدترین',
            'date'       => 'جدیدترین',
            'sales'      => 'پرفروش‌ترین',
            'price'      => 'ارزان‌ترین',
            'price-desc' => 'گران‌ترین',
        );

        ob_start();
        ?>
        <div class="beban-product-filters">
            <div class="beban-filters-header">
                <img src="https://salamatfarivar3.faramoujdev.ir/wp-content/uploads/2026/06/Sort-From-Top-To-Bottom.svg" alt="مرتب سازی" class="beban-sort-icon">
                <span class="beban-sort-title">مرتب سازی :</span>
            </div>
            <div class="beban-filters-list">
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
}
