<?php
defined('ABSPATH') || exit;

class WCLF_Shortcodes {

    /**
     * Request-level cache for contextual min/max prices.
     *
     * @var array|null
     */
    private $contextual_price_range = null;

    /**
     * Constructor.
     */
    public function __construct() {
        add_shortcode('elementor_price_filter', array($this, 'price_filter_shortcode'));
        add_shortcode('elementor_category_filter', array($this, 'category_filter_shortcode'));
        add_shortcode('elementor_brand_filter', array($this, 'brand_filter_shortcode'));
        add_shortcode('elementor_stock_filter', array($this, 'stock_filter_shortcode'));
        add_shortcode('elementor_attribute_filter', array($this, 'attribute_filter_shortcode'));
        add_shortcode('beban_product_filters', array($this, 'sorting_filters_shortcode'));
        add_shortcode('wclf_product_count', array($this, 'product_count_shortcode'));

        // Align price slider bounds with the current catalog query (same hooks WC price widget uses).
        add_filter('woocommerce_price_filter_widget_min_amount', array($this, 'filter_widget_min_amount'), 20);
        add_filter('woocommerce_price_filter_widget_max_amount', array($this, 'filter_widget_max_amount'), 20);

        add_filter('body_class', array($this, 'add_filter_context_body_classes'));

        // Register scripts for enqueueing
        add_action('wp_enqueue_scripts', array($this, 'register_filter_scripts'));
    }

    /**
     * Mark brand / leaf-category archives for CSS / theme targeting.
     *
     * @param array $classes Body classes.
     * @return array
     */
    public function add_filter_context_body_classes($classes) {
        if ($this->is_brand_archive_context()) {
            $classes[] = 'wclf-brand-archive';
        }

        $archive_term = $this->get_category_archive_context_term();
        if ($archive_term && $this->is_leaf_product_category($archive_term)) {
            $classes[] = 'wclf-leaf-category';
        }

        return $classes;
    }

    /**
     * Override WooCommerce price-filter widget minimum using the current page query.
     *
     * @param float|int $min Default min amount.
     * @return float|int
     */
    public function filter_widget_min_amount($min) {
        $range = $this->get_contextual_price_range();
        return isset($range['min']) ? $range['min'] : $min;
    }

    /**
     * Override WooCommerce price-filter widget maximum using the current page query.
     *
     * @param float|int $max Default max amount.
     * @return float|int
     */
    public function filter_widget_max_amount($max) {
        $range = $this->get_contextual_price_range();
        return isset($range['max']) ? $range['max'] : $max;
    }

    /**
     * Register scripts.
     */
    public function register_filter_scripts() {
        $version = defined('WCLF_VERSION') ? WCLF_VERSION : '2.9.12';

        wp_register_script(
            'wclf-ajax-core',
            WCLF_PLUGIN_URL . 'assets/js/wclf-ajax-core.js',
            array(),
            $version,
            true
        );

        $spinner_color = class_exists('WCLF_Admin')
            ? WCLF_Admin::get_spinner_color()
            : '#333333';

        wp_localize_script(
            'wclf-ajax-core',
            'wclfDebugConfig',
            array(
                'enabled' => (defined('WP_DEBUG') && WP_DEBUG) || (isset($_GET['wclf_debug']) && '1' === $_GET['wclf_debug']),
                'spinnerColor' => $spinner_color,
            )
        );

        wp_register_script(
            'wclf-price-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-price-filter.js',
            array('wclf-ajax-core'),
            $version,
            true
        );
        wp_register_script(
            'wclf-category-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-category-filter.js',
            array('wclf-ajax-core'),
            $version,
            true
        );
        wp_register_script(
            'wclf-brand-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-brand-filter.js',
            array('wclf-ajax-core'),
            $version,
            true
        );
        wp_register_script(
            'wclf-stock-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-stock-filter.js',
            array('wclf-ajax-core'),
            $version,
            true
        );
        wp_register_script(
            'wclf-attribute-filter',
            WCLF_PLUGIN_URL . 'assets/js/wclf-attribute-filter.js',
            array('wclf-ajax-core'),
            $version,
            true
        );
    }

