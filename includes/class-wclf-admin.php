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
        <div class="wrap wclf-admin-wrap" style="direction: rtl; text-align: right; max-width: 800px; margin-top: 20px;">
            <h1><?php esc_html_e('تنظیمات فیلترهای سفارشی المنتور و ووکامرس', 'woo-custom-loop-filters'); ?></h1>
            <hr class="wp-header-end">
            
            <div class="card" style="margin-top: 20px; padding: 20px; border-right: 4px solid #ffb900; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="color: #d54e21; margin-top: 0; font-size: 1.3em;">
                    <span class="dashicons dashicons-warning" style="vertical-align: middle; margin-left: 5px;"></span>
                    <?php esc_html_e('اقدام بسیار مهم برای اتصال به المنتور (Query ID)', 'woo-custom-loop-filters'); ?>
                </h2>
                <p style="font-size: 14px; line-height: 1.8;">
                    <?php esc_html_e('برای اینکه فیلترها و مرتب‌سازی روی ویجت‌های المنتور (مانند Loop Grid یا Product Grid) تاثیر بگذارند، باید مراحل زیر را انجام دهید:', 'woo-custom-loop-filters'); ?>
                </p>
                <ol style="font-size: 14px; line-height: 1.8; padding-right: 20px;">
                    <li><?php esc_html_e('ویجت Loop Grid یا هر ویجت نمایش محصولات المنتور را در صفحه ویرایشگر المنتور انتخاب کنید.', 'woo-custom-loop-filters'); ?></li>
                    <li><?php esc_html_e('در منوی تنظیمات ویجت، به تب Content و بخش Query بروید.', 'woo-custom-loop-filters'); ?></li>
                    <li><?php esc_html_e('فیلد Query ID را پیدا کرده و دقیقاً عبارت زیر را در آن بنویسید:', 'woo-custom-loop-filters'); ?></li>
                </ol>
                <div style="background: #f0f0f1; padding: 10px 15px; border-radius: 4px; font-family: monospace; font-size: 16px; font-weight: bold; display: inline-block; direction: ltr; margin: 10px 0;">
                    custom_loop_filters
                </div>
                <p style="font-size: 13px; color: #666; margin-bottom: 0;">
                    <?php esc_html_e('* توجه: در صورتی که این مقدار را وارد نکنید، فیلترهای صفحه روی کوئری المنتور تاثیری نخواهند داشت.', 'woo-custom-loop-filters'); ?>
                </p>
            </div>

            <div class="card" style="margin-top: 20px; padding: 20px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <span class="dashicons dashicons-shortcode" style="vertical-align: middle; margin-left: 5px;"></span>
                    <?php esc_html_e('لیست شورت‌کدهای موجود', 'woo-custom-loop-filters'); ?>
                </h2>
                <p style="font-size: 14px; line-height: 1.8;">
                    <?php esc_html_e('شما می‌توانید از شورت‌کدهای زیر در هر بخش از صفحات المنتور یا سایدبارها استفاده کنید تا فیلترها نمایش داده شوند:', 'woo-custom-loop-filters'); ?>
                </p>
                
                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th style="width: 30%; font-weight: bold; text-align: right;"><?php esc_html_e('شورت‌کد', 'woo-custom-loop-filters'); ?></th>
                            <th style="width: 70%; font-weight: bold; text-align: right;"><?php esc_html_e('توضیحات و عملکرد', 'woo-custom-loop-filters'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code style="font-size: 14px; background: #f0f0f1; padding: 3px 6px; border-radius: 3px; direction: ltr; display: inline-block;">[elementor_price_filter]</code></td>
                            <td style="line-height: 1.6;"><?php esc_html_e('فیلتر بازه قیمتی محصولات. این شورت‌کد کمترین و بیشترین قیمت موجود در دیتابیس را به عنوان محدوده انتخاب می‌کند و امکان فیلتر قیمت را به کاربر می‌دهد.', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><code style="font-size: 14px; background: #f0f0f1; padding: 3px 6px; border-radius: 3px; direction: ltr; display: inline-block;">[elementor_category_filter]</code></td>
                            <td style="line-height: 1.6;"><?php esc_html_e('فیلتر دسته‌بندی محصولات به صورت دکمه‌های رادیویی. فقط دسته‌هایی که دارای محصول هستند را نمایش می‌دهد.', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><code style="font-size: 14px; background: #f0f0f1; padding: 3px 6px; border-radius: 3px; direction: ltr; display: inline-block;">[elementor_stock_filter]</code></td>
                            <td style="line-height: 1.6;"><?php esc_html_e('فیلتر موجودی محصولات. یک سوئیچ تغییر وضعیت (Toggle Switch) که با روشن کردن آن فقط محصولات موجود در انبار نمایش داده می‌شوند.', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                        <tr>
                            <td><code style="font-size: 14px; background: #f0f0f1; padding: 3px 6px; border-radius: 3px; direction: ltr; display: inline-block;">[beban_product_filters]</code></td>
                            <td style="line-height: 1.6;"><?php esc_html_e('بخش مرتب‌سازی پیشرفته محصولات. گزینه‌هایی شامل: بیشترین تخفیف، پربازدیدترین، جدیدترین، پرفروش‌ترین، ارزان‌ترین و گران‌ترین.', 'woo-custom-loop-filters'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="margin-top: 20px; padding: 20px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <span class="dashicons dashicons-info" style="vertical-align: middle; margin-left: 5px;"></span>
                    <?php esc_html_e('نکات فنی و توسعه', 'woo-custom-loop-filters'); ?>
                </h2>
                <ul style="font-size: 14px; line-height: 1.8; list-style-type: disc; padding-right: 20px;">
                    <li><strong><?php esc_html_e('سازگاری با آرشیو پیش‌فرض ووکامرس:', 'woo-custom-loop-filters'); ?></strong> <?php esc_html_e('این پلاگین به صورت کاملاً خودکار فیلترها را روی صفحات پیش‌فرض آرشیو محصولات ووکامرس (مانند صفحه فروشگاه و آرشیو دسته‌بندی‌ها) نیز اعمال می‌کند و نیازی به تنظیم Query ID برای آن صفحات نیست.', 'woo-custom-loop-filters'); ?></li>
                    <li><strong><?php esc_html_e('شمارش بازدید محصولات:', 'woo-custom-loop-filters'); ?></strong> <?php esc_html_e('تعداد بازدیدها در متای post_views ثبت می‌شود. برای جلوگیری از ثبت تکراری بازدیدها توسط یک کاربر یا ربات‌های خزنده، از مکانیزم بررسی کوکی و ربات‌یاب استفاده شده است.', 'woo-custom-loop-filters'); ?></li>
                    <li><strong><?php esc_html_e('محاسبه درصد تخفیف:', 'woo-custom-loop-filters'); ?></strong> <?php esc_html_e('درصد تخفیف هر محصول در زمان ذخیره یا بروزرسانی در متای _discount_percentage ذخیره می‌شود تا قابلیت مرتب‌سازی بر اساس بیشترین تخفیف با سرعت بالا اجرا شود.', 'woo-custom-loop-filters'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}
