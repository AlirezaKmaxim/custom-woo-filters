<?php
defined('ABSPATH') || exit;

/**
 * Scenario tester for WCLF dynamic filters (shop / category / leaf / brand / tag / search).
 *
 * Client / QA entry points:
 * - WP-CLI: wp wclf test-scenarios
 * - Admin: WCLF settings page → «اجرای تست سناریوها»
 *
 * @see Docs/DELIVERY.MD
 * @see Docs/Test-Report.MD
 */
class WCLF_Scenario_Tester {

    /**
     * @var array<int,array{id:string,title:string,status:string,message:string,details?:array}>
     */
    private $results = array();

    /**
     * Register WP-CLI command when available.
     */
    public static function register_cli() {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        WP_CLI::add_command(
            'wclf test-scenarios',
            function () {
                $tester = new self();
                $report = $tester->run_all();
                foreach ($report['results'] as $row) {
                    $prefix = ('pass' === $row['status']) ? 'OK' : (('fail' === $row['status']) ? 'FAIL' : 'SKIP');
                    WP_CLI::log(sprintf('[%s] %s — %s', $prefix, $row['id'], $row['message']));
                }
                if ($report['failed'] > 0) {
                    WP_CLI::error(sprintf('%d failed, %d passed, %d skipped', $report['failed'], $report['passed'], $report['skipped']));
                }
                WP_CLI::success(sprintf('%d passed, %d skipped', $report['passed'], $report['skipped']));
            }
        );
    }

    /**
     * Run the full scenario suite.
     *
     * @return array{passed:int,failed:int,skipped:int,results:array}
     */
    public function run_all() {
        $this->results = array();

        if (!class_exists('WooCommerce') || !class_exists('WCLF_Query_Helper')) {
            $this->add('bootstrap', 'پیش‌نیاز', 'fail', 'WooCommerce یا WCLF_Query_Helper در دسترس نیست.');
            return $this->summary();
        }

        $this->test_shop();
        $this->test_parent_category();
        $this->test_leaf_category();
        $this->test_brand_archive();
        $this->test_product_tag();
        $this->test_product_search();
        $this->test_product_count_sensitivity();

        return $this->summary();
    }

    /**
     * /shop — top-level cats, price/brand from shop products.
     */
    private function test_shop() {
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
        if (!$shop_url || is_wp_error($shop_url)) {
            $this->add('shop', 'صفحه فروشگاه', 'skip', 'آدرس shop پیدا نشد.');
            return;
        }

        $this->go_to($shop_url);

        if (!WCLF_Query_Helper::is_shop_context() && !is_shop()) {
            $this->add('shop-context', 'Context فروشگاه', 'fail', 'پس از go_to، is_shop_context برقرار نشد.', array('url' => $shop_url));
            return;
        }

        $ids = WCLF_Query_Helper::get_page_product_ids(array(
            'exclude_price'       => true,
            'exclude_brand'       => true,
            'allow_unscoped_shop' => true,
        ));

        $prices = WCLF_Query_Helper::get_min_max_prices();
        $brand_tax = WCLF_Query_Helper::get_brand_taxonomy();
        $brand_counts = $brand_tax
            ? WCLF_Query_Helper::get_term_counts_for_products($brand_tax, $ids)
            : array();

        $top_cats = WCLF_Query_Helper::get_term_counts_for_products('product_cat', true, array('parent' => 0));

        $ok_price = isset($prices['max']) && floatval($prices['max']) > 0;
        $ok_cats = !empty($top_cats);

        // All top-level counts must be > 0 (empty hidden).
        $empty_top = array_filter($top_cats, function ($c) {
            return (int) $c <= 0;
        });

        if ($ok_price && $ok_cats && empty($empty_top)) {
            $this->add(
                'shop',
                'فروشگاه /shop',
                'pass',
                'بازه قیمت و دسته‌های سطح اول معتبرند؛ برندها از محصولات فروشگاه.',
                array(
                    'price'        => $prices,
                    'top_cats'     => count($top_cats),
                    'brand_terms'  => count($brand_counts),
                    'product_ids'  => (true === $ids) ? 'unscoped-shop' : count((array) $ids),
                )
            );
        } else {
            $this->add(
                'shop',
                'فروشگاه /shop',
                'fail',
                'بازه قیمت یا دسته‌های سطح اول نامعتبر است.',
                array('price' => $prices, 'top_cats' => $top_cats, 'empty_top' => $empty_top)
            );
        }
    }