    /**
     * Price Filter Shortcode [elementor_price_filter]
     *
     * Min/max come from the current page product set (archive / active filters),
     * then pass through the same WooCommerce price-widget amount filters.
     *
     * @return string
     */
    public function price_filter_shortcode() {
        if (!class_exists('WooCommerce')) {
            return '<p>' . esc_html__('ووکامرس فعال نیست!', 'woo-custom-loop-filters') . '</p>';
        }

        $range = $this->get_contextual_price_range();

        $min_price = (int) apply_filters('woocommerce_price_filter_widget_min_amount', $range['min']);
        $max_price = (int) apply_filters('woocommerce_price_filter_widget_max_amount', $range['max']);

        if ($max_price <= $min_price) {
            $max_price = $min_price + 1000;
        }

        $current_min = isset($_GET['min_price']) ? intval($_GET['min_price']) : $min_price;
        $current_max = isset($_GET['max_price']) ? intval($_GET['max_price']) : $max_price;

        $current_min = max($min_price, min($current_min, $max_price));
        $current_max = max($min_price, min($current_max, $max_price));

        $current_min = (int) (floor($current_min / 1000) * 1000);
        $current_max = (int) (ceil($current_max / 1000) * 1000);

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
     * Get min/max prices for the current catalog context (cached per request + transient).
     * Uses WCLF_Query_Helper shared page product IDs + lookup-table SQL.
     *
     * @return array{min:int,max:int}
     */
    private function get_contextual_price_range() {
        if (null !== $this->contextual_price_range) {
            return $this->contextual_price_range;
        }

        $cache_key = $this->get_price_range_cache_key();
        $cached = get_transient($cache_key);

        if (is_array($cached) && isset($cached['min'], $cached['max'])) {
            $this->contextual_price_range = array(
                'min' => (int) $cached['min'],
                'max' => (int) $cached['max'],
            );
            return $this->contextual_price_range;
        }

        $raw = WCLF_Query_Helper::get_min_max_prices();
        $min_raw = isset($raw['min']) ? floatval($raw['min']) : 0;
        $max_raw = isset($raw['max']) ? floatval($raw['max']) : 0;

        if ($max_raw <= 0) {
            $fallback = WCLF_Query_Helper::get_min_max_prices(true);
            $min_raw = $fallback['min'];
            $max_raw = $fallback['max'];
            if ($max_raw <= 0) {
                $max_raw = 10000000;
            }
        }

        $min_price = (int) (floor(max(0, $min_raw) / 1000) * 1000);
        $max_price = (int) (ceil(max($min_raw, $max_raw) / 1000) * 1000);

        if ($max_price <= $min_price) {
            $max_price = $min_price + 1000;
        }

        $this->contextual_price_range = array(
            'min' => $min_price,
            'max' => $max_price,
        );

        set_transient($cache_key, $this->contextual_price_range, DAY_IN_SECONDS);

        return $this->contextual_price_range;
    }

    /**
     * Build a stable transient key for the current archive + non-price filters.
     *
     * @return string
     */
    private function get_price_range_cache_key() {
        $parts = $this->get_price_range_context_signature();
        return 'wclf_min_max_prices_' . md5(wp_json_encode($parts));
    }

    /**
     * Signature of catalog constraints that affect the price slider bounds.
     * Price GET params are intentionally excluded so the slider range does not shrink itself.
     *
     * @return array
     */
    private function get_price_range_context_signature() {
        $parts = array(
            'shop' => $this->is_shop_context() ? 1 : 0,
        );

        if (class_exists('WCLF_Query_Helper') && WCLF_Query_Helper::is_product_search_context()) {
            $parts['search'] = WCLF_Query_Helper::get_product_search_keyword();
        }

        $queried = get_queried_object();
        if ($queried && !is_wp_error($queried) && !empty($queried->taxonomy) && !empty($queried->term_id)) {
            $parts['archive'] = $queried->taxonomy . ':' . (int) $queried->term_id;
        }

        if (isset($_GET['product_cat_filter']) && '' !== $_GET['product_cat_filter']) {
            $parts['cat'] = $this->sanitize_filter_term_value($_GET['product_cat_filter']);
        }

        if (isset($_GET['product_brand_filter']) && '' !== $_GET['product_brand_filter']) {
            $parts['brand'] = $this->sanitize_filter_term_value($_GET['product_brand_filter']);
        }

        if (isset($_GET['stock_filter']) && 'instock' === $_GET['stock_filter']) {
            $parts['stock'] = 'instock';
        }

        $attrs = array();
        foreach ($_GET as $key => $value) {
            if (0 === strpos($key, 'filter_') && '' !== $value) {
                $attrs[$key] = sanitize_text_field(wp_unslash($value));
            }
        }
        if (!empty($attrs)) {
            ksort($attrs);
            $parts['attrs'] = $attrs;
        }

        if (isset($_GET['s']) && '' !== $_GET['s']) {
            $parts['s'] = sanitize_text_field(wp_unslash($_GET['s']));
        }

        return $parts;
    }

    /**
     * Category Filter Shortcode [elementor_category_filter]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function category_filter_shortcode($atts) {
        if (!class_exists('WooCommerce')) {
            return '<p>' . esc_html__('ووکامرس فعال نیست!', 'woo-custom-loop-filters') . '</p>';
        }

        $atts = shortcode_atts(array(
            'contextual' => 'yes',
        ), $atts, 'elementor_category_filter');

        // Leaf category archives (no direct children): hide the filter completely.
        if ('yes' === $atts['contextual']) {
            $archive_term = $this->get_category_archive_context_term();
            if ($archive_term && $this->is_leaf_product_category($archive_term)) {
                return $this->render_hidden_category_filter_placeholder();
            }
        }

        if ('yes' === $atts['contextual']) {
            $terms = $this->get_contextual_product_cat_terms();
        } else {
            $terms = get_terms($this->get_filter_terms_args('product_cat'));
        }

        if (empty($terms) || is_wp_error($terms)) {
            // Also hide when archive children exist but none have products.
            if ($this->get_category_archive_context_term()) {
                return $this->render_hidden_category_filter_placeholder();
            }
            return '';
        }

        $current_cat = isset($_GET['product_cat_filter']) ? $this->sanitize_filter_term_value($_GET['product_cat_filter']) : '';
        $archive_term = $this->get_category_archive_context_term();
        $all_label = $archive_term
            ? __('همه در این دسته', 'woo-custom-loop-filters')
            : __('همه محصولات', 'woo-custom-loop-filters');

        wp_enqueue_script('wclf-category-filter');

        ob_start();
        ?>
        <div class="custom-category-filter-wrapper" id="categoryFilterWrapper">
            <div class="cat-filter-dropdown">
                <button class="dropdown-toggle" type="button" id="catToggle">
                    <span><?php esc_html_e('فیلتر بر اساس دسته‌بندی', 'woo-custom-loop-filters'); ?></span>
                    <svg class="arrow-icon" width="16" height="8" viewBox="0 0 16 8" fill="none">
                        <path d="M2 2L8 6L14 2" stroke="#e7a439" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="dropdown-content open" id="catFilterContent">
                    <div class="category-list">
                        <label class="cat-radio-label">
                            <input type="radio" name="cat_filter_radio" value="" <?php checked($current_cat, ''); ?>>
                            <span><?php echo esc_html($all_label); ?></span>
                        </label>

                        <?php foreach ($terms as $term) : ?>
                            <label class="cat-radio-label">
                                <input type="radio"
                                       name="cat_filter_radio"
                                       value="<?php echo esc_attr($term->term_id); ?>"
                                       <?php checked($this->is_term_filter_active($current_cat, $term)); ?>>
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
     * Brand Filter Shortcode [elementor_brand_filter]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function brand_filter_shortcode($atts) {
        if (!class_exists('WooCommerce')) {
            return '<p>' . esc_html__('ووکامرس فعال نیست!', 'woo-custom-loop-filters') . '</p>';
        }

        // Fully hide on any brand taxonomy archive (e.g. /brand/nike/).
        if ($this->is_brand_archive_context()) {
            return $this->render_hidden_brand_filter_placeholder();
        }

        $atts = shortcode_atts(array(
            'taxonomy' => '',
        ), $atts, 'elementor_brand_filter');

        $taxonomy = !empty($atts['taxonomy']) ? sanitize_key($atts['taxonomy']) : $this->get_brand_taxonomy();
        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            return '<p>' . esc_html__('تاکسونومی برند یافت نشد!', 'woo-custom-loop-filters') . '</p>';
        }

        $terms = $this->get_contextual_brand_terms($taxonomy);

        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }

        $current_brand = isset($_GET['product_brand_filter']) ? $this->sanitize_filter_term_value($_GET['product_brand_filter']) : '';
        $tax_obj = get_taxonomy($taxonomy);
        $tax_label = $tax_obj && isset($tax_obj->labels->singular_name) ? $tax_obj->labels->singular_name : __('برند', 'woo-custom-loop-filters');

        wp_enqueue_script('wclf-brand-filter');

        ob_start();
        ?>
        <div class="custom-brand-filter-wrapper" id="brandFilterWrapper">
            <div class="cat-filter-dropdown">
                <button class="dropdown-toggle" type="button" id="brandToggle">
                    <span><?php echo esc_html(sprintf(__('فیلتر بر اساس %s', 'woo-custom-loop-filters'), $tax_label)); ?></span>
                    <svg class="arrow-icon" width="16" height="8" viewBox="0 0 16 8" fill="none">
                        <path d="M2 2L8 6L14 2" stroke="#e7a439" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="dropdown-content open" id="brandFilterContent">
                    <div class="category-list">
                        <label class="cat-radio-label">
                            <input type="radio" name="brand_filter_radio" value="" <?php checked($current_brand, ''); ?>>
                            <span><?php esc_html_e('همه برندها', 'woo-custom-loop-filters'); ?></span>
                        </label>

                        <?php foreach ($terms as $term) : ?>
                            <label class="cat-radio-label">
                                <input type="radio"
                                       name="brand_filter_radio"
                                       value="<?php echo esc_attr($term->term_id); ?>"
                                       <?php checked($this->is_term_filter_active($current_brand, $term)); ?>>
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
            'discount'     => 'بیشترین تخفیف',
            'discount-asc' => 'کمترین تخفیف',
            'popularity'   => 'پربازدیدترین',
            'date'       => 'جدیدترین',
            'sales'      => 'پرفروش‌ترین',
            'price'      => 'ارزان‌ترین',
            'price-desc' => 'گران‌ترین',
        );

        // Check if any filters or sorting parameters are active
        $has_active_filters = false;
        foreach ($_GET as $key => $val) {
            if (in_array($key, array('min_price', 'max_price', 'product_cat_filter', 'product_brand_filter', 'stock_filter', 'orderby'), true) || strpos($key, 'filter_') === 0) {
                if (!empty($val)) {
                    $has_active_filters = true;
                    break;
                }
            }
        }

        $reset_url = remove_query_arg(array('min_price', 'max_price', 'product_cat_filter', 'product_brand_filter', 'stock_filter', 'orderby'));
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
                <img src="<?php echo esc_url(WCLF_PLUGIN_URL . 'assets/sort-from-top-to-bottom.svg'); ?>" alt="مرتب سازی" class="beban-sort-icon">
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

        $terms = $this->get_contextual_attribute_terms($taxonomy, $attribute_slug);

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
     * Attribute terms present on products in the current catalog scope.
     * Excludes the current attribute's own filter so selected options stay visible.
     *
     * @param string $taxonomy       Attribute taxonomy (pa_*).
     * @param string $attribute_slug Attribute slug without pa_ prefix.
     * @return array
     */
    private function get_contextual_attribute_terms($taxonomy, $attribute_slug) {
        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            return array();
        }

        $product_ids = WCLF_Query_Helper::get_page_product_ids(array(
            'exclude_price'       => false,
            'exclude_brand'       => false,
            'exclude_attribute'   => $attribute_slug,
            'allow_unscoped_shop' => true,
        ));

        if (empty($product_ids)) {
            return array();
        }

        $counts = WCLF_Query_Helper::get_term_counts_for_products($taxonomy, $product_ids);

        if (empty($counts)) {
            return array();
        }

        $ordered_ids = array_map('intval', array_keys($counts));
        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'include'    => $ordered_ids,
            'orderby'    => 'include',
            'hide_empty' => false,
        ));

        if (empty($terms) || is_wp_error($terms)) {
            return array();
        }

        foreach ($terms as $term) {
            if (isset($counts[(int) $term->term_id])) {
                $term->count = (int) $counts[(int) $term->term_id];
            }
        }

        $param_key = 'filter_' . $attribute_slug;
        $current_value = isset($_GET[$param_key]) ? sanitize_text_field(wp_unslash($_GET[$param_key])) : '';
        if ('' !== $current_value) {
            $current_slugs = array_filter(array_map('sanitize_title', explode(',', $current_value)));
            $present_slugs = wp_list_pluck($terms, 'slug');
            foreach ($current_slugs as $slug) {
                if (in_array($slug, $present_slugs, true)) {
                    continue;
                }
                $selected = get_term_by('slug', $slug, $taxonomy);
                if ($selected && !is_wp_error($selected)) {
                    $selected->count = isset($counts[(int) $selected->term_id])
                        ? (int) $counts[(int) $selected->term_id]
                        : 0;
                    $terms[] = $selected;
                }
            }
            $terms = $this->sort_terms_by_contextual_count($terms);
        }

        return $terms;
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
            'show_total'     => 'no',
            'label_filtered' => '{filtered} کالا',
            'label_both'     => '{filtered} کالا از {total} کالا',
            'label_total'    => '{total} کالا',
        ), $atts, 'wclf_product_count');

        // Check if any filters are active
        $has_active_filters = false;
        foreach ($_GET as $key => $val) {
            if (in_array($key, array('min_price', 'max_price', 'product_cat_filter', 'product_brand_filter', 'stock_filter', 'orderby'), true) || strpos($key, 'filter_') === 0) {
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
            'posts_per_page' => 1,
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
            $args['tax_query'] = isset($args['tax_query']) ? $args['tax_query'] : array();
            $args['tax_query'][] = $this->build_tax_filter_clause(
                'product_cat',
                $this->sanitize_filter_term_value($_GET['product_cat_filter'])
            );
        }

        // 2a. Brand Filter
        if (isset($_GET['product_brand_filter']) && !empty($_GET['product_brand_filter'])) {
            $brand_taxonomy = $this->get_brand_taxonomy();
            if (!empty($brand_taxonomy)) {
                $args['tax_query'] = isset($args['tax_query']) ? $args['tax_query'] : array();
                $args['tax_query'][] = $this->build_tax_filter_clause(
                    $brand_taxonomy,
                    $this->sanitize_filter_term_value($_GET['product_brand_filter'])
                );
            }
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

        // 4. Discount sorting filter (only products with discount)
        $orderby = isset($_GET['orderby']) ? wc_clean(wp_unslash($_GET['orderby'])) : '';
        if (in_array($orderby, array('discount', 'discount-asc'), true)) {
            $args['meta_query'] = isset($args['meta_query']) ? $args['meta_query'] : array();
            $args['meta_query'][] = array(
                'key'     => 'discount_percentage',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
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

    /**
     * Get the category archive term for the current page (ignores active sub-filters).
     *
     * @return WP_Term|null
     */
    private function get_category_archive_context_term() {
        if (function_exists('is_product_category') && is_product_category()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->term_id)) {
                return $term;
            }
        }

        // Fallback for Theme Builder / custom routers that keep product_cat queried object.
        if (is_tax('product_cat')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->term_id)) {
                return $term;
            }
        }

        return null;
    }

    /**
     * Get the active category context used for query scoping.
     *
     * @return WP_Term|null
     */
    private function get_category_filter_context_term() {
        if (isset($_GET['product_cat_filter']) && !empty($_GET['product_cat_filter'])) {
            $value = $this->sanitize_filter_term_value($_GET['product_cat_filter']);
            if (is_numeric($value)) {
                $term = get_term((int) $value, 'product_cat');
            } else {
                $term = get_term_by('slug', $value, 'product_cat');
            }

            if ($term && !is_wp_error($term)) {
                return $term;
            }
        }

        if (is_product_category()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                return $term;
            }
        }

        return null;
    }

    /**
     * Brand terms present on at least one product in the current archive loop.
     * Uses shared page product IDs + term_taxonomy counts from WCLF_Query_Helper.
     *
     * @param string $taxonomy Brand taxonomy name.
     * @return array
     */
    private function get_contextual_brand_terms($taxonomy) {
        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            return array();
        }

        $product_ids = WCLF_Query_Helper::get_page_product_ids(array(
            'exclude_price'       => false,
            'exclude_brand'       => true,
            'allow_unscoped_shop' => true,
        ));

        if (empty($product_ids)) {
            return array();
        }

        $counts = WCLF_Query_Helper::get_term_counts_for_products($taxonomy, $product_ids);

        if (empty($counts)) {
            return array();
        }

        $ordered_ids = array_map('intval', array_keys($counts));

        $terms = get_terms($this->get_filter_terms_args($taxonomy, array(
            'include'    => $ordered_ids,
            'orderby'    => 'include',
            'hide_empty' => false,
        )));

        if (empty($terms) || is_wp_error($terms)) {
            return array();
        }

        foreach ($terms as $term) {
            if (isset($counts[(int) $term->term_id])) {
                $term->count = (int) $counts[(int) $term->term_id];
            }
        }

        $current_brand = isset($_GET['product_brand_filter']) ? $this->sanitize_filter_term_value($_GET['product_brand_filter']) : '';
        if ('' !== $current_brand) {
            $has_current = false;
            foreach ($terms as $term) {
                if ($this->is_term_filter_active($current_brand, $term)) {
                    $has_current = true;
                    break;
                }
            }

            if (!$has_current) {
                if (is_numeric($current_brand)) {
                    $selected = get_term((int) $current_brand, $taxonomy);
                } else {
                    $selected = get_term_by('slug', $current_brand, $taxonomy);
                }
                if ($selected && !is_wp_error($selected)) {
                    $selected->count = isset($counts[(int) $selected->term_id])
                        ? (int) $counts[(int) $selected->term_id]
                        : 0;
                    $terms[] = $selected;
                    $terms = $this->sort_terms_by_contextual_count($terms);
                }
            }
        }

        return $terms;
    }

    /**
     * Sort term objects by contextual count DESC, then name ASC.
     *
     * @param array $terms WP_Term objects (with ->count set to page-local count).
     * @return array
     */
    private function sort_terms_by_contextual_count($terms) {
        usort(
            $terms,
            function ($a, $b) {
                $count_a = isset($a->count) ? (int) $a->count : 0;
                $count_b = isset($b->count) ? (int) $b->count : 0;
                if ($count_a === $count_b) {
                    return strcasecmp($a->name, $b->name);
                }
                return ($count_b > $count_a) ? 1 : -1;
            }
        );

        return $terms;
    }

    /**
     * Get category terms to display in the filter UI (single-level, no drill-down).
     *
     * Shop: top-level categories (parent=0), non-empty, ordered by total product count DESC.
     * Category archive: direct children of the current archive category only.
     * Brand / tag / search / other product tax: categories assigned to products in that scope.
     * If the archive category has no children, returns empty (filter is fully hidden).
     *
     * @return array
     */
    private function get_contextual_product_cat_terms() {
        $archive_term = $this->get_category_archive_context_term();

        if ($archive_term) {
            return $this->get_category_archive_child_product_cat_terms($archive_term);
        }

        if ($this->is_shop_context()) {
            return $this->get_shop_top_level_product_cat_terms();
        }

        // Brand, tag, search, and other product taxonomy archives.
        if (WCLF_Query_Helper::is_catalog_context()) {
            return $this->get_scoped_product_cat_terms_for_page();
        }

        return array();
    }

    /**
     * Categories present on products in the current non-shop / non-category catalog page
     * (brand archive, product tag, product search, custom product taxonomies).
     *
     * @return array
     */
    private function get_scoped_product_cat_terms_for_page() {
        $product_ids = WCLF_Query_Helper::get_page_product_ids(array(
            'exclude_price'           => true,
            'exclude_brand'           => false,
            'exclude_category_filter' => true,
            'allow_unscoped_shop'     => false,
        ));

        if (empty($product_ids) || true === $product_ids) {
            return array();
        }

        $counts = WCLF_Query_Helper::get_term_counts_for_products('product_cat', $product_ids);

        if (empty($counts)) {
            return array();
        }

        $ordered_ids = array_map('intval', array_keys($counts));
        $terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'include'    => $ordered_ids,
                'orderby'    => 'include',
                'hide_empty' => false,
            )
        );

        if (empty($terms) || is_wp_error($terms)) {
            return array();
        }

        foreach ($terms as $term) {
            if (isset($counts[(int) $term->term_id])) {
                $term->count = (int) $counts[(int) $term->term_id];
            }
        }

        return $this->sort_and_filter_product_cat_terms_by_count($terms);
    }

    /**
     * Product-category archive filter: only direct children of the current category.
     * Counts use shared page product IDs (archive scope) via WCLF_Query_Helper.
     *
     * @param WP_Term $parent_term Current archive category term.
     * @return array
     */
    private function get_category_archive_child_product_cat_terms($parent_term) {
        if (!$parent_term || is_wp_error($parent_term) || empty($parent_term->term_id)) {
            return array();
        }

        if ($this->is_leaf_product_category($parent_term)) {
            return array();
        }

        $product_ids = WCLF_Query_Helper::get_page_product_ids(array(
            'exclude_price'           => true,
            'exclude_brand'           => true,
            'exclude_category_filter' => true,
            'allow_unscoped_shop'     => false,
        ));

        if (empty($product_ids) || true === $product_ids) {
            return array();
        }

        $counts = WCLF_Query_Helper::get_term_counts_for_products(
            'product_cat',
            $product_ids,
            array('parent' => (int) $parent_term->term_id)
        );

        if (empty($counts)) {
            return array();
        }

        $ordered_ids = array_map('intval', array_keys($counts));
        $terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'include'    => $ordered_ids,
                'orderby'    => 'include',
                'hide_empty' => false,
            )
        );

        if (empty($terms) || is_wp_error($terms)) {
            return array();
        }

        foreach ($terms as $term) {
            if (isset($counts[(int) $term->term_id])) {
                $term->count = (int) $counts[(int) $term->term_id];
            }
        }

        return $this->sort_and_filter_product_cat_terms_by_count($terms);
    }

    /**
     * Whether a product category has zero direct child terms (leaf category).
     *
     * @param WP_Term $term Category term.
     * @return bool
     */
    private function is_leaf_product_category($term) {
        return 0 === $this->count_direct_product_cat_children($term);
    }

    /**
     * Count direct child terms of a product category (including empty children).
     *
     * @param WP_Term $term Parent category term.
     * @return int
     */
    private function count_direct_product_cat_children($term) {
        if (!$term || is_wp_error($term) || empty($term->term_id)) {
            return 0;
        }

        $children = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'parent'     => (int) $term->term_id,
                'hide_empty' => false,
                'fields'     => 'ids',
            )
        );

        return (empty($children) || is_wp_error($children)) ? 0 : count($children);
    }

    /**
     * Shop page category filter: only parent=0, hide empty, sort by total products DESC.
     *
     * @return array
     */
    private function get_shop_top_level_product_cat_terms() {
        $counts = WCLF_Query_Helper::get_term_counts_for_products(
            'product_cat',
            true,
            array('parent' => 0)
        );

        if (empty($counts)) {
            return array();
        }

        $ordered_ids = array_map('intval', array_keys($counts));
        $terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'include'    => $ordered_ids,
                'orderby'    => 'include',
                'hide_empty' => false,
                'pad_counts' => true,
            )
        );

        if (empty($terms) || is_wp_error($terms)) {
            return array();
        }

        foreach ($terms as $term) {
            if (isset($counts[(int) $term->term_id])) {
                $term->count = (int) $counts[(int) $term->term_id];
            }
        }

        return $this->sort_and_filter_product_cat_terms_by_count($terms);
    }

    /**
     * Drop empty product_cat terms and sort by padded count DESC (name ASC on ties).
     *
     * @param array $terms WP_Term list.
     * @return array
     */
    private function sort_and_filter_product_cat_terms_by_count($terms) {
        $terms = array_values(
            array_filter(
                $terms,
                function ($term) {
                    return isset($term->count) && (int) $term->count > 0;
                }
            )
        );

        usort(
            $terms,
            function ($a, $b) {
                $count_a = (int) $a->count;
                $count_b = (int) $b->count;
                if ($count_a === $count_b) {
                    return strcasecmp($a->name, $b->name);
                }
                return ($count_b > $count_a) ? 1 : -1;
            }
        );

        return $terms;
    }

    /**
     * Detect WooCommerce shop page context.
     *
     * @return bool
     */
    private function is_shop_context() {
        return WCLF_Query_Helper::is_shop_context();
    }

    /**
     * Detect the active brand taxonomy.
     *
     * @return string
     */
    private function get_brand_taxonomy() {
        return WCLF_Query_Helper::get_brand_taxonomy();
    }

    /**
     * Known brand taxonomy slugs (priority order).
     *
     * @return string[]
     */
    private function get_brand_taxonomy_candidates() {
        return WCLF_Query_Helper::get_brand_taxonomy_candidates();
    }

    /**
     * Detect whether the current page is any brand taxonomy archive.
     *
     * @param string $taxonomy Optional specific taxonomy; unused for detection (all candidates are checked).
     * @return bool
     */
    private function is_brand_archive_context($taxonomy = '') {
        foreach ($this->get_brand_taxonomy_candidates() as $candidate) {
            if (taxonomy_exists($candidate) && is_tax($candidate)) {
                return true;
            }
        }

        if (!empty($taxonomy) && taxonomy_exists($taxonomy) && is_tax($taxonomy)) {
            return true;
        }

        return false;
    }

    /**
     * Invisible marker so Elementor shortcode widgets can be collapsed with CSS :has().
     *
     * @return string
     */
    private function render_hidden_brand_filter_placeholder() {
        $this->enqueue_hidden_filter_styles();
        return '<span class="wclf-brand-filter-hidden" hidden aria-hidden="true"></span>';
    }

    /**
     * Invisible marker to fully hide the category filter (leaf archives / no children).
     *
     * @return string
     */
    private function render_hidden_category_filter_placeholder() {
        $this->enqueue_hidden_filter_styles();
        return '<span class="wclf-category-filter-hidden" hidden aria-hidden="true"></span>';
    }

    /**
     * Shared CSS for collapsing hidden brand/category filter shortcode widgets.
     */
    private function enqueue_hidden_filter_styles() {
        if (wp_style_is('wclf-hidden-filters', 'enqueued')) {
            return;
        }

        wp_register_style('wclf-hidden-filters', false, array(), defined('WCLF_VERSION') ? WCLF_VERSION : '2.9.12');
        wp_enqueue_style('wclf-hidden-filters');
        // Only hide the shortcode widget itself — never broad Elementor containers,
        // because parent sections/columns would hide the Loop Grid too.
        wp_add_inline_style(
            'wclf-hidden-filters',
            'body.wclf-brand-archive .custom-brand-filter-wrapper,'
            . 'body.wclf-brand-archive #brandFilterWrapper,'
            . 'body.wclf-leaf-category .custom-category-filter-wrapper,'
            . 'body.wclf-leaf-category #categoryFilterWrapper,'
            . '.wclf-brand-filter-hidden,'
            . '.wclf-category-filter-hidden{display:none!important;}'
            . '.elementor-widget-shortcode:has(.wclf-brand-filter-hidden),'
            . '.elementor-widget-shortcode:has(.wclf-category-filter-hidden){display:none!important;}'
        );
    }

    /**
     * Sanitize taxonomy filter values from GET (supports numeric term IDs).
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private function sanitize_filter_term_value($value) {
        $value = wp_unslash($value);

        if (is_numeric($value)) {
            return (string) (int) $value;
        }

        return sanitize_text_field($value);
    }

    /**
     * Check whether a taxonomy term matches the active filter value.
     *
     * @param string  $current_value Active filter value.
     * @param WP_Term $term          Taxonomy term.
     * @return bool
     */
    private function is_term_filter_active($current_value, $term) {
        if ('' === $current_value) {
            return false;
        }

        return ((string) $term->term_id === (string) $current_value) || ($term->slug === $current_value);
    }

    /**
     * Build a taxonomy filter clause from term ID or slug.
     *
     * @param string $taxonomy Taxonomy name.
     * @param string $value    Term ID or slug.
     * @return array
     */
    private function build_tax_filter_clause($taxonomy, $value) {
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
     * Default get_terms args for category/brand filters sorted by product count.
     *
     * @param string $taxonomy  Taxonomy name.
     * @param array  $extra_args Additional get_terms arguments.
     * @return array
     */
    private function get_filter_terms_args($taxonomy, $extra_args = array()) {
        return array_merge(
            array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'orderby'    => 'count',
                'order'      => 'DESC',
            ),
            $extra_args
        );
    }
}
