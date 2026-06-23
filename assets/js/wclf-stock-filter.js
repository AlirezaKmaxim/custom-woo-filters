(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const stockToggle = document.getElementById('stockToggle');
        if (!stockToggle) return;

        stockToggle.addEventListener('change', function() {
            const url = new URL(window.location.href);
            
            if (this.checked) {
                url.searchParams.set('stock_filter', 'instock');
            } else {
                url.searchParams.delete('stock_filter');
            }
            
            window.location.href = url.toString();
        });
    });
})();