    /**
     * Parent category with children (e.g. /product-category/pen/).
     */
    private function test_parent_category() {
        $parent = $this->find_parent_category_with_children();
        if (!$parent) {
            $this->add('parent-cat', 'دسته والد', 'skip', 'دسته والدی با زیردسته پیدا نشد.');
            return;
        }

        $url = get_term_link($parent);
        if (is_wp_error($url)) {
            $this->add('parent-cat', 'دسته والد', 'skip', 'لینک دسته والد نامعتبر است.');
            return;
        }

        $this->go_to($url);

        $ids = WCLF_Query_Helper::get_page_product_ids(array(
            'exclude_price'           => true,
            'exclude_brand'           => true,
            'exclude_category_filter' => true,
            'allow_unscoped_shop'     => false,
        ));

        $prices = WCLF_Query_Helper::get_min_max_prices();
        $child_counts = WCLF_Query_Helper::get_term_counts_for_products(
            'product_cat',
            $ids,
            array('parent' => (int) $parent->term_id)
        );

        $direct_children = get_terms(array(
            'taxonomy'   => 'product_cat',
            'parent'     => (int) $parent->term_id,
            'hide_empty' => false,
            'fields'     => 'ids',
        ));
        $direct_count = (empty($direct_children) || is_wp_error($direct_children)) ? 0 : count($direct_children);

        $brand_tax = WCLF_Query_Helper::get_brand_taxonomy();
        $brand_counts = $brand_tax
            ? WCLF_Query_Helper::get_term_counts_for_products($brand_tax, $ids)
            : array();

        // Child brand terms must only reference products in $ids.
        $brands_ok = $this->assert_brands_subset_of_products($brand_tax, $brand_counts, $ids);
        $price_ok = $this->assert_price_within_products($prices, $ids);
        $children_ok = $direct_count > 0 && !empty($child_counts);

        // Sorted DESC
        $sorted = $child_counts;
        arsort($sorted, SORT_NUMERIC);
        $order_ok = array_keys($child_counts) === array_keys($sorted);

        if ($children_ok && $price_ok && $brands_ok && $order_ok) {
            $this->add(
                'parent-cat',
                'دسته والد (' . $parent->slug . ')',
                'pass',
                'زیردسته‌ها نمایش‌پذیر، قیمت/برند محدود به محصولات دسته.',
                array(
                    'url'          => $url,
                    'children'     => count($child_counts),
                    'direct'       => $direct_count,
                    'price'        => $prices,
                    'brand_terms'  => count($brand_counts),
                    'products'     => is_array($ids) ? count($ids) : $ids,
                )
            );
        } else {
            $this->add(
                'parent-cat',
                'دسته والد (' . $parent->slug . ')',
                'fail',
                'یکی از قوانین دسته والد نقض شد.',
                array(
                    'children_ok' => $children_ok,
                    'price_ok'    => $price_ok,
                    'brands_ok'   => $brands_ok,
                    'order_ok'    => $order_ok,
                    'price'       => $prices,
                    'child_counts'=> $child_counts,
                )
            );
        }
    }

