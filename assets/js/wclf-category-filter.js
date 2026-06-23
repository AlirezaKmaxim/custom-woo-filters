(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const wrapper = document.getElementById('categoryFilterWrapper');
        if (!wrapper) return;

        const toggleBtn = document.getElementById('catToggle');
        const content = document.getElementById('catFilterContent');

        if (toggleBtn && content) {
            toggleBtn.addEventListener('click', function() {
                content.classList.toggle('open');
                toggleBtn.classList.toggle('active');
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
                
                window.location.href = url.toString();
            });
        });
    });
})();
