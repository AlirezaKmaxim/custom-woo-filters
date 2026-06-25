<?php
require_once('wp-load.php'); // Adjust path if needed

$args = array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
);
$products = get_posts($args);

$count = 0;
foreach ($products as $post) {
    $product = wc_get_product($post->ID);
    if (!$product) continue;

    $regular_price = $product->get_regular_price();
    $sale_price = $product->get_sale_price();

    if ($regular_price && $sale_price && $regular_price > 0) {
        $discount = (($regular_price - $sale_price) / $regular_price) * 100;
        update_post_meta($post->ID, '_discount_percentage', round($discount, 2));
        $count++;
    } else {
        delete_post_meta($post->ID, '_discount_percentage');
    }
}

echo "Updated $count products with discount percentage.\n";