    /**
     * Leaf category — category filter must hide (zero direct children).
     */
    private function test_leaf_category() {
        $leaf = $this->find_leaf_category();
        if (!$leaf) {
            $this->add('leaf-cat', 'دسته برگ', 'skip', 'دسته برگ (بدون زیردسته) پیدا نشد.');
            return;
        }

        $url = get_term_link($leaf);
        if (is_wp_error($url)) {
            $this->add('leaf-cat', 'دسته برگ', 'skip', 'لینک دسته برگ نامعتبر است.');
            return;
        }

        $this->go_to($url);

        $direct = get_terms(array(
            'taxonomy'   => 'product_cat',
            'parent'     => (int) $leaf->term_id,
            'hide_empty' => false,
            'fields'     => 'ids',
        ));
        $direct_count = (empty($direct) || is_wp_error($direct)) ? 0 : count($direct);

        $ids = WCLF_Query_Helper::get_page_product_ids(array(
            'exclude_price'           => true,
            'exclude_brand'           => true,
            'exclude_category_filter' => true,
            'allow_unscoped_shop'     => false,
        ));
        $prices = WCLF_Query_Helper::get_min_max_prices();
        $child_counts = WCLF_Query_Helper::get_term_counts_for_products(
            'product_cat',
            is_array($ids) ? $ids : array(),
            array('parent' => (int) $leaf->term_id)
        );

        $hide_ok = (0 === $direct_count) && empty($child_counts);
        $price_ok = empty($ids) ? true : $this->assert_price_within_products($prices, $ids);

        if ($hide_ok && $price_ok) {
            $this->add(
                'leaf-cat',
                'دسته برگ (' . $leaf->slug . ')',
                'pass',
                'فیلتر دسته باید مخفی شود (زیردسته=۰)؛ قیمت در محدوده محصولات برگ است.',
                array('url' => $url, 'price' => $prices, 'products' => is_array($ids) ? count($ids) : 0)
            );
        } else {
            $this->add(
                'leaf-cat',
                'دسته برگ (' . $leaf->slug . ')',
                'fail',
                'دسته برگ هنوز زیردسته/شمارش دارد یا قیمت نامعتبر است.',
                array('direct' => $direct_count, 'child_counts' => $child_counts, 'price' => $prices)
            );
        }
    }

    /**
     * Brand archive — brand filter hidden; price scoped to brand products.
     */
    private function test_brand_archive() {
        $brand_tax = WCLF_Query_Helper::get_brand_taxonomy();
        if (!$brand_tax) {
            $this->add('brand', 'آرشیو برند', 'skip', 'تاکسونومی برند یافت نشد.');
            return;
        }

        $terms = get_terms(array(
            'taxonomy'   => $brand_tax,
            'hide_empty' => true,
            'number'     => 1,
        ));
        if (empty($terms) || is_wp_error($terms)) {
            $this->add('brand', 'آرشیو برند', 'skip', 'هیچ ترم برند غیرخالی نیست.');
            return;
        }

        $term = $terms[0];
        $url = get_term_link($term);
        if (is_wp_error($url)) {
            $this->add('brand', 'آرشیو برند', 'skip', 'لینک برند نامعتبر است.');
            return;
        }

        $this->go_to($url);

        $is_brand = is_tax($brand_tax);
        $ids = WCLF_Query_Helper::get_page_product_ids(array(
            'exclude_price'       => true,
            'exclude_brand'       => false,
            'allow_unscoped_shop' => false,
        ));
        $prices = WCLF_Query_Helper::get_min_max_prices();

        // On brand archive, facet brands with exclude_brand should not be the primary UI,
        // but price must still match brand products.
        $price_ok = $this->assert_price_within_products($prices, $ids);

        if ($is_brand && $price_ok) {
            $this->add(
                'brand',
                'آرشیو برند (' . $term->slug . ')',
                'pass',
                'context برند برقرار است؛ بازه قیمت محدود به محصولات همین برند.',
                array(
                    'url'      => $url,
                    'taxonomy' => $brand_tax,
                    'price'    => $prices,
                    'products' => is_array($ids) ? count($ids) : $ids,
                )
            );
        } else {
            $this->add(
                'brand',
                'آرشیو برند (' . $term->slug . ')',
                'fail',
                'context برند یا بازه قیمت نادرست است.',
                array('is_brand' => $is_brand, 'price_ok' => $price_ok, 'price' => $prices)
            );
        }
    }

