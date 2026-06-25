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
        
        add_action('template_redirect', array($this, 'track_product_views'));
    }

    /**
     * Clear price range transient cache.
     */
    public function clear_price_transient() {
        delete_transient('wclf_min_max_prices');
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
     * Track product views.
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

        // Prevent counting duplicate views in the same session using cookies
        $viewed_products = isset($_COOKIE['wclf_viewed_products']) ?
            explode(',', sanitize_text_field(wp_unslash($_COOKIE['wclf_viewed_products']))) : array();

        if (!in_array((string)$product_id, $viewed_products, true)) {
            $count = (int) get_post_meta($product_id, 'post_views', true);
            update_post_meta($product_id, 'post_views', $count + 1);

            $viewed_products[] = (string)$product_id;
            setcookie(
                'wclf_viewed_products',
                implode(',', $viewed_products),
                time() + HOUR_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN
            );
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
            'yandex', 'baidu', 'bingbot', 'duckduckbot'
        );

        foreach ($bot_identifiers as $bot) {
            if (strpos($user_agent, $bot) !== false) {
                return true;
            }
        }
        return false;
    }
}
