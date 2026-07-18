(function() {
    'use strict';

    window.wclf_is_debug_enabled = function() {
        if (window.wclfDebugConfig && window.wclfDebugConfig.enabled) {
            return true;
        }

        try {
            return window.localStorage.getItem('wclf_debug') === '1';
        } catch (e) {
            return false;
        }
    };

    window.wclf_log = function(level, message, data) {
        if (!window.wclf_is_debug_enabled()) {
            return;
        }

        const prefix = '[WCLF][' + level + '] ' + message;
        if (typeof data === 'undefined') {
            console.log(prefix);
            return;
        }

        console.log(prefix, data);
    };

    window.wclf_count_products_in_container = function(container) {
        if (!container) {
            return 0;
        }

        const selectors = [
            '.product',
            '.woocommerce-loop-product__link',
            '.e-loop-item',
            'article.product',
            '[data-elementor-type="loop-item"]'
        ];

        let maxCount = 0;
        selectors.forEach(function(selector) {
            const count = container.querySelectorAll(selector).length;
            if (count > maxCount) {
                maxCount = count;
            }
        });

        return maxCount;
    };

    window.wclf_filter_param_keys = [
        'min_price',
        'max_price',
        'product_cat_filter',
        'product_brand_filter',
        'stock_filter',
        'orderby'
    ];

    window.wclf_has_active_filters = function(urlString) {
        try {
            const url = new URL(urlString || window.location.href, window.location.origin);
            for (let i = 0; i < window.wclf_filter_param_keys.length; i++) {
                const key = window.wclf_filter_param_keys[i];
                if (url.searchParams.has(key) && url.searchParams.get(key) !== '') {
                    return true;
                }
            }
            const keys = url.searchParams.keys();
            let entry = keys.next();
            while (!entry.done) {
                if (String(entry.value).indexOf('filter_') === 0 && url.searchParams.get(entry.value) !== '') {
                    return true;
                }
                entry = keys.next();
            }
        } catch (e) {
            return false;
        }
        return false;
    };

    window.wclf_get_clear_filters_url = function() {
        const url = new URL(window.location.href);
        window.wclf_filter_param_keys.forEach(function(key) {
            url.searchParams.delete(key);
        });
        const toDelete = [];
        url.searchParams.forEach(function(value, key) {
            if (String(key).indexOf('filter_') === 0) {
                toDelete.push(key);
            }
        });
        toDelete.forEach(function(key) {
            url.searchParams.delete(key);
        });
        return url.toString();
    };

    window.wclf_ensure_empty_styles = function() {
        if (document.getElementById('wclf-empty-results-styles')) {
            return;
        }
        const styleSheet = document.createElement('style');
        styleSheet.id = 'wclf-empty-results-styles';
        styleSheet.type = 'text/css';
        styleSheet.textContent = `
            .wclf-empty-results {
                width: 100%;
                max-width: 480px;
                margin: 48px auto;
                padding: 28px 24px;
                text-align: center;
                box-sizing: border-box;
                direction: rtl;
            }
            .wclf-empty-results__title {
                margin: 0 0 10px;
                font-size: 1.15rem;
                font-weight: 700;
                color: #1f2937;
                line-height: 1.5;
            }
            .wclf-empty-results__message {
                margin: 0 0 20px;
                font-size: 0.95rem;
                color: #6b7280;
                line-height: 1.7;
            }
            .wclf-empty-results__reset {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 44px;
                padding: 10px 22px;
                border: 0;
                border-radius: 8px;
                background: #e7a439;
                color: #fff;
                font-size: 0.95rem;
                font-weight: 600;
                cursor: pointer;
                text-decoration: none;
                line-height: 1.4;
            }
            .wclf-empty-results__reset:hover,
            .wclf-empty-results__reset:focus {
                background: #d0922f;
                color: #fff;
                outline: none;
            }
        `;
        document.head.appendChild(styleSheet);
    };

    window.wclf_remove_empty_results = function(container) {
        if (!container) {
            return;
        }
        const existing = container.querySelectorAll('.wclf-empty-results');
        existing.forEach(function(el) {
            el.remove();
        });
    };

    window.wclf_show_empty_results = function(container) {
        if (!container) {
            return;
        }

        window.wclf_ensure_empty_styles();
        window.wclf_remove_empty_results(container);

        const i18n = (window.wclfDebugConfig && window.wclfDebugConfig.i18n)
            ? window.wclfDebugConfig.i18n
            : {};
        const title = i18n.emptyTitle || 'محصولی با این فیلترها پیدا نشد';
        const message = i18n.emptyMessage || 'لطفاً فیلترها را پاک کنید و دوباره جستجو کنید.';
        const resetLabel = i18n.resetButton || 'پاک کردن فیلترها';

        const wrap = document.createElement('div');
        wrap.className = 'wclf-empty-results';
        wrap.setAttribute('role', 'status');
        wrap.innerHTML =
            '<p class="wclf-empty-results__title"></p>' +
            '<p class="wclf-empty-results__message"></p>' +
            '<button type="button" class="wclf-empty-results__reset"></button>';

        wrap.querySelector('.wclf-empty-results__title').textContent = title;
        wrap.querySelector('.wclf-empty-results__message').textContent = message;
        const resetBtn = wrap.querySelector('.wclf-empty-results__reset');
        resetBtn.textContent = resetLabel;
        resetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const href = window.wclf_get_clear_filters_url();
            window.wclf_log('info', 'Empty-state reset clicked', { href: href });
            if (typeof window.wclf_apply_filters === 'function') {
                window.wclf_apply_filters(href);
            } else {
                window.location.href = href;
            }
        });

        container.innerHTML = '';
        container.appendChild(wrap);
        window.wclf_log('info', 'Empty results UI rendered');
    };

    window.wclf_maybe_show_empty_results = function(container, urlHint) {
        if (!container) {
            return false;
        }

        const count = window.wclf_count_products_in_container(container);
        const hasFilters = window.wclf_has_active_filters(urlHint || window.location.href);

        if (count === 0 && hasFilters) {
            window.wclf_show_empty_results(container);
            return true;
        }

        if (count > 0) {
            window.wclf_remove_empty_results(container);
        }

        return false;
    };

    window.wclf_filter_marker_selector = [
        '#priceFilterWrapper',
        '#categoryFilterWrapper',
        '#brandFilterWrapper',
        '#stockFilterWrapper',
        '#attributeFilterWrapper',
        '.beban-product-filters',
        '.wclf-product-count-wrapper'
    ].join(',');

    window.wclf_find_product_container = function(root) {
        const scope = root || document;
        const selectors = [
            '#cprf-products-area',
            '.elementor-widget-loop-grid',
            '.elementor-loop-container',
            'ul.products',
            '.products'
        ];

        for (let i = 0; i < selectors.length; i++) {
            const selector = selectors[i];
            const candidates = scope.querySelectorAll(selector);

            for (let j = 0; j < candidates.length; j++) {
                const el = candidates[j];
                if (el.querySelector(window.wclf_filter_marker_selector)) {
                    window.wclf_log('warn', 'Skipping product container that also contains filters', {
                        selector: selector
                    });
                    continue;
                }
                return {
                    container: el,
                    selector: selector
                };
            }
        }

        return {
            container: null,
            selector: null
        };
    };

    window.wclf_apply_filters = function(url) {
        const found = window.wclf_find_product_container(document);
        const container = found.container;
        const selectorUsed = found.selector;

        window.wclf_log('info', 'apply_filters called', {
            url: url,
            selectorUsed: selectorUsed,
            hasContainer: !!container,
            params: Object.fromEntries(new URL(url, window.location.origin).searchParams.entries())
        });

        if (!container) {
            window.wclf_log('warn', 'No product container found, falling back to full page reload');
            window.location.href = url;
            return;
        }

        const htmlEl = document.documentElement;
        const bodyEl = document.body;

        // Do NOT set overflow:hidden — hiding the scrollbar shifts the viewport.
        // Block scrolling with events while the overlay covers the page.
        const preventScrollEvent = function(e) {
            e.preventDefault();
        };
        const preventScrollKeys = function(e) {
            const codes = [
                'ArrowUp', 'ArrowDown', 'PageUp', 'PageDown',
                'Home', 'End', 'Space', ' ', 'Spacebar'
            ];
            if (codes.indexOf(e.key) !== -1 || codes.indexOf(e.code) !== -1) {
                e.preventDefault();
            }
        };

        document.addEventListener('wheel', preventScrollEvent, { passive: false });
        document.addEventListener('touchmove', preventScrollEvent, { passive: false });
        document.addEventListener('keydown', preventScrollKeys, { passive: false });

        htmlEl.classList.add('wclf-ajax-loading');
        bodyEl.classList.add('wclf-ajax-loading');

        const unlockScroll = function() {
            document.removeEventListener('wheel', preventScrollEvent, { passive: false });
            document.removeEventListener('touchmove', preventScrollEvent, { passive: false });
            document.removeEventListener('keydown', preventScrollKeys, { passive: false });
            htmlEl.classList.remove('wclf-ajax-loading');
            bodyEl.classList.remove('wclf-ajax-loading');
        };

        const removeSpinner = function() {
            const activeSpinner = document.querySelector('.wclf-spinner');
            if (activeSpinner) {
                activeSpinner.remove();
            }
        };

        const cleanupLoadingUi = function() {
            removeSpinner();
            const activeOverlay = document.querySelector('.wclf-ajax-overlay');
            if (activeOverlay) {
                activeOverlay.remove();
            }
            unlockScroll();
        };

        const oldOverlay = document.querySelector('.wclf-ajax-overlay');
        if (oldOverlay) {
            oldOverlay.remove();
        }
        removeSpinner();

        const overlay = document.createElement('div');
        overlay.className = 'wclf-ajax-overlay';

        const spinnerColorRaw = (window.wclfDebugConfig && window.wclfDebugConfig.spinnerColor)
            ? String(window.wclfDebugConfig.spinnerColor)
            : '#333333';
        const spinnerColor = /^#[0-9A-Fa-f]{3,8}$/.test(spinnerColorRaw)
            ? spinnerColorRaw
            : '#333333';

        const spinner = document.createElement('div');
        spinner.className = 'wclf-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        spinner.innerHTML = '<svg width="40" height="40" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><style>.spinner_z9k8{transform-origin:center;animation:spinner_StKS .75s infinite linear}@keyframes spinner_StKS{100%{transform:rotate(360deg)}}</style><path d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z" opacity=".25" fill="' + spinnerColor + '"/><path d="M12,4a8,8,0,0,1,7.89,6.7A1.53,1.53,0,0,0,21.38,12h0a1.5,1.5,0,0,0,1.48-1.75,11,11,0,0,0-21.72,0A1.5,1.5,0,0,0,2.62,12h0a1.53,1.53,0,0,0,1.49-1.3A8,8,0,0,1,12,4Z" class="spinner_z9k8" fill="' + spinnerColor + '"/></svg>';

        let styleSheet = document.getElementById('wclf-spinner-styles');
        if (!styleSheet) {
            styleSheet = document.createElement('style');
            styleSheet.id = 'wclf-spinner-styles';
            styleSheet.type = 'text/css';
            document.head.appendChild(styleSheet);
        }
        styleSheet.textContent = `
            html.wclf-ajax-loading,
            body.wclf-ajax-loading {
                overscroll-behavior: none !important;
            }
            .wclf-ajax-overlay {
                position: fixed !important;
                top: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
                width: auto !important;
                height: auto !important;
                max-width: none !important;
                max-height: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-sizing: border-box !important;
                background-color: rgba(255, 255, 255, 0.45) !important;
                -webkit-backdrop-filter: blur(6px) !important;
                backdrop-filter: blur(6px) !important;
                z-index: 99998 !important;
                display: block !important;
                pointer-events: auto !important;
                border-radius: 0 !important;
                overflow: hidden !important;
                touch-action: none !important;
            }
            .wclf-spinner {
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                right: auto !important;
                bottom: auto !important;
                transform: translate(-50%, -50%) !important;
                width: 40px !important;
                height: 40px !important;
                max-width: 40px !important;
                max-height: 40px !important;
                display: flex !important;
                align-items: center;
                justify-content: center;
                z-index: 99999 !important;
                pointer-events: none !important;
                margin: 0 !important;
            }
        `;

        document.body.appendChild(overlay);
        document.body.appendChild(spinner);

        history.pushState(null, '', url);

        const beforeCount = window.wclf_count_products_in_container(container);
        window.wclf_log('info', 'Fetching filtered page', {
            beforeProductCount: beforeCount
        });

        fetch(url, {
            cache: 'no-store',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        })
            .then(function(response) {
                window.wclf_log('info', 'Fetch response received', {
                    ok: response.ok,
                    status: response.status,
                    url: response.url
                });

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.text();
            })
            .then(function(html) {
                try {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newFound = window.wclf_find_product_container(doc);
                    const newContainer = (newFound.selector === selectorUsed && newFound.container)
                        ? newFound.container
                        : doc.querySelector(selectorUsed);

                    window.wclf_log('info', 'Parsed AJAX HTML', {
                        htmlLength: html.length,
                        foundNewContainer: !!newContainer,
                        selectorUsed: selectorUsed
                    });

                    if (newContainer) {
                        container.innerHTML = newContainer.innerHTML;
                        const afterCount = window.wclf_count_products_in_container(container);
                        window.wclf_log('info', 'Products container updated', {
                            afterProductCount: afterCount
                        });

                        if (afterCount === 0) {
                            window.wclf_log('warn', 'No products found after filter. Check PHP query / taxonomies / URL params.', {
                                url: url
                            });
                            window.wclf_maybe_show_empty_results(container, url);
                        } else {
                            window.wclf_remove_empty_results(container);
                        }
                    } else {
                        window.wclf_log('error', 'Matching container not found in AJAX response', {
                            selectorUsed: selectorUsed
                        });
                    }

                    const filterWrappers = [
                        '#priceFilterWrapper',
                        '#categoryFilterWrapper',
                        '#brandFilterWrapper',
                        '#stockFilterWrapper',
                        '#attributeFilterWrapper',
                        '.beban-product-filters',
                        '.wclf-product-count-wrapper'
                    ];

                    filterWrappers.forEach(function(fwSelector) {
                        const currentElements = document.querySelectorAll(fwSelector);
                        const newElements = doc.querySelectorAll(fwSelector);

                        window.wclf_log('debug', 'Updating filter wrapper', {
                            selector: fwSelector,
                            currentCount: currentElements.length,
                            newCount: newElements.length
                        });

                        currentElements.forEach(function(el, index) {
                            if (newElements[index]) {
                                el.innerHTML = newElements[index].innerHTML;
                            }
                        });
                    });

                    if (typeof window.wclf_init_price_filter === 'function') {
                        window.wclf_init_price_filter();
                    }
                    if (typeof window.wclf_init_category_filter === 'function') {
                        window.wclf_init_category_filter();
                    }
                    if (typeof window.wclf_init_brand_filter === 'function') {
                        window.wclf_init_brand_filter();
                    }
                    if (typeof window.wclf_init_stock_filter === 'function') {
                        window.wclf_init_stock_filter();
                    }
                    if (typeof window.wclf_init_attribute_filter === 'function') {
                        window.wclf_init_attribute_filter();
                    }
                    if (typeof window.wclf_init_sorting_filter === 'function') {
                        window.wclf_init_sorting_filter();
                    }
                    if (typeof window.wclf_init_mobile_sheets === 'function') {
                        window.wclf_init_mobile_sheets();
                    }

                    window.wclf_log('info', 'Filter re-initialization complete');
                } finally {
                    cleanupLoadingUi();
                }
            })
            .catch(function(error) {
                cleanupLoadingUi();
                window.wclf_log('error', 'AJAX Filter failed', {
                    message: error && error.message ? error.message : error,
                    url: url
                });
                console.error('AJAX Filter error:', error);
                window.location.href = url;
            });
    };

    window.wclf_init_sorting_filter = function() {
        const sortingContainer = document.querySelector('.beban-product-filters');
        if (!sortingContainer) {
            window.wclf_log('debug', 'Sorting container not found');
            return;
        }

        const links = sortingContainer.querySelectorAll('.beban-filter-item');
        links.forEach(function(link) {
            const newLink = link.cloneNode(true);
            link.parentNode.replaceChild(newLink, link);

            newLink.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                window.wclf_log('info', 'Sorting clicked', { href: href });
                if (href && href !== '#') {
                    window.wclf_apply_filters(href);
                }
            });
        });
    };

    window.wclf_boot = function() {
        window.wclf_log('info', 'WCLF AJAX core initialized', {
            debugEnabled: window.wclf_is_debug_enabled(),
            pageUrl: window.location.href
        });
        window.wclf_init_sorting_filter();
        if (typeof window.wclf_init_mobile_sheets === 'function') {
            window.wclf_init_mobile_sheets();
        }

        const found = window.wclf_find_product_container(document);
        if (found.container) {
            window.wclf_maybe_show_empty_results(found.container);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.wclf_boot);
    } else {
        window.wclf_boot();
    }

    window.addEventListener('popstate', function() {
        window.wclf_log('info', 'popstate detected, reloading page');
        window.location.reload();
    });
})();
