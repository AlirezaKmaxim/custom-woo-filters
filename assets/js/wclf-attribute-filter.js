(function() {
    'use strict';

    window.wclf_init_attribute_filter = function() {
        const wrappers = document.querySelectorAll('.custom-attribute-filter-wrapper');
        wrappers.forEach(function(wrapper) {
            const attributeSlug = wrapper.getAttribute('data-attribute');
            if (!attributeSlug) return;

            const toggleBtn = wrapper.querySelector('.dropdown-toggle');
            const content = wrapper.querySelector('.dropdown-content');

            if (toggleBtn && content) {
                const newToggle = toggleBtn.cloneNode(true);
                toggleBtn.parentNode.replaceChild(newToggle, toggleBtn);
                newToggle.addEventListener('click', function() {
                    content.classList.toggle('open');
                    newToggle.classList.toggle('active');
                });
            }

            const radios = wrapper.querySelectorAll('input[type="radio"]');
            radios.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    const url = new URL(window.location.href);
                    const paramKey = 'filter_' + attributeSlug;
                    
                    if (this.value === '') {
                        url.searchParams.delete(paramKey);
                    } else {
                        url.searchParams.set(paramKey, this.value);
                    }
                    
                    window.wclf_apply_filters(url.toString());
                });
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.wclf_init_attribute_filter);
    } else {
        window.wclf_init_attribute_filter();
    }
})();
