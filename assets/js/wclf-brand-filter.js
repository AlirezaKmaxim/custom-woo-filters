(function() {
    'use strict';

    window.wclf_init_brand_filter = function() {
        const wrapper = document.getElementById('brandFilterWrapper');
        if (!wrapper) {
            if (typeof window.wclf_log === 'function') {
                window.wclf_log('debug', 'Brand filter wrapper not found');
            }
            return;
        }

        const toggleBtn = document.getElementById('brandToggle');
        const content = document.getElementById('brandFilterContent');

        if (toggleBtn && content) {
            const newToggle = toggleBtn.cloneNode(true);
            toggleBtn.parentNode.replaceChild(newToggle, toggleBtn);
            newToggle.addEventListener('click', function() {
                content.classList.toggle('open');
                newToggle.classList.toggle('active');
            });
        }

        const radios = wrapper.querySelectorAll('input[name="brand_filter_radio"]');
        if (typeof window.wclf_log === 'function') {
            window.wclf_log('info', 'Brand filter initialized', {
                radioCount: radios.length
            });
        }

        radios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                const url = new URL(window.location.href);

                if (this.value === '') {
                    url.searchParams.delete('product_brand_filter');
                } else {
                    url.searchParams.set('product_brand_filter', this.value);
                }

                if (typeof window.wclf_log === 'function') {
                    window.wclf_log('info', 'Brand filter changed', {
                        brandSlug: this.value,
                        url: url.toString()
                    });
                }

                window.wclf_apply_filters(url.toString());
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.wclf_init_brand_filter);
    } else {
        window.wclf_init_brand_filter();
    }
})();