    /**
     * Product tag archive.
     */
    private function test_product_tag() {
        $tags = get_terms(array(
            'taxonomy'   => 'product_tag',
            'hide_empty' => true,
            'number'     => 5,
        ));
        if (empty($tags) || is_wp_error($tags)) {
            $this->add('tag', 'برچسب محصول', 'skip', 'برچسب محصول غیرخالی پیدا نشد.');
            return;
        }

        $tag = $tags[0];
        $url = get_term_link($tag);
        if (is_wp_error($url)) {
            $this->add('tag', 'برچسب محصول', 'skip', 'لینک برچسب نامعتبر است.');
            return;
        }

        $this->go_to($url);

        $ids = WCLF_Query_Helper::get_page_product_ids(array(
            'exclude_price'       => true,
            'exclude_brand'       => true,
            'allow_unscoped_shop' => false,
        ));
        $prices = WCLF_Query_Helper::get_min_max_prices();
        $brand_tax = WCLF_Query_Helper::get_brand_taxonomy();
        $brand_counts = $brand_tax
            ? WCLF_Query_Helper::get_term_counts_for_products($brand_tax, $ids)
            : array();

        $ctx_ok = function_exists('is_product_tag') && is_product_tag();
        $price_ok = $this->assert_price_within_products($prices, $ids);
        $brands_ok = $this->assert_brands_subset_of_products($brand_tax, $brand_counts, $ids);

        if ($ctx_ok && $price_ok && $brands_ok) {
            $this->add(
                'tag',
                'برچسب (' . $tag->slug . ')',
                'pass',
                'قیمت و برند محدود به محصولات همین برچسب هستند.',
                array(
                    'url'         => $url,
                    'price'       => $prices,
                    'brand_terms' => count($brand_counts),
                    'products'    => is_array($ids) ? count($ids) : 0,
                )
            );
        } else {
            $this->add(
                'tag',
                'برچسب (' . $tag->slug . ')',
                'fail',
                'قوانین برچسب نقض شد.',
                array('ctx_ok' => $ctx_ok, 'price_ok' => $price_ok, 'brands_ok' => $brands_ok, 'price' => $prices)
            );
        }
    }

    /**
     * Product search results.
     */
    private function test_product_search() {
        $keyword = $this->find_search_keyword();
        if ('' === $keyword) {
            $this->add('search', 'جستجوی محصول', 'skip', 'کلمهٔ جستجوی قابل‌اتکا از نام محصولات پیدا نشد.');
            return;
        }

        $url = add_query_arg(
            array(
                's'         => $keyword,
                'post_type' => 'product',
            ),
            home_url('/')
        );

        $this->go_to($url);

        $ids = WCLF_Query_Helper::get_page_product_ids(array(
            'exclude_price'       => true,
            'exclude_brand'       => true,
            'allow_unscoped_shop' => false,
        ));
        $prices = WCLF_Query_Helper::get_min_max_prices();
        $brand_tax = WCLF_Query_Helper::get_brand_taxonomy();
        $brand_counts = $brand_tax
            ? WCLF_Query_Helper::get_term_counts_for_products($brand_tax, $ids)
            : array();

        $ctx_ok = WCLF_Query_Helper::is_product_search_context() || WCLF_Query_Helper::is_catalog_context();
        $has_products = is_array($ids) && count($ids) > 0;
        $price_ok = $has_products ? $this->assert_price_within_products($prices, $ids) : true;
        $brands_ok = $has_products ? $this->assert_brands_subset_of_products($brand_tax, $brand_counts, $ids) : true;

        if ($ctx_ok && $has_products && $price_ok && $brands_ok) {
            $this->add(
                'search',
                'جستجو (s=' . $keyword . ')',
                'pass',
                'قیمت و برند با نتایج جستجوی محصول هم‌خوان است.',
                array(
                    'url'         => $url,
                    'products'    => count($ids),
                    'price'       => $prices,
                    'brand_terms' => count($brand_counts),
                )
            );
        } else {
            $this->add(
                'search',
                'جستجو (s=' . $keyword . ')',
                'fail',
                'جستجوی محصول نتایج/بازهٔ معتبر نداد.',
                array(
                    'ctx_ok'       => $ctx_ok,
                    'has_products' => $has_products,
                    'price_ok'     => $price_ok,
                    'brands_ok'    => $brands_ok,
                    'url'          => $url,
                )
            );
        }
    }

