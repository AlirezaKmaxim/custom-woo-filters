<?php
defined('ABSPATH') || exit;

class WCLF_Admin {

    const SPINNER_COLOR_OPTION = 'wclf_spinner_color';
    const SPINNER_COLOR_DEFAULT = '#333333';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_wclf_run_scenario_tests', array($this, 'handle_run_scenario_tests'));
        add_action('admin_post_wclf_recalculate_discounts', array($this, 'handle_recalculate_discounts'));
        add_action('admin_post_wclf_save_appearance', array($this, 'handle_save_appearance'));
    }

    /**
     * Get sanitized spinner color from options.
     *
     * @return string
     */
    public static function get_spinner_color() {
        $color = get_option(self::SPINNER_COLOR_OPTION, self::SPINNER_COLOR_DEFAULT);
        $color = is_string($color) ? sanitize_hex_color($color) : '';
        return $color ? $color : self::SPINNER_COLOR_DEFAULT;
    }

    /**
     * Save appearance settings (spinner color).
     */
    public function handle_save_appearance() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'woo-custom-loop-filters'), 403);
        }

        check_admin_referer('wclf_save_appearance');

        $raw = isset($_POST['wclf_spinner_color']) ? wp_unslash($_POST['wclf_spinner_color']) : '';
        $color = sanitize_hex_color($raw);
        if (!$color) {
            $color = self::SPINNER_COLOR_DEFAULT;
        }

        update_option(self::SPINNER_COLOR_OPTION, $color, false);

        wp_safe_redirect(add_query_arg('wclf_appearance', '1', admin_url('admin.php?page=wclf-custom-filters')));
        exit;
    }

    /**
     * Bulk recalculate discount_percentage meta from admin.
     */
    public function handle_recalculate_discounts() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'woo-custom-loop-filters'), 403);
        }

        check_admin_referer('wclf_recalculate_discounts');

        if (!class_exists('WCLF_Product_Meta')) {
            wp_safe_redirect(add_query_arg('wclf_discounts', 'missing', admin_url('admin.php?page=wclf-custom-filters')));
            exit;
        }

        $result = WCLF_Product_Meta::recalculate_all_discounts();
        set_transient(
            'wclf_discount_recalc_' . get_current_user_id(),
            $result,
            15 * MINUTE_IN_SECONDS
        );

        wp_safe_redirect(add_query_arg('wclf_discounts', '1', admin_url('admin.php?page=wclf-custom-filters')));
        exit;
    }

    /**
     * Run scenario tests from admin and redirect back with results in transient.
     */
    public function handle_run_scenario_tests() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'woo-custom-loop-filters'), 403);
        }

        check_admin_referer('wclf_run_scenario_tests');

        if (!class_exists('WCLF_Scenario_Tester')) {
            wp_safe_redirect(add_query_arg('wclf_tests', 'missing', admin_url('admin.php?page=wclf-custom-filters')));
            exit;
        }

        $tester = new WCLF_Scenario_Tester();
        $report = $tester->run_all();
        set_transient('wclf_scenario_test_report_' . get_current_user_id(), $report, 15 * MINUTE_IN_SECONDS);

        wp_safe_redirect(add_query_arg('wclf_tests', '1', admin_url('admin.php?page=wclf-custom-filters')));
        exit;
    }

    /**
     * Add settings page menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            __('فیلترهای سفارشی المنتور', 'woo-custom-loop-filters'),
            __('فیلترهای سفارشی المنتور', 'woo-custom-loop-filters'),
            'manage_options',
            'wclf-custom-filters',
            array($this, 'render_admin_page'),
            'dashicons-filter',
            58
        );
    }

    /**
     * Render the admin page HTML.
     */
    public function render_admin_page() {
        $report = null;
        if (isset($_GET['wclf_tests']) && '1' === $_GET['wclf_tests']) {
            $report = get_transient('wclf_scenario_test_report_' . get_current_user_id());
        }

        $discount_result = null;
        if (isset($_GET['wclf_discounts']) && '1' === $_GET['wclf_discounts']) {
            $discount_result = get_transient('wclf_discount_recalc_' . get_current_user_id());
        }
        ?>
        <style>
            .wclf-admin-wrap {
                direction: rtl;
                text-align: right;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                margin-top: 25px;
                padding-left: 20px;
                padding-right: 5px;
                box-sizing: border-box;
            }
            .wclf-admin-wrap h1 {
                font-size: 24px;
                font-weight: 700;
                color: #23282d;
                margin-bottom: 20px;
            }
            .wclf-admin-wrap .card {
                max-width: 100% !important;
                width: 100% !important;
                margin-top: 25px;
                padding: 30px;
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important;
                border: 1px solid #e2e8f0 !important;
                box-sizing: border-box;
            }
            .wclf-admin-wrap .card-warning {
                border-right: 5px solid #ffb900 !important;
            }
            .wclf-admin-wrap .card-info {
                border-right: 5px solid #2271b1 !important;
            }
            .wclf-admin-wrap .card-success {
                border-right: 5px solid #00a32a !important;
            }
            .wclf-admin-wrap h2.success-title span.dashicons {
                color: #00a32a;
            }
            .wclf-admin-wrap .wclf-setup-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 16px;
                margin: 20px 0;
            }
            .wclf-admin-wrap .wclf-setup-box {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 16px 18px;
            }
            .wclf-admin-wrap .wclf-setup-box h3 {
                margin: 0 0 10px;
                font-size: 15px;
                color: #1e1e1e;
            }
            .wclf-admin-wrap .wclf-setup-box ul {
                margin: 0;
                padding-right: 18px;
            }
            .wclf-admin-wrap .wclf-setup-box li {
                margin-bottom: 8px !important;
                font-size: 13px !important;
            }
            .wclf-admin-wrap .wclf-badge-recommended {
                display: inline-block;
                background: #d1fae5;
                color: #065f46;
                font-size: 11px;
                font-weight: 600;
                padding: 2px 8px;
                border-radius: 999px;
                margin-right: 6px;
                vertical-align: middle;
            }
            .wclf-admin-wrap .wclf-version-badge {
                display: inline-block;
                background: #eef2ff;
                color: #3730a3;
                font-size: 12px;
                font-weight: 600;
                padding: 4px 10px;
                border-radius: 6px;
                margin-right: 10px;
                direction: ltr;
            }
            .wclf-admin-wrap h2 {
                margin-top: 0;
                font-size: 18px;
                font-weight: 600;
                color: #1e1e1e;
                border-bottom: 1px solid #f0f0f1;
                padding-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .wclf-admin-wrap h2 span.dashicons {
                font-size: 22px;
                width: 22px;
                height: 22px;
                color: #2271b1;
            }
            .wclf-admin-wrap h2.warning-title span.dashicons {
                color: #d54e21;
            }
            .wclf-admin-wrap p {
                font-size: 14px;
                line-height: 1.8;
                color: #50575e;
            }
            .wclf-admin-wrap ol, .wclf-admin-wrap ul {
                padding-right: 20px;
                margin: 15px 0;
            }
            .wclf-admin-wrap ol li, .wclf-admin-wrap ul li {
                font-size: 14px;
                line-height: 1.8;
                color: #50575e;
                margin-bottom: 12px;
            }
            .wclf-admin-wrap ol li strong, .wclf-admin-wrap ul li strong {
                color: #1e1e1e;
            }
            .wclf-query-id-box {
                background: #f0f6fc;
                border: 1px dashed #2271b1;
                padding: 12px 20px;
                border-radius: 6px;
                font-family: Consolas, Monaco, monospace;
                font-size: 16px;
                font-weight: bold;
                display: inline-block;
                direction: ltr;
                margin: 15px 0;
                color: #2271b1;
            }
            .wclf-admin-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .wclf-admin-table th {
                background-color: #f8fafc;
                font-weight: 600;
                color: #1e1e1e;
                text-align: right;
                padding: 15px;
                font-size: 14px;
                border-bottom: 2px solid #e2e8f0;
            }
            .wclf-admin-table td {
                padding: 18px 15px;
                border-bottom: 1px solid #f0f0f1;
                vertical-align: middle;
                font-size: 14px;
                color: #50575e;
                line-height: 1.8;
            }
            .wclf-admin-table tr:hover td {
                background-color: #f8fafc;
            }
            .wclf-code-badge {
                font-size: 13px;
                background: #f1f5f9;
                padding: 6px 12px;
                border-radius: 6px;
                direction: ltr;
                display: inline-block;
                font-family: Consolas, Monaco, monospace;
                border: 1px solid #e2e8f0;
                color: #0f172a;
                white-space: nowrap;
                box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            }
            .wclf-code-param {
                font-size: 12px;
                background: #fef08a;
                padding: 2px 6px;
                border-radius: 4px;
                direction: ltr;
                display: inline-block;
                font-family: Consolas, Monaco, monospace;
                border: 1px solid #fde047;
                color: #854d0e;
            }
            .wclf-param-list {
                margin-top: 8px;
                padding-right: 15px;
                list-style-type: circle;
            }
            .wclf-param-list li {
                margin-bottom: 5px !important;
                font-size: 13px !important;
            }
            .wclf-admin-wrap .wclf-context-table td:first-child {
                font-weight: 600;
                color: #1e1e1e;
                white-space: nowrap;
            }
            .wclf-admin-wrap .wclf-note-box {
                background: #fffbeb;
                border: 1px solid #fde68a;
                border-radius: 8px;
                padding: 14px 18px;
                margin-top: 16px;
                font-size: 13px;
                color: #78350f;
                line-height: 1.8;
            }
            .wclf-admin-wrap .wclf-color-row {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 16px;
                margin: 18px 0;
            }
            .wclf-admin-wrap .wclf-color-row label {
                font-size: 14px;
                font-weight: 600;
                color: #1e1e1e;
            }
            .wclf-admin-wrap .wclf-color-row input[type="color"] {
                width: 48px;
                height: 36px;
                padding: 0;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                background: #fff;
                cursor: pointer;
            }
            .wclf-admin-wrap .wclf-color-preview {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                direction: ltr;
                font-family: Consolas, Monaco, monospace;
                font-size: 13px;
                color: #50575e;
            }
            .wclf-admin-wrap .wclf-color-swatch {
                width: 18px;
                height: 18px;
                border-radius: 50%;
                border: 1px solid #e2e8f0;
                box-shadow: inset 0 0 0 1px rgba(0,0,0,0.04);
            }
        </style>
        <div class="wrap wclf-admin-wrap">
            <h1>
                <?php esc_html_e('تنظیمات فیلترهای سفارشی المنتور و ووکامرس', 'woo-custom-loop-filters'); ?>
                <span class="wclf-version-badge">v<?php echo esc_html(defined('WCLF_VERSION') ? WCLF_VERSION : '2.9.11'); ?></span>
            </h1>
            <hr class="wp-header-end">

            <?php
            $spinner_color = self::get_spinner_color();
            $appearance_saved = isset($_GET['wclf_appearance']) && '1' === $_GET['wclf_appearance'];
            ?>

            <div class="card card-info">
                <h2>
                    <span class="dashicons dashicons-art"></span>
                    <?php esc_html_e('ظاهر پریلودر', 'woo-custom-loop-filters'); ?>
                </h2>
                <p>
                    <?php esc_html_e('رنگ SVG اسپینر هنگام فیلتر AJAX را تنظیم کنید. اسپینر دقیقاً در مرکز viewport کاربر نمایش داده می‌شود.', 'woo-custom-loop-filters'); ?>
                </p>
                <?php if ($appearance_saved) : ?>
                    <div class="wclf-note-box" style="background:#ecfdf5;border-color:#a7f3d0;color:#065f46;">
                        <?php esc_html_e('تنظیمات ظاهر ذخیره شد.', 'woo-custom-loop-filters'); ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wclf_save_appearance">
                    <?php wp_nonce_field('wclf_save_appearance'); ?>
                    <div class="wclf-color-row">
                        <label for="wclf_spinner_color"><?php esc_html_e('رنگ اسپینر', 'woo-custom-loop-filters'); ?></label>
                        <input
                            type="color"
                            id="wclf_spinner_color"
                            name="wclf_spinner_color"
                            value="<?php echo esc_attr($spinner_color); ?>"
                        >
                        <span class="wclf-color-preview">
                            <span class="wclf-color-swatch" style="background:<?php echo esc_attr($spinner_color); ?>;"></span>
                            <span id="wclf_spinner_color_hex"><?php echo esc_html($spinner_color); ?></span>
                        </span>
                    </div>
                    <?php submit_button(__('ذخیره ظاهر', 'woo-custom-loop-filters'), 'primary', 'submit', false); ?>
                </form>
                <script>
                    (function () {
                        var input = document.getElementById('wclf_spinner_color');
                        var hex = document.getElementById('wclf_spinner_color_hex');
                        var swatch = document.querySelector('.wclf-color-swatch');
                        if (!input || !hex) {
                            return;
                        }
                        input.addEventListener('input', function () {
                            hex.textContent = input.value;
                            if (swatch) {
                                swatch.style.background = input.value;
                            }
                        });
                    })();
                </script>
            </div>

            <div class="card card-success">
                <h2>
                    <span class="dashicons dashicons-tag"></span>
                    <?php esc_html_e('بازمحاسبه درصد تخفیف', 'woo-custom-loop-filters'); ?>
                </h2>
                <p>
                    <?php esc_html_e('متای discount_percentage را برای همه محصولات منتشرشده دوباره محاسبه می‌کند. برای نمایش بج روی کارت محصول و مرتب‌سازی ?orderby=discount لازم است.', 'woo-custom-loop-filters'); ?>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wclf_recalculate_discounts">
                    <?php wp_nonce_field('wclf_recalculate_discounts'); ?>
                    <?php submit_button(__('بازمحاسبه تخفیف همه محصولات', 'woo-custom-loop-filters'), 'primary', 'submit', false); ?>
                </form>
                <?php if (is_array($discount_result)) : ?>
                    <p style="margin-top:14px;">
                        <strong>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: 1: scanned products 2: products with discount */
                                    __('انجام شد: %1$d محصول بررسی شد، %2$d محصول دارای تخفیف ذخیره شد.', 'woo-custom-loop-filters'),
                                    (int) $discount_result['updated'],
                                    (int) $discount_result['with_discount']
                                )
                            );
                            ?>
                        </strong>
                    </p>
                <?php endif; ?>
                <div class="wclf-note-box">
                    <?php esc_html_e('اگر قبلاً کد fb_update_discount_meta را در functions.php گذاشته‌اید، آن را حذف کنید — همین منطق داخل افزونه است و تکراری بودنش لازم نیست.', 'woo-custom-loop-filters'); ?>
                </div>
            </div>

            <div class="card card-info">
                <h2>
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('تست سناریوها (تسک ۱۱)', 'woo-custom-loop-filters'); ?>
                </h2>
                <p>
                    <?php esc_html_e('shop، دسته والد، دسته برگ، برند، برچسب، جستجو و حساسیت تعداد محصول را روی دیتای واقعی فروشگاه بررسی می‌کند.', 'woo-custom-loop-filters'); ?>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wclf_run_scenario_tests">
                    <?php wp_nonce_field('wclf_run_scenario_tests'); ?>
                    <?php submit_button(__('اجرای تست سناریوها', 'woo-custom-loop-filters'), 'primary', 'submit', false); ?>
                </form>
                <p style="margin-top:12px;color:#50575e;font-size:13px;">
                    <?php esc_html_e('CLI:', 'woo-custom-loop-filters'); ?>
                    <code>wp wclf test-scenarios</code>
                </p>
                <?php if (is_array($report) && !empty($report['results'])) : ?>
                    <p>
                        <strong><?php echo esc_html(sprintf(
                            /* translators: 1: passed 2: failed 3: skipped */
                            __('نتیجه: %1$d موفق، %2$d ناموفق، %3$d ردشده', 'woo-custom-loop-filters'),
                            (int) $report['passed'],
                            (int) $report['failed'],
                            (int) $report['skipped']
                        )); ?></strong>
                    </p>
                    <table class="wclf-admin-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('سناریو', 'woo-custom-loop-filters'); ?></th>
                                <th><?php esc_html_e('وضعیت', 'woo-custom-loop-filters'); ?></th>
                                <th><?php esc_html_e('پیام', 'woo-custom-loop-filters'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['results'] as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html($row['title']); ?></td>
                                    <td>
                                        <?php
                                        $label = $row['status'];
                                        if ('pass' === $row['status']) {
                                            $label = 'PASS';
                                        } elseif ('fail' === $row['status']) {
                                            $label = 'FAIL';
                                        } else {
                                            $label = 'SKIP';
                                        }
                                        echo esc_html($label);
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($row['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- اتصال به المنتور - روش پیشنهادی -->
            <div class="card card-success">
                <h2 class="success-title">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('اتصال به المنتور (روش پیشنهادی — بدون Query ID)', 'woo-custom-loop-filters'); ?>
                </h2>
                <p>
                    <?php esc_html_e('برای یک قالب داینامیک روی Shop، دسته‌بندی، برچسب، برند و سایر آرشیوهای محصول، از Theme Builder و تنظیمات زیر استفاده کنید:', 'woo-custom-loop-filters'); ?>
                </p>
                <ol>
                    <li><?php esc_html_e('در Elementor → Theme Builder یک قالب Product Archive بسازید.', 'woo-custom-loop-filters'); ?></li>
                    <li><?php esc_html_e('ویجت Loop Grid را اضافه کنید.', 'woo-custom-loop-filters'); ?></li>
                    <li><strong><?php esc_html_e('Query → Source:', 'woo-custom-loop-filters'); ?></strong> <?php esc_html_e('Products', 'woo-custom-loop-filters'); ?></li>
                    <li><strong><?php esc_html_e('Query → Include:', 'woo-custom-loop-filters'); ?></strong> <?php esc_html_e('Current Query', 'woo-custom-loop-filters'); ?></li>
                    <li><strong><?php esc_html_e('Loop Item Template:', 'woo-custom-loop-filters'); ?></strong> <?php esc_html_e('از نوع Product', 'woo-custom-loop-filters'); ?></li>
                </ol>
                <p style="margin-bottom: 0;">
                    <?php esc_html_e('در این حالت Query ID لازم نیست. فیلترها با AJAX روی همان query المنتور اعمال می‌شوند و context آرشیو جاری (مثلاً دسته یا برند) حفظ می‌شود.', 'woo-custom-loop-filters'); ?>
                </p>
            </div>

            <!-- روش جایگزین Query ID -->
            <div class="card card-warning">
                <h2 class="warning-title" style="color: #d54e21;">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e('روش جایگزین (فقط اگر Source = Posts)', 'woo-custom-loop-filters'); ?>
                </h2>
                <p>
                    <?php esc_html_e('اگر در Loop Grid مجبورید Source را Posts بگذارید، Query ID را وارد کنید. افزونه post_type را product می‌کند و taxonomy آرشیو جاری را خودکار اعمال می‌کند.', 'woo-custom-loop-filters'); ?>
                </p>
                <div class="wclf-query-id-box">
                    custom_loop_filters
                </div>
                <p style="font-size: 13px; color: #50575e; margin-bottom: 0;">
                    <?php esc_html_e('توجه: Loop Item Template همچنان باید از نوع Product باشد.', 'woo-custom-loop-filters'); ?>
                </p>
            </div>

            <!-- چیدمان پیشنهادی -->
            <div class="card">
                <h2>
                    <span class="dashicons dashicons-layout"></span>
                    <?php esc_html_e('چیدمان پیشنهادی فیلترها در صفحه', 'woo-custom-loop-filters'); ?>
                </h2>
                <p><?php esc_html_e('شورت‌کدها را در ویجت Shortcode المنتور (یا سایدبار فیلتر) به ترتیب زیر قرار دهید:', 'woo-custom-loop-filters'); ?></p>
                <div class="wclf-setup-grid">
                    <div class="wclf-setup-box">
                        <h3><?php esc_html_e('فیلترها', 'woo-custom-loop-filters'); ?></h3>
                        <ul>
                            <li><code class="wclf-code-badge">[elementor_price_filter]</code></li>
                            <li><code class="wclf-code-badge">[elementor_category_filter]</code> <span class="wclf-badge-recommended"><?php esc_html_e('هوشمند', 'woo-custom-loop-filters'); ?></span></li>
                            <li><code class="wclf-code-badge">[elementor_brand_filter]</code></li>
                            <li><code class="wclf-code-badge">[elementor_stock_filter]</code></li>
                            <li><code class="wclf-code-badge">[elementor_attribute_filter attribute="color"]</code></li>
                        </ul>
                    </div>
                    <div class="wclf-setup-box">
                        <h3><?php esc_html_e('مرتب‌سازی و شمارنده', 'woo-custom-loop-filters'); ?></h3>
                        <ul>
                            <li><code class="wclf-code-badge">[beban_product_filters]</code></li>
                            <li><code class="wclf-code-badge">[wclf_product_count]</code></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- لیست شورت‌کدها -->
            <div class="card">
                <h2>
                    <span class="dashicons dashicons-shortcode"></span>
                    <?php esc_html_e('لیست شورت‌کدهای فیلتر و شمارشگر محصولات', 'woo-custom-loop-filters'); ?>
                </h2>
                <p>
                    <?php esc_html_e('از شورت‌کدهای زیر می‌توانید در هر کجای صفحات المنتور، برگه‌ها یا سایدبارها استفاده کنید. تمامی این فیلترها به صورت کاملاً پویا و ایجکس (بدون لود مجدد صفحه) با یکدیگر همگام شده و کار می‌کنند:', 'woo-custom-loop-filters'); ?>
                </p>
                
                <table class="wclf-admin-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;"><?php esc_html_e('شورت‌کد', 'woo-custom-loop-filters'); ?></th>
                            <th style="width: 70%;"><?php esc_html_e('توضیحات و نمونه کارکرد', 'woo-custom-loop-filters'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- قیمت -->
                        <tr>
                            <td>
                                <code class="wclf-code-badge">[elementor_price_filter]</code>
                            </td>
                            <td>
                                <strong><?php esc_html_e('فیلتر بازه قیمتی محصولات:', 'woo-custom-loop-filters'); ?></strong>
                                <?php esc_html_e('اسلایدر دوطرفه قیمت که کمترین و بیشترین قیمت موجود در دیتابیس محصولات را به عنوان محدوده انتخاب می‌کند و امکان فیلتر قیمت را به کاربر می‌دهد.', 'woo-custom-loop-filters'); ?>
                            </td>
                        </tr>
                        <!-- دسته‌بندی -->
                        <tr>
                            <td>
                                <code class="wclf-code-badge">[elementor_category_filter]</code>
                                <br>
                                <code class="wclf-code-badge">[elementor_category_filter contextual="no"]</code>
                            </td>
                            <td>
                                <strong><?php esc_html_e('فیلتر دسته‌بندی محصولات (هوشمند):', 'woo-custom-loop-filters'); ?></strong>
                                <?php esc_html_e('به‌صورت پیش‌فرض فقط دسته‌های مرتبط با صفحه جاری نمایش داده می‌شوند — نه همه دسته‌های فروشگاه.', 'woo-custom-loop-filters'); ?>
                                <ul class="wclf-param-list">
                                    <li><code class="wclf-code-param">contextual</code>: <?php esc_html_e('yes (پیش‌فرض) = فیلتر هوشمند بر اساس آرشیو | no = نمایش همه دسته‌های سایت', 'woo-custom-loop-filters'); ?></li>
                                </ul>
                                <strong><?php esc_html_e('رفتار در هر صفحه:', 'woo-custom-loop-filters'); ?></strong>
                                <ul class="wclf-param-list">
                                    <li><?php esc_html_e('Shop → فقط دسته‌های سطح اول', 'woo-custom-loop-filters'); ?></li>
                                    <li><?php esc_html_e('آرشیو دسته → زیردسته‌های همان دسته (یا دسته‌های واقعی محصولات همان صفحه)', 'woo-custom-loop-filters'); ?></li>
                                    <li><?php esc_html_e('آرشیو برند → دسته‌های محصولات همان برند', 'woo-custom-loop-filters'); ?></li>
                                    <li><?php esc_html_e('دسته بدون زیردسته → فیلتر مخفی می‌شود', 'woo-custom-loop-filters'); ?></li>
                                </ul>
                                <strong><?php esc_html_e('برچسب «همه»:', 'woo-custom-loop-filters'); ?></strong>
                                <?php esc_html_e('در آرشیو دسته «همه در این دسته» و در Shop «همه محصولات» نمایش داده می‌شود.', 'woo-custom-loop-filters'); ?>
                            </td>
                        </tr>
                        <!-- برند -->
                        <tr>
                            <td>
                                <code class="wclf-code-badge">[elementor_brand_filter]</code>
                            </td>
                            <td>
                                <strong><?php esc_html_e('فیلتر برند محصولات:', 'woo-custom-loop-filters'); ?></strong>
                                <?php esc_html_e('نمایش برندها از taxonomy برند. پیش‌فرض: ', 'woo-custom-loop-filters'); ?>
                                <code class="wclf-code-param">product_brand</code>
                                <?php esc_html_e(' — فقط برندهای دارای محصول نمایش داده می‌شوند.', 'woo-custom-loop-filters'); ?>
                                <br>
                                <strong><?php esc_html_e('taxonomy سفارشی:', 'woo-custom-loop-filters'); ?></strong>
                                <code class="wclf-code-badge">[elementor_brand_filter taxonomy="product_brand"]</code>
                            </td>
                        </tr>
                        <!-- موجودی -->
                        <tr>
                            <td>
                                <code class="wclf-code-badge">[elementor_stock_filter]</code>
                            </td>
                            <td>
                                <strong><?php esc_html_e('فیلتر وضعیت موجودی محصولات:', 'woo-custom-loop-filters'); ?></strong>
                                <?php esc_html_e('یک سوئیچ تغییر وضعیت (Toggle Switch) شکیل که با روشن کردن آن، فقط محصولات موجود در انبار به کاربر نمایش داده می‌شوند.', 'woo-custom-loop-filters'); ?>
                            </td>
                        </tr>
                        <!-- ویژگی‌ها -->
                        <tr>
                            <td>
                                <code class="wclf-code-badge">[elementor_attribute_filter attribute="color"]</code>
                            </td>
                            <td>
                                <strong><?php esc_html_e('فیلتر ویژگی‌های محصولات (Attributes):', 'woo-custom-loop-filters'); ?></strong>
                                <?php esc_html_e('فیلتر ویژگی‌های محصولات (مانند رنگ، سایز، برند و غیره). مقدار پارامتر ', 'woo-custom-loop-filters'); ?>
                                <code class="wclf-code-param">attribute</code>
                                <?php esc_html_e(' را برابر با نامک ویژگی ووکامرس قرار دهید. مثال برای سایز: ', 'woo-custom-loop-filters'); ?>
                                <code class="wclf-code-badge">[elementor_attribute_filter attribute="size"]</code>
                            </td>
                        </tr>
                        <!-- مرتب‌سازی -->
                        <tr>
                            <td>
                                <code class="wclf-code-badge">[beban_product_filters]</code>
                            </td>
                            <td>
                                <strong><?php esc_html_e('مرتب‌سازی پیشرفته محصولات:', 'woo-custom-loop-filters'); ?></strong>
                                <?php esc_html_e('۷ حالت مرتب‌سازی به‌علاوه گزینه «همه»: بیشترین تخفیف، کمترین تخفیف، پربازدیدترین، جدیدترین، پرفروش‌ترین، ارزان‌ترین و گران‌ترین.', 'woo-custom-loop-filters'); ?>
                                <br>
                                <strong><?php esc_html_e('نکته تخفیف:', 'woo-custom-loop-filters'); ?></strong>
                                <?php esc_html_e('مرتب‌سازی تخفیف فقط محصولات دارای تخفیف و موجود را نشان می‌دهد. درصد تخفیف در meta ', 'woo-custom-loop-filters'); ?>
                                <code class="wclf-code-param">discount_percentage</code>
                                <?php esc_html_e(' ذخیره می‌شود (محصول متغیر: بیشترین تخفیف variationهای موجود).', 'woo-custom-loop-filters'); ?>
                            </td>
                        </tr>
                        <!-- شمارنده محصولات جدید -->
                        <tr>
                            <td>
                                <code class="wclf-code-badge">[wclf_product_count]</code>
                            </td>
                            <td>
                                <strong><?php esc_html_e('شمارنده پویای تعداد محصولات (جدید):', 'woo-custom-loop-filters'); ?></strong>
                                <?php esc_html_e('این شورت‌کد تعداد کل محصولات و تعداد فیلتر شده را نشان می‌دهد و به محض اعمال هر فیلتری، مقدار عددی آن به صورت کاملاً زنده (Dynamic) تغییر می‌کند.', 'woo-custom-loop-filters'); ?>
                                <br>
                                <strong><?php esc_html_e('پارامترهای اختیاری جهت شخصی‌سازی متون:', 'woo-custom-loop-filters'); ?></strong>
                                <ul class="wclf-param-list">
                                    <li><code class="wclf-code-param">show_total</code>: <?php esc_html_e('نمایش تعداد کل در کنار فیلتر شده (yes یا no - پیش‌فرض: yes).', 'woo-custom-loop-filters'); ?></li>
                                    <li><code class="wclf-code-param">label_both</code>: <?php esc_html_e('قالب متنی نمایش همزمان. پیش‌فرض: "نمایش {filtered} محصول از {total} محصول".', 'woo-custom-loop-filters'); ?></li>
                                    <li><code class="wclf-code-param">label_filtered</code>: <?php esc_html_e('قالب متنی نمایش بدون تعداد کل. پیش‌فرض: "نمایش {filtered} محصول".', 'woo-custom-loop-filters'); ?></li>
                                    <li><code class="wclf-code-param">label_total</code>: <?php esc_html_e('قالب متنی زمانی که هیچ فیلتری اعمال نشده است. پیش‌فرض: "نمایش {total} محصول".', 'woo-custom-loop-filters'); ?></li>
                                </ul>
                                <strong><?php esc_html_e('مثال برای استفاده ساده:', 'woo-custom-loop-filters'); ?></strong>
                                <code class="wclf-code-badge">[wclf_product_count]</code>
                                <br>
                                <strong><?php esc_html_e('مثال برای عدم نمایش تعداد کل (فقط تعداد فیلتر شده):', 'woo-custom-loop-filters'); ?></strong>
                                <code class="wclf-code-badge">[wclf_product_count show_total="no" label_filtered="تعداد: {filtered} کالا"]</code>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- فیلتر هوشمند دسته‌بندی -->
            <div class="card card-info">
                <h2>
                    <span class="dashicons dashicons-category"></span>
                    <?php esc_html_e('فیلتر هوشمند دسته‌بندی (Contextual Category Filter)', 'woo-custom-loop-filters'); ?>
                </h2>
                <p>
                    <?php esc_html_e('مشکل قبلی: در صفحه یک دسته (مثلاً ۱۰ محصول)، فیلتر دسته‌بندی همه دسته‌های سایت را نشان می‌داد. از نسخه فعلی، لیست دسته‌ها بر اساس context صفحه محدود می‌شود:', 'woo-custom-loop-filters'); ?>
                </p>
                <table class="wclf-admin-table wclf-context-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;"><?php esc_html_e('صفحه', 'woo-custom-loop-filters'); ?></th>
                            <th style="width: 75%;"><?php esc_html_e('دسته‌های نمایش‌داده‌شده در فیلتر', 'woo-custom-loop-filters'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php esc_html_e('Shop', 'woo-custom-loop-filters'); ?></td>
                            <td><?php esc_html_e('فقط دسته‌های سطح اول (parent = 0) که محصول دارند', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('آرشیو دسته‌بندی', 'woo-custom-loop-filters'); ?></td>
                            <td><?php esc_html_e('ابتدا زیردسته‌های مستقیم همان دسته؛ اگر نبود، دسته‌هایی که روی محصولات همان آرشیو assign شده‌اند', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('آرشیو برند', 'woo-custom-loop-filters'); ?></td>
                            <td><?php esc_html_e('دسته‌هایی که محصولات همان برند در آن‌ها قرار دارند', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('دسته برگ (leaf)', 'woo-custom-loop-filters'); ?></td>
                            <td><?php esc_html_e('فیلتر نمایش داده نمی‌شود — زیردسته‌ای برای فیلتر وجود ندارد', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <div class="wclf-note-box">
                    <strong><?php esc_html_e('نکته:', 'woo-custom-loop-filters'); ?></strong>
                    <?php esc_html_e('اگر فیلترهای دیگر (قیمت، برند، موجودی) فعال باشند، لیست دسته‌ها بر اساس محصولات فیلترشده به‌روز می‌شود. برای نمایش همه دسته‌های سایت از ', 'woo-custom-loop-filters'); ?>
                    <code class="wclf-code-badge">[elementor_category_filter contextual="no"]</code>
                    <?php esc_html_e(' استفاده کنید.', 'woo-custom-loop-filters'); ?>
                </div>
            </div>

            <!-- پارامترهای URL -->
            <div class="card">
                <h2>
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e('پارامترهای URL (Query String)', 'woo-custom-loop-filters'); ?>
                </h2>
                <p><?php esc_html_e('فیلترها و مرتب‌سازی از طریق پارامترهای GET در URL اعمال می‌شوند و با history.pushState در مرورگر حفظ می‌شوند:', 'woo-custom-loop-filters'); ?></p>
                <table class="wclf-admin-table">
                    <thead>
                        <tr>
                            <th style="width: 28%;"><?php esc_html_e('پارامتر', 'woo-custom-loop-filters'); ?></th>
                            <th style="width: 22%;"><?php esc_html_e('نوع', 'woo-custom-loop-filters'); ?></th>
                            <th style="width: 50%;"><?php esc_html_e('توضیح', 'woo-custom-loop-filters'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code class="wclf-code-param">min_price</code> / <code class="wclf-code-param">max_price</code></td>
                            <td><?php esc_html_e('عدد (تومان)', 'woo-custom-loop-filters'); ?></td>
                            <td><?php esc_html_e('بازه قیمت — گام اسلایدر ۱,۰۰۰ تومان', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><code class="wclf-code-param">product_cat_filter</code></td>
                            <td><?php esc_html_e('slug', 'woo-custom-loop-filters'); ?></td>
                            <td><?php esc_html_e('فیلتر دسته‌بندی محصول', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><code class="wclf-code-param">product_brand_filter</code></td>
                            <td><?php esc_html_e('slug', 'woo-custom-loop-filters'); ?></td>
                            <td><?php esc_html_e('فیلتر برند (taxonomy: product_brand)', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><code class="wclf-code-param">stock_filter</code></td>
                            <td><code class="wclf-code-param">instock</code></td>
                            <td><?php esc_html_e('فقط محصولات موجود', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><code class="wclf-code-param">filter_{attribute}</code></td>
                            <td><?php esc_html_e('slug', 'woo-custom-loop-filters'); ?></td>
                            <td><?php esc_html_e('فیلتر ویژگی — مثال: filter_color=red', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><code class="wclf-code-param">orderby</code></td>
                            <td><?php esc_html_e('string', 'woo-custom-loop-filters'); ?></td>
                            <td>
                                <?php esc_html_e('مرتب‌سازی:', 'woo-custom-loop-filters'); ?>
                                <code class="wclf-code-param">discount</code>,
                                <code class="wclf-code-param">discount-asc</code>,
                                <code class="wclf-code-param">popularity</code>,
                                <code class="wclf-code-param">date</code>,
                                <code class="wclf-code-param">sales</code>,
                                <code class="wclf-code-param">price</code>,
                                <code class="wclf-code-param">price-desc</code>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- نکات فنی -->
            <div class="card card-info">
                <h2>
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e('نکات فنی و توسعه', 'woo-custom-loop-filters'); ?>
                </h2>
                <ul>
                    <li>
                        <strong><?php esc_html_e('فیلتر دسته‌بندی هوشمند:', 'woo-custom-loop-filters'); ?></strong>
                        <?php esc_html_e('در آرشیو هر دسته فقط زیردسته‌ها یا دسته‌های مرتبط با محصولات همان صفحه نمایش داده می‌شود. پارامتر ', 'woo-custom-loop-filters'); ?>
                        <code class="wclf-code-param">contextual="yes"</code>
                        <?php esc_html_e(' پیش‌فرض است.', 'woo-custom-loop-filters'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('آرشیوهای پشتیبانی‌شده:', 'woo-custom-loop-filters'); ?></strong>
                        <?php esc_html_e('Shop، دسته‌بندی (product_cat)، برچسب (product_tag)، برند (product_brand) و سایر taxonomyهای محصول — در Theme Builder با Products + Current Query.', 'woo-custom-loop-filters'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('اتصال المنتور:', 'woo-custom-loop-filters'); ?></strong>
                        <?php esc_html_e('روش پیشنهادی: Source = Products و Include = Current Query (بدون Query ID). روش جایگزین: Source = Posts با Query ID = custom_loop_filters.', 'woo-custom-loop-filters'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('محاسبه خودکار تخفیف:', 'woo-custom-loop-filters'); ?></strong>
                        <?php esc_html_e('با هر ذخیره محصول، meta ', 'woo-custom-loop-filters'); ?>
                        <code class="wclf-code-param">discount_percentage</code>
                        <?php esc_html_e(' به‌روز می‌شود. برای محصولات قدیمی یک‌بار ', 'woo-custom-loop-filters'); ?>
                        <code class="wclf-code-badge">update-discounts.php</code>
                        <?php esc_html_e(' را اجرا کنید (CLI یا با دسترسی manage_options).', 'woo-custom-loop-filters'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('بهینه‌سازی دیتابیس:', 'woo-custom-loop-filters'); ?></strong>
                        <?php esc_html_e('کش min/max قیمت (transient ۲۴ ساعته)، meta تخفیف (discount_percentage) و بازدید (post_views) برای سرعت فیلتر و مرتب‌سازی.', 'woo-custom-loop-filters'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('بروزرسانی AJAX متقاطع:', 'woo-custom-loop-filters'); ?></strong>
                        <?php esc_html_e('پس از هر فیلتر، لیست محصولات و تمام wrapperهای فیلتر (قیمت، دسته، برند، موجودی، ویژگی، مرتب‌سازی، شمارنده) همزمان به‌روز می‌شوند.', 'woo-custom-loop-filters'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('امنیت:', 'woo-custom-loop-filters'); ?></strong>
                        <?php esc_html_e('ورودی‌های GET با sanitize/intval پردازش می‌شوند. کوئری‌های SQL با $wpdb->prepare. اسکریپت update-discounts.php فقط برای ادمین یا CLI.', 'woo-custom-loop-filters'); ?>
                    </li>
                </ul>
            </div>

            <!-- ابزارها -->
            <div class="card card-info">
                <h2>
                    <span class="dashicons dashicons-database"></span>
                    <?php esc_html_e('ابزارهای نگهداری', 'woo-custom-loop-filters'); ?>
                </h2>
                <table class="wclf-admin-table">
                    <thead>
                        <tr>
                            <th style="width: 35%;"><?php esc_html_e('ابزار', 'woo-custom-loop-filters'); ?></th>
                            <th style="width: 65%;"><?php esc_html_e('کاربرد', 'woo-custom-loop-filters'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code class="wclf-code-badge">update-discounts.php</code></td>
                            <td>
                                <?php esc_html_e('محاسبه bulk درصد تخفیف همه محصولات و ذخیره در discount_percentage. اجرا از ترمینال:', 'woo-custom-loop-filters'); ?>
                                <br><code class="wclf-code-badge">php wp-content/plugins/woo-custom-loop-filters/update-discounts.php</code>
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('حذف افزونه (Uninstall)', 'woo-custom-loop-filters'); ?></td>
                            <td>
                                <?php esc_html_e('فقط transient/optionهای wclf_* و cookie ردیابی بازدید افزونه. متاهای مشترک مثل discount_percentage و post_views حذف نمی‌شوند.', 'woo-custom-loop-filters'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
