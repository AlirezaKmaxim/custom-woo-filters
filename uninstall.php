<?php
/**
 * WooCommerce Custom Loop Filters Uninstall
 *
 * Uninstalling WooCommerce Custom Loop Filters cleans up transient cache and post meta.
 *
 * @package WooCommerceCustomLoopFilters
 * @version 1.2.0
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// 1. Delete transient cache
delete_transient('wclf_min_max_prices');

// 2. Delete product metadata fields
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_discount_percentage', 'post_views')");
