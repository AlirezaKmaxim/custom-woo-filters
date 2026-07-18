<?php
/**
 * Bulk recalculate discount_percentage meta for all published products.
 *
 * Usage (CLI): php update-discounts.php
 * Usage (web): only for administrators via browser.
 * Prefer: WP Admin → فیلترهای سفارشی المنتور → بازمحاسبه تخفیف
 */

$wp_load = dirname(__FILE__) . '/../../../wp-load.php';
if (!file_exists($wp_load)) {
    $wp_load = dirname(__FILE__) . '/../../../../wp-load.php';
}
require_once $wp_load;

if (!defined('ABSPATH')) {
    exit('WordPress could not be loaded.');
}

if (!class_exists('WooCommerce') || !class_exists('WCLF_Product_Meta')) {
    exit('WooCommerce or WooCommerce Custom Loop Filters is not active.');
}

if (php_sapi_name() !== 'cli') {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        status_header(403);
        wp_die(
            esc_html__('You do not have permission to run this script.', 'woo-custom-loop-filters'),
            esc_html__('Forbidden', 'woo-custom-loop-filters'),
            array('response' => 403)
        );
    }
}

$result  = WCLF_Product_Meta::recalculate_all_discounts();
$message = sprintf(
    'Updated %d products (%d with discount percentage).',
    (int) $result['updated'],
    (int) $result['with_discount']
);

if (php_sapi_name() === 'cli') {
    echo $message . "\n";
} else {
    echo esc_html($message);
}
