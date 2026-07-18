<?php
/**
 * WooCommerce Custom Loop Filters Uninstall
 *
 * Only removes plugin-owned options/transients (wclf_*).
 * Does NOT delete shared product meta such as discount_percentage or post_views —
 * those may be used by theme snippets / other code outside this plugin.
 *
 * @package WooCommerceCustomLoopFilters
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Plugin-owned transients and options only.
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_wclf_%'
        OR option_name LIKE '_transient_timeout_wclf_%'
        OR option_name LIKE '_site_transient_wclf_%'
        OR option_name LIKE '_site_transient_timeout_wclf_%'
        OR option_name LIKE 'wclf_%'"
);

// Legacy transient keys created by early versions of this plugin.
delete_transient('wclf_min_max_prices');
delete_transient('wclf_min_max_prices_shop');

// Clear this plugin's tracking cookie only (no postmeta / no shared keys).
if (!headers_sent() && defined('COOKIEPATH') && defined('COOKIE_DOMAIN')) {
    setcookie(
        'wclf_viewed_products',
        '',
        time() - DAY_IN_SECONDS,
        COOKIEPATH,
        COOKIE_DOMAIN
    );
}
