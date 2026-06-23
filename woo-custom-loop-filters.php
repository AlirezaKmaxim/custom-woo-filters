<?php
/**
 * Plugin Name: WooCommerce Custom Loop Filters
 * Plugin URI: https://example.com/
 * Description: A comprehensive OOP plugin to filter and sort products in Elementor Loop Grid and WooCommerce default shop archives using a single Query ID.
 * Version: 1.1.0
 * Author: AlirezaKMaxim
 * Author URL:https://github.com/AlirezaKmaxim/
 * License: GPL2
 * Text Domain: woo-custom-loop-filters
 * Domain Path: /languages
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
        $this->includes();
        $this->init();
    }

    /**
     * Define plugin constants.
     */
    private function define_constants() {
        define('WCLF_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('WCLF_PLUGIN_URL', plugin_dir_url(__FILE__));
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once WCLF_PLUGIN_DIR . 'includes/class-wclf-product-meta.php';
        require_once WCLF_PLUGIN_DIR . 'includes/class-wclf-query-handler.php';
        require_once WCLF_PLUGIN_DIR . 'includes/class-wclf-shortcodes.php';
        require_once WCLF_PLUGIN_DIR . 'includes/class-wclf-admin.php';
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
    }
}

// Instantiate the bootstrap class after plugins are loaded to ensure WooCommerce is loaded first
add_action('plugins_loaded', array('WCLF_Bootstrap', 'get_instance'));
