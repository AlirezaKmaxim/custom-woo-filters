(function() {
    'use strict';

    window.wclf_init_stock_filter = function() {
        const stockToggle = document.getElementById('stockToggle');
        if (!stockToggle) return;

        const newToggle = stockToggle.cloneNode(true);
        stockToggle.parentNode.replaceChild(newToggle, stockToggle);

        newToggle.addEventListener('change', function() {
            const url = new URL(window.location.href);
            
            if (this.checked) {
                url.searchParams.set('stock_filter', 'instock');
            } else {
                url.searchParams.delete('stock_filter');
            }
            
            window.wclf_apply_filters(url.toString());
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.wclf_init_stock_filter);
    } else {
        window.wclf_init_stock_filter();
    }
})();