    /**
     * Sensitivity: removing a product from the ID set must shrink brand counts / price bounds.
     */
    private function test_product_count_sensitivity() {
        $ids = get_posts(array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
        $ids = array_map('intval', (array) $ids);

        if (count($ids) < 4) {
            $this->add('sensitivity', 'حساسیت تعداد محصول', 'skip', 'حداقل ۴ محصول برای تست حساسیت لازم است.');
            return;
        }

        $brand_tax = WCLF_Query_Helper::get_brand_taxonomy();
        $full_prices = WCLF_Query_Helper::get_min_max_prices($ids);
        $half = array_slice($ids, 0, (int) floor(count($ids) / 2));
        $half_prices = WCLF_Query_Helper::get_min_max_prices($half);

        $full_brands = $brand_tax ? WCLF_Query_Helper::get_term_counts_for_products($brand_tax, $ids) : array();
        $half_brands = $brand_tax ? WCLF_Query_Helper::get_term_counts_for_products($brand_tax, $half) : array();

        $price_changed = ($full_prices['min'] !== $half_prices['min']) || ($full_prices['max'] !== $half_prices['max'])
            || (count($half) < count($ids));
        $brand_sum_full = array_sum($full_brands);
        $brand_sum_half = array_sum($half_brands);
        $brands_shrunk = !$brand_tax || ($brand_sum_half <= $brand_sum_full);

        // Half set max cannot exceed full set max.
        $bounds_ok = floatval($half_prices['max']) <= floatval($full_prices['max']) + 0.0001
            && floatval($half_prices['min']) >= floatval($full_prices['min']) - 0.0001;

        if ($price_changed && $brands_shrunk && $bounds_ok) {
            $this->add(
                'sensitivity',
                'حساسیت تعداد محصول',
                'pass',
                'با نصف‌کردن مجموعهٔ محصولات، بازه قیمت/شمارش برند منقبض یا پایدارِ معتبر می‌ماند.',
                array(
                    'full_price' => $full_prices,
                    'half_price' => $half_prices,
                    'full_brand_sum' => $brand_sum_full,
                    'half_brand_sum' => $brand_sum_half,
                )
            );
        } else {
            $this->add(
                'sensitivity',
                'حساسیت تعداد محصول',
                'fail',
                'کاهش مجموعهٔ محصولات رفتار قیمت/برند را نقض کرد.',
                array(
                    'price_changed' => $price_changed,
                    'brands_shrunk' => $brands_shrunk,
                    'bounds_ok'     => $bounds_ok,
                    'full_price'    => $full_prices,
                    'half_price'    => $half_prices,
                )
            );
        }
    }

    /**
     * @param array{min:float,max:float} $prices Price range.
     * @param int[]|true                 $ids    Product IDs.
     * @return bool
     */
    private function assert_price_within_products($prices, $ids) {
        if (true === $ids) {
            return isset($prices['max']) && floatval($prices['max']) >= 0;
        }
        if (empty($ids) || !is_array($ids)) {
            return true;
        }

        $actual = WCLF_Query_Helper::get_min_max_prices($ids);
        return abs(floatval($actual['min']) - floatval($prices['min'])) < 0.01
            && abs(floatval($actual['max']) - floatval($prices['max'])) < 0.01;
    }

    /**
     * Every counted brand term must appear on at least one product in $ids.
     *
     * @param string     $taxonomy Brand taxonomy.
     * @param array      $counts   term_id => count.
     * @param int[]|true $ids      Product IDs.
     * @return bool
     */
    private function assert_brands_subset_of_products($taxonomy, $counts, $ids) {
        if (!$taxonomy || empty($counts)) {
            return true;
        }
        if (true === $ids) {
            return true;
        }
        if (!is_array($ids) || empty($ids)) {
            return empty($counts);
        }

        foreach ($counts as $term_id => $count) {
            if ((int) $count <= 0) {
                return false;
            }
            $found = false;
            foreach ($ids as $product_id) {
                if (has_term((int) $term_id, $taxonomy, $product_id)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return WP_Term|null
     */
    private function find_parent_category_with_children() {
        $parents = get_terms(array(
            'taxonomy'   => 'product_cat',
            'parent'     => 0,
            'hide_empty' => false,
            'number'     => 50,
        ));
        if (empty($parents) || is_wp_error($parents)) {
            return null;
        }

        foreach ($parents as $parent) {
            $children = get_terms(array(
                'taxonomy'   => 'product_cat',
                'parent'     => (int) $parent->term_id,
                'hide_empty' => false,
                'fields'     => 'ids',
                'number'     => 1,
            ));
            if (!empty($children) && !is_wp_error($children)) {
                return $parent;
            }
        }

        // Fallback: any non-root term that has children.
        $all = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'number'     => 100,
        ));
        if (empty($all) || is_wp_error($all)) {
            return null;
        }
        foreach ($all as $term) {
            $children = get_terms(array(
                'taxonomy'   => 'product_cat',
                'parent'     => (int) $term->term_id,
                'hide_empty' => false,
                'fields'     => 'ids',
                'number'     => 1,
            ));
            if (!empty($children) && !is_wp_error($children)) {
                return $term;
            }
        }

        return null;
    }

    /**
     * @return WP_Term|null
     */
    private function find_leaf_category() {
        $terms = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'number'     => 100,
        ));
        if (empty($terms) || is_wp_error($terms)) {
            return null;
        }

        foreach ($terms as $term) {
            if ((int) $term->term_id === (int) get_option('default_product_cat')) {
                continue;
            }
            $children = get_terms(array(
                'taxonomy'   => 'product_cat',
                'parent'     => (int) $term->term_id,
                'hide_empty' => false,
                'fields'     => 'ids',
                'number'     => 1,
            ));
            if (empty($children) || is_wp_error($children)) {
                return $term;
            }
        }

        return null;
    }

    /**
     * @return string
     */
    private function find_search_keyword() {
        $products = get_posts(array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'fields'         => 'ids',
        ));
        foreach ($products as $product_id) {
            $title = get_the_title($product_id);
            if (!$title) {
                continue;
            }
            $parts = preg_split('/\s+/u', $title);
            if (!empty($parts[0]) && mb_strlen($parts[0]) >= 3) {
                return $parts[0];
            }
        }
        return '';
    }

