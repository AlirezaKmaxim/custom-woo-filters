(function() {
    'use strict';

    window.wclf_init_category_filter = function() {
        const wrapper = document.getElementById('categoryFilterWrapper');
        if (!wrapper) return;

        const toggleBtn = document.getElementById('catToggle');
        const content = document.getElementById('catFilterContent');

        if (toggleBtn && content) {
            const newToggle = toggleBtn.cloneNode(true);
            toggleBtn.parentNode.replaceChild(newToggle, toggleBtn);
            newToggle.addEventListener('click', function() {
                content.classList.toggle('open');
                newToggle.classList.toggle('active');
            });
        }

        const radios = wrapper.querySelectorAll('input[name="cat_filter_radio"]');
        radios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                const url = new URL(window.location.href);
                
                if (this.value === '') {
                    url.searchParams.delete('product_cat_filter');
                } else {
                    url.searchParams.set('product_cat_filter', this.value);
                }
                
                window.wclf_apply_filters(url.toString());
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.wclf_init_category_filter);
    } else {
        window.wclf_init_category_filter();
    }
})();
