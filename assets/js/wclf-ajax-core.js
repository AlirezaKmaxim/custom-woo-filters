(function() {
    'use strict';

    window.wclf_apply_filters = function(url) {
        // Define potential product area selectors
        const selectors = [
            '#cprf-products-area',
            '.elementor-widget-loop-grid',
            '.elementor-loop-container',
            '.products'
        ];

        let container = null;
        let selectorUsed = null;

        for (const selector of selectors) {
            container = document.querySelector(selector);
            if (container) {
                selectorUsed = selector;
                break;
            }
        }

        if (!container) {
            // Fallback: If no container is found, just do a normal page reload
            window.location.href = url;
            return;
        }

        // 1. Show preloader overlay
        // Ensure container is relative so overlay aligns to it
        const originalPosition = window.getComputedStyle(container).position;
        if (originalPosition === 'static') {
            container.style.position = 'relative';
        }

        // Remove any existing overlay first
        const oldOverlay = container.querySelector('.wclf-ajax-overlay');
        if (oldOverlay) {
            oldOverlay.remove();
        }

        const overlay = document.createElement('div');
        overlay.className = 'wclf-ajax-overlay';

        const spinner = document.createElement('div');
        spinner.className = 'wclf-spinner';

        // Ensure preloader CSS exists in head
        if (!document.getElementById('wclf-spinner-styles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'wclf-spinner-styles';
            styleSheet.type = 'text/css';
            styleSheet.innerText = `
                @keyframes wclf-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .wclf-ajax-overlay {
                    position: absolute !important;
                    top: 0 !important;
                    left: 0 !important;
                    width: 100% !important;
                    height: 100% !important;
                    background-color: rgba(255, 255, 255, 0.4) !important;
                    backdrop-filter: blur(10px) !important;
                    -webkit-backdrop-filter: blur(10px) !important;
                    z-index: 9999 !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    pointer-events: auto !important;
                }
                .wclf-spinner {
                    position: fixed !important;
                    top: 50% !important;
                    left: 50% !important;
                    transform: translate(-50%, -50%) !important;
                    width: 40px !important;
                    height: 40px !important;
                    border: 4px solid rgba(0, 0, 0, 0.1) !important;
                    border-top: 4px solid #e7a439 !important;
                    border-radius: 50% !important;
                    animation: wclf-spin 1s linear infinite !important;
                    z-index: 10000 !important;
                }
            `;
            document.head.appendChild(styleSheet);
        }

        overlay.appendChild(spinner);
        container.appendChild(overlay);

        // Update URL in browser
        history.pushState(null, '', url);

        // Fetch new content
        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContainer = doc.querySelector(selectorUsed);

                if (newContainer) {
                    // Replace products container contents
                    container.innerHTML = newContainer.innerHTML;
                }

                // Also update all filters wrappers to match the new URL
                const filterWrappers = [
                    '#priceFilterWrapper',
                    '#categoryFilterWrapper',
                    '#stockFilterWrapper',
                    '#attributeFilterWrapper',
                    '.beban-product-filters',
                    '.wclf-product-count-wrapper'
                ];

                filterWrappers.forEach(fwSelector => {
                    const currentElements = document.querySelectorAll(fwSelector);
                    const newElements = doc.querySelectorAll(fwSelector);
                    currentElements.forEach((el, index) => {
                        if (newElements[index]) {
                            el.innerHTML = newElements[index].innerHTML;
                        }
                    });
                });

                // Re-initialize filter scripts
                if (typeof window.wclf_init_price_filter === 'function') {
                    window.wclf_init_price_filter();
                }
                if (typeof window.wclf_init_category_filter === 'function') {
                    window.wclf_init_category_filter();
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
            })
            .catch(error => {
                console.error('AJAX Filter error:', error);
                // Fallback to normal page load on error
                window.location.href = url;
            });
    };

    window.wclf_init_sorting_filter = function() {
        const sortingContainer = document.querySelector('.beban-product-filters');
        if (!sortingContainer) return;

        const links = sortingContainer.querySelectorAll('.beban-filter-item');
        links.forEach(function(link) {
            const newLink = link.cloneNode(true);
            link.parentNode.replaceChild(newLink, link);

            newLink.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                if (href && href !== '#') {
                    window.wclf_apply_filters(href);
                }
            });
        });
    };

    // Initialize sorting filters on DOM load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.wclf_init_sorting_filter);
    } else {
        window.wclf_init_sorting_filter();
    }

    // Handle back/forward browser navigation
    window.addEventListener('popstate', function() {
        window.location.reload();
    });
})();
