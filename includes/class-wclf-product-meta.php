<?php
defined('ABSPATH') || exit;

class WCLF_Product_Meta {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('woocommerce_update_product', array($this, 'clear_price_transient'));
        add_action('woocommerce_new_product', array($this, 'clear_price_transient'));
        add_action('woocommerce_delete_product', array($this, 'clear_price_transient'));
        add_action('wp_trash_post', array($this, 'clear_price_transient_on_trash'));
        add_action('untrash_post', array($this, 'clear_price_transient_on_trash'));

        // Prefer process_product_meta: prices are already written to the DB.
        add_action('woocommerce_process_product_meta', array($this, 'update_discount_meta'), 30);
        add_action('woocommerce_update_product', array($this, 'update_discount_meta'), 30);
        add_action('woocommerce_new_product', array($this, 'update_discount_meta'), 30);
        add_action('woocommerce_save_product_variation', array($this, 'update_discount_meta_from_variation'), 30, 2);
        add_action('woocommerce_update_product_variation', array($this, 'update_discount_meta'), 30);

        add_action('template_redirect', array($this, 'track_product_views'));
    }

    /**
     * Recalculate discount meta when a product or its variation is saved.
     *
     * @param int $product_id Product or variation ID.
     */
    public function update_discount_meta($product_id) {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return;
        }

        // Avoid stale cached product objects after price edits.
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return;
        }

        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                self::save_discount_meta($parent_id);
            }
            return;
        }

        self::save_discount_meta($product_id);
    }

    /**
     * Recalculate parent discount when a variation is saved from the product editor.
     *
     * @param int $variation_id Variation ID.
     * @param int $i            Loop index (unused).
     */
    public function update_discount_meta_from_variation($variation_id, $i = 0) {
        unset($i);
        $this->update_discount_meta((int) $variation_id);
    }

    /**
     * Calculate and persist discount_percentage meta for a parent/simple product.
     *
     * @param int $product_id Product ID.
     */
    public static function save_discount_meta($product_id) {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return;
        }

        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return;
        }

        if ($product->is_type('variable')) {
            $max_discount = 0;

            foreach ($product->get_children() as $variation_id) {
                if (function_exists('wc_delete_product_transients')) {
                    wc_delete_product_transients($variation_id);
                }
                $variation_product = wc_get_product($variation_id);
                if (!$variation_product) {
                    continue;
                }

                $discount_percentage = self::calculate_discount_percentage($variation_product);
                if ($discount_percentage > $max_discount) {
                    $max_discount = $discount_percentage;
                }
            }

            if ($max_discount > 0) {
                update_post_meta($product_id, 'discount_percentage', $max_discount);
            } else {
                delete_post_meta($product_id, 'discount_percentage');
            }

            return;
        }

        $discount_percentage = self::calculate_discount_percentage($product);

        if ($discount_percentage > 0) {
            update_post_meta($product_id, 'discount_percentage', $discount_percentage);
        } else {
            delete_post_meta($product_id, 'discount_percentage');
        }
    }

    /**
     * Compute discount % from regular vs sale/active price.
     * Does not require in-stock (badge + sorting still need the meta).
     *
     * @param WC_Product $product Product object.
     * @return int
     */
    public static function calculate_discount_percentage($product) {
        if (!$product) {
            return 0;
        }

        $regular_price = floatval($product->get_regular_price());
        $sale_price    = floatval($product->get_sale_price());

        // Fallback: some catalogs only expose a lower active price.
        if ($sale_price <= 0) {
            $active_price = floatval($product->get_price());
            if ($regular_price > 0 && $active_price > 0 && $active_price < $regular_price) {
                $sale_price = $active_price;
            }
        }

        if ($sale_price <= 0 || $regular_price <= 0 || $regular_price <= $sale_price) {
            return 0;
        }

        return (int) round((($regular_price - $sale_price) / $regular_price) * 100);
    }

    /**
     * Bulk recalculate discount_percentage for all published products.
     *
     * @return array{updated:int,with_discount:int}
     */
    public static function recalculate_all_discounts() {
        $product_ids = get_posts(
            array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        $with_discount = 0;

        foreach ($product_ids as $product_id) {
            self::save_discount_meta((int) $product_id);
            if ('' !== (string) get_post_meta($product_id, 'discount_percentage', true)) {
                $with_discount++;
            }
        }

        return array(
            'updated'       => count($product_ids),
            'with_discount' => $with_discount,
        );
    }

    /**
     * Clear the viewed-products tracking cookie.
     */
    public static function clear_viewed_products_cookie() {
        if (headers_sent() || !defined('COOKIEPATH') || !defined('COOKIE_DOMAIN')) {
            return;
        }

        setcookie(
            'wclf_viewed_products',
            '',
            time() - DAY_IN_SECONDS,
            COOKIEPATH,
            COOKIE_DOMAIN
        );
    }

    /**
     * Clear price range transient cache (shop + all archive-scoped keys).
     */
    public function clear_price_transient() {
        delete_transient('wclf_min_max_prices');
        delete_transient('wclf_min_max_prices_shop');

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_wclf_min_max_prices_%'
             OR option_name LIKE '_transient_timeout_wclf_min_max_prices_%'"
        );
    }

    /**
     * Clear price range transient cache when product post type is trashed/untrashed.
     *
     * @param int $post_id Post ID.
     */
    public function clear_price_transient_on_trash($post_id) {
        if ('product' === get_post_type($post_id)) {
            $this->clear_price_transient();
        }
    }

    /**
     * Increment post_views once per product per browser session.
     */
    public function track_product_views() {
        if (!is_singular('product')) {
            return;
        }
        if ($this->is_bot()) {
            return;
        }
        if (current_user_can('administrator')) {
            return;
        }

        global $post;
        if (empty($post) || !isset($post->ID)) {
            return;
        }
        $product_id = $post->ID;

        $viewed_products = isset($_COOKIE['wclf_viewed_products']) ?
            explode(',', sanitize_text_field(wp_unslash($_COOKIE['wclf_viewed_products']))) : array();

        if (!in_array((string) $product_id, $viewed_products, true)) {
            $count = (int) get_post_meta($product_id, 'post_views', true);
            update_post_meta($product_id, 'post_views', $count + 1);

            $viewed_products[] = (string) $product_id;
            if (!headers_sent() && defined('COOKIEPATH') && defined('COOKIE_DOMAIN')) {
                setcookie(
                    'wclf_viewed_products',
                    implode(',', $viewed_products),
                    time() + HOUR_IN_SECONDS,
                    COOKIEPATH,
                    COOKIE_DOMAIN
                );
            }
        }
    }

    /**
     * Helper function to detect bots.
     *
     * @return bool
     */
    private function is_bot() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))) : '';
        if (empty($user_agent)) {
            return false;
        }

        $bot_identifiers = array(
            'bot', 'crawler', 'spider', 'slurp', 'googlebot',
            'yandex', 'baidu', 'bingbot', 'duckduckbot',
        );

        foreach ($bot_identifiers as $bot) {
            if (strpos($user_agent, $bot) !== false) {
                return true;
            }
        }
        return false;
    }
}
