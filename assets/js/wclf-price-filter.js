(function() {
    'use strict';

    window.wclf_init_price_filter = function() {
        const wrapper = document.getElementById('priceFilterWrapper');
        if (!wrapper) return;

        const toggleBtn = document.getElementById('priceToggle');
        const content = document.getElementById('priceFilterContent');
        const sliderMin = document.getElementById('sliderMin');
        const sliderMax = document.getElementById('sliderMax');
        const priceMinLabel = document.getElementById('priceMin');
        const priceMaxLabel = document.getElementById('priceMax');
        const rangeFill = document.getElementById('rangeFill');
        const resetBtn = document.getElementById('resetFilterBtn');

        if (!sliderMin || !sliderMax || !priceMinLabel || !priceMaxLabel || !rangeFill) return;

        const MIN_VALUE = parseInt(sliderMin.min);
        const MAX_VALUE = parseInt(sliderMax.max);
        const GAP = Math.max(1000, Math.round((MAX_VALUE - MIN_VALUE) / 100));

        function formatPrice(num) {
            return new Intl.NumberFormat('fa-IR').format(num) + ' تومان';
        }

        function updateSlider() {
            let minVal = parseInt(sliderMin.value);
            let maxVal = parseInt(sliderMax.value);

            if (minVal >= maxVal - GAP) {
                minVal = maxVal - GAP;
                sliderMin.value = minVal;
            }
            if (maxVal <= minVal + GAP) {
                maxVal = minVal + GAP;
                sliderMax.value = maxVal;
            }

            priceMinLabel.textContent = formatPrice(minVal);
            priceMaxLabel.textContent = formatPrice(maxVal);

            const percentMin = ((minVal - MIN_VALUE) / (MAX_VALUE - MIN_VALUE)) * 100;
            const percentMax = ((maxVal - MIN_VALUE) / (MAX_VALUE - MIN_VALUE)) * 100;

            rangeFill.style.left = percentMin + '%';
            rangeFill.style.width = (percentMax - percentMin) + '%';
        }

        function applyFilter() {
            const minVal = parseInt(sliderMin.value);
            const maxVal = parseInt(sliderMax.value);

            const url = new URL(window.location.href);

            if (minVal > MIN_VALUE) {
                url.searchParams.set('min_price', minVal);
            } else {
                url.searchParams.delete('min_price');
            }

            if (maxVal < MAX_VALUE) {
                url.searchParams.set('max_price', maxVal);
            } else {
                url.searchParams.delete('max_price');
            }

            window.wclf_apply_filters(url.toString());
        }

        if (toggleBtn && content) {
            const newToggle = toggleBtn.cloneNode(true);
            toggleBtn.parentNode.replaceChild(newToggle, toggleBtn);
            newToggle.addEventListener('click', function() {
                content.classList.toggle('open');
                newToggle.classList.toggle('active');
            });
        }

        sliderMin.addEventListener('input', updateSlider);
        sliderMax.addEventListener('input', updateSlider);

        // Apply automatically on drag release
        sliderMin.addEventListener('change', applyFilter);
        sliderMax.addEventListener('change', applyFilter);

        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                const url = new URL(window.location.href);
                url.searchParams.delete('min_price');
                url.searchParams.delete('max_price');
                window.wclf_apply_filters(url.toString());
            });
        }

        updateSlider();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.wclf_init_price_filter);
    } else {
        window.wclf_init_price_filter();
    }
})();
