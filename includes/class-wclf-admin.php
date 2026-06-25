<?php
defined('ABSPATH') || exit;

class WCLF_Admin {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
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
        </style>
        <div class="wrap wclf-admin-wrap">
            <h1><?php esc_html_e('تنظیمات فیلترهای سفارشی المنتور و ووکامرس', 'woo-custom-loop-filters'); ?></h1>
            <hr class="wp-header-end">
            
            <!-- اتصال به المنتور -->
            <div class="card card-warning">
                <h2 class="warning-title" style="color: #d54e21;">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('اقدام بسیار مهم برای اتصال به المنتور (Query ID)', 'woo-custom-loop-filters'); ?>
                </h2>
                <p>
                    <?php esc_html_e('یکی از کلیدی‌ترین ویژگی‌های این افزونه، قابلیت اعمال فیلترها و مرتب‌سازی به صورت ایجکس بر روی ویجت‌های نمایش محصولات المنتور (مانند Loop Grid و Product Grid) است. برای فعال‌سازی این اتصال، مراحل زیر را در ویرایشگر المنتور دنبال کنید:', 'woo-custom-loop-filters'); ?>
                </p>
                <ol>
                    <li><?php esc_html_e('ویجت Loop Grid یا هر ویجت محصولات المنتور را در صفحه انتخاب کنید.', 'woo-custom-loop-filters'); ?></li>
                    <li><?php esc_html_e('در منوی تنظیمات ویجت (سمت راست)، به تب Content (محتوا) و سپس بخش Query (کوئری) بروید.', 'woo-custom-loop-filters'); ?></li>
                    <li><?php esc_html_e('فیلد شناسه کوئری (Query ID) را پیدا کرده و دقیقاً مقدار زیر را وارد کنید:', 'woo-custom-loop-filters'); ?></li>
                </ol>
                <div class="wclf-query-id-box">
                    custom_loop_filters
                </div>
                <p style="font-size: 13px; color: #d54e21; font-weight: bold; margin-bottom: 0;">
                    <?php esc_html_e('* توجه: در صورتی که این مقدار را وارد نکنید، فیلترهای صفحه روی محصولات کوئری المنتور هیچ تاثیری نخواهند داشت.', 'woo-custom-loop-filters'); ?>
                </p>
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
                            </td>
                            <td>
                                <strong><?php esc_html_e('فیلتر دسته‌بندی محصولات:', 'woo-custom-loop-filters'); ?></strong>
                                <?php esc_html_e('نمایش دسته‌بندی‌ها به صورت دکمه‌های رادیویی هوشمند. فقط دسته‌هایی که دارای محصول هستند در این بخش لیست می‌شوند.', 'woo-custom-loop-filters'); ?>
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
                                <?php esc_html_e('گزینه‌های مرتب‌سازی پیشرفته و سریع شامل: بیشترین تخفیف، پربازدیدترین، جدیدترین، پرفروش‌ترین، ارزان‌ترین و گران‌ترین.', 'woo-custom-loop-filters'); ?>
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

            <!-- نکات فنی -->
            <div class="card card-info">
                <h2>
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e('نکات فنی و توسعه', 'woo-custom-loop-filters'); ?>
                </h2>
                <ul>
                    <li><strong><?php esc_html_e('سازگاری کامل با صفحات آرشیو:', 'woo-custom-loop-filters'); ?></strong> <?php esc_html_e('این افزونه به صورت خودکار فیلترها را روی صفحات پیش‌فرض فروشگاه ووکامرس (Shop Archive) و صفحات آرشیو دسته‌بندی و برچسب نیز اعمال می‌کند و نیازی به شناسه کوئری برای آن‌ها نیست.', 'woo-custom-loop-filters'); ?></li>
                    <li><strong><?php esc_html_e('بهینه‌سازی دیتابیس:', 'woo-custom-loop-filters'); ?></strong> <?php esc_html_e('درصد تخفیف محصولات و تعداد بازدیدها به صورت پیش‌محاسبه در دیتابیس (فیلدهای متای اختصاصی) ثبت می‌شوند تا سرعت پاسخ‌دهی فیلترها و مرتب‌سازی در سایت‌های با تعداد محصول زیاد دچار افت نشود.', 'woo-custom-loop-filters'); ?></li>
                    <li><strong><?php esc_html_e('بروزرسانی ایجکس متقاطع:', 'woo-custom-loop-filters'); ?></strong> <?php esc_html_e('تمام المان‌های فیلتر در صورت قرارگیری در بخش‌های مختلف (مانند سایدبار، فوتر یا هدر) به صورت متقاطع بروزرسانی شده و وضعیت‌های انتخاب‌شده را حفظ می‌کنند.', 'woo-custom-loop-filters'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}
