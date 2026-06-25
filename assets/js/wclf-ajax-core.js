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
        spinner.innerHTML = '<svg width="40" height="40" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><style>.spinner_z9k8{transform-origin:center;animation:spinner_StKS .75s infinite linear}@keyframes spinner_StKS{100%{transform:rotate(360deg)}}</style><path d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z" opacity=".25" fill="#999999"/><path d="M12,4a8,8,0,0,1,7.89,6.7A1.53,1.53,0,0,0,21.38,12h0a1.5,1.5,0,0,0,1.48-1.75,11,11,0,0,0-21.72,0A1.5,1.5,0,0,0,2.62,12h0a1.53,1.53,0,0,0,1.49-1.3A8,8,0,0,1,12,4Z" class="spinner_z9k8" fill="#333333"/></svg>';

        // Ensure preloader CSS exists in head
        if (!document.getElementById('wclf-spinner-styles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'wclf-spinner-styles';
            styleSheet.type = 'text/css';
            styleSheet.innerText = `
                .wclf-ajax-overlay {
                    position: absolute !important;
                    top: -15px !important;
                    left: -15px !important;
                    right: -15px !important;
                    bottom: -15px !important;
                    width: auto !important;
                    height: auto !important;
                    background-color: rgba(255, 255, 255, 0.4) !important;
                    backdrop-filter: blur(10px) !important;
                    -webkit-backdrop-filter: blur(10px) !important;
                    z-index: 98 !important;
                    display: block !important;
                    pointer-events: auto !important;
                    border-radius: 12px !important;
                }
                .wclf-spinner {
                    position: fixed !important;
                    top: 50% !important;
                    left: 50% !important;
                    transform: translate(-50%, -50%) !important;
                    width: 40px !important;
                    height: 40px !important;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 99999 !important;
                }
                @media (min-width: 768px) {
                    .wclf-spinner {
                        top: 30% !important;
                    }
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