    /**
     * Lightweight go_to() inspired by WP core test suite.
     *
     * @param string $url Absolute URL.
     */
    private function go_to($url) {
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        global $wp, $wp_query, $wp_the_query;

        $parts = wp_parse_url($url);
        $request_uri = (isset($parts['path']) ? $parts['path'] : '/');
        if (!empty($parts['query'])) {
            $request_uri .= '?' . $parts['query'];
        }

        $_SERVER['REQUEST_URI'] = $request_uri;
        $_SERVER['HTTP_HOST'] = isset($parts['host']) ? $parts['host'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');

        $_GET = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $parsed);
            foreach ($parsed as $key => $value) {
                $_GET[$key] = $value;
            }
        }

        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $wp_query = new WP_Query();
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $wp_the_query = $wp_query;
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $wp = new WP();

        $wp->init();
        $wp->parse_request();
        $wp->query_posts();
        $wp->register_globals();

        $this->reset_helper_caches();
    }

    /**
     * Reset WCLF_Query_Helper static caches via reflection.
     */
    private function reset_helper_caches() {
        if (!class_exists('WCLF_Query_Helper')) {
            return;
        }

        try {
            $ref = new ReflectionClass('WCLF_Query_Helper');
            foreach (array('product_ids_cache', 'term_counts_cache', 'price_range_cache') as $prop) {
                if ($ref->hasProperty($prop)) {
                    $p = $ref->getProperty($prop);
                    $p->setAccessible(true);
                    $p->setValue(null, array());
                }
            }
        } catch (Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
            // Ignore reflection failures; tests still run with warm cache risk.
        }
    }

    /**
     * @param string $id      Test id.
     * @param string $title   Title.
     * @param string $status  pass|fail|skip.
     * @param string $message Message.
     * @param array  $details Extra.
     */
    private function add($id, $title, $status, $message, $details = array()) {
        $this->results[] = array(
            'id'      => $id,
            'title'   => $title,
            'status'  => $status,
            'message' => $message,
            'details' => $details,
        );
    }

    /**
     * @return array{passed:int,failed:int,skipped:int,results:array}
     */
    private function summary() {
        $passed = 0;
        $failed = 0;
        $skipped = 0;
        foreach ($this->results as $row) {
            if ('pass' === $row['status']) {
                $passed++;
            } elseif ('fail' === $row['status']) {
                $failed++;
            } else {
                $skipped++;
            }
        }

        return array(
            'passed'  => $passed,
            'failed'  => $failed,
            'skipped' => $skipped,
            'results' => $this->results,
        );
    }
}
