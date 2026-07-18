<?php
/**
 * Plugin Name: WooCommerce Custom Loop Filters
 * Plugin URI: https://example.com/
 * Description: A comprehensive OOP plugin to filter and sort products in Elementor Loop Grid and WooCommerce default shop archives using a single Query ID.
 * Version: 2.9.16
 * Author: AlirezaKMaxim
 * Author URL:https://github.com/AlirezaKmaxim/
 * License: GPL2
 * Text Domain: woo-custom-loop-filters
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * Changelog (2.9.16): Hard WooCommerce dependency header + admin notice when missing.
 * Changelog (2.9.15): orderby=discount sorts only; does not hide non-sale products.
 * Changelog (2.9.14): Sorting "همه" clears only orderby, not other filters.
 * Changelog (2.9.13): Scope attribute filter terms to current catalog context.
 * Changelog (2.9.12): Contextual category filter on brand/tag/search archives.
 * Changelog (2.9.11): Serve sort icon from local plugin assets (no hardcoded remote URL).
 * Changelog (2.9.10): Do not rewrite Elementor library queries to product (fixes blank shop).
 * Changelog (2.9.9): Prevent AJAX swap from wiping filters/template; safer overlay clip.
 * Changelog (2.9.8): Center spinner in viewport; clip blur overlay to products area only.
 * Changelog (2.9.7): Admin spinner color option; keep preloader in top third of viewport.
 * Changelog (2.9.6): Fix mobile horizontal overflow while AJAX filter overlay is shown.
 * Changelog (2.9.5): Uninstall only clears wclf_* data (keeps discount_percentage /
 * post_views). Discount rebuild + Elementor leaf CSS fixes from 2.9.4.
 */

defined('ABSPATH') || exit;

class WCLF_Bootstrap {
    /**
     * Singleton instance of the bootstrap class.
     *
     * @var WCLF_Bootstrap|null
     */
    private static $instance = null;

    /**
     * Get class instance.
     *
     * @return WCLF_Bootstrap
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->define_constants();

        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'missing_woocommerce_notice'));
            return;
        }

        $this->includes();
        $this->init();
    }

    /**
     * Whether WooCommerce is available.
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Admin notice when WooCommerce is inactive.
     */
    public function missing_woocommerce_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__(
            'افزونه WooCommerce Custom Loop Filters برای اجرا به ووکامرس نیاز دارد. لطفاً ووکامرس را نصب و فعال کنید.',
            'woo-custom-loop-filters'
        );
        echo '</p></div>';
    }

    /**
     * Define plugin constants.
     */
    private function define_constants() {
        define('WCLF_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('WCLF_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('WCLF_VERSION', '2.9.16');
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once WCLF_PLUGIN_DIR . 'includes/class-wclf-product-meta.php';
        require_once WCLF_PLUGIN_DIR . 'includes/class-wclf-query-helper.php';
        require_once WCLF_PLUGIN_DIR . 'includes/class-wclf-query-handler.php';
        require_once WCLF_PLUGIN_DIR . 'includes/class-wclf-shortcodes.php';
        require_once WCLF_PLUGIN_DIR . 'includes/class-wclf-admin.php';
        require_once WCLF_PLUGIN_DIR . 'includes/class-wclf-scenario-tester.php';
    }

    /**
     * Initialize the plugin components.
     */
    private function init() {
        if (class_exists('WCLF_Product_Meta')) {
            new WCLF_Product_Meta();
        }
        if (class_exists('WCLF_Query_Handler')) {
            new WCLF_Query_Handler();
        }
        if (class_exists('WCLF_Shortcodes')) {
            new WCLF_Shortcodes();
        }
        if (class_exists('WCLF_Admin')) {
            new WCLF_Admin();
        }
        if (class_exists('WCLF_Scenario_Tester')) {
            WCLF_Scenario_Tester::register_cli();
        }
    }
}

// Instantiate after plugins are loaded so WooCommerce can register first.
add_action('plugins_loaded', array('WCLF_Bootstrap', 'get_instance'));

register_deactivation_hook(__FILE__, 'wclf_deactivate_plugin');

/**
 * Cleanup on plugin deactivation.
 */
function wclf_deactivate_plugin() {
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
