(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const wrapper = document.getElementById('priceFilterWrapper');
        if (!wrapper) return;

        const toggleBtn = document.getElementById('priceToggle');
        const content = document.getElementById('priceFilterContent');
        const sliderMin = document.getElementById('sliderMin');
        const sliderMax = document.getElementById('sliderMax');
        const priceMinLabel = document.getElementById('priceMin');
        const priceMaxLabel = document.getElementById('priceMax');
        const rangeFill = document.getElementById('rangeFill');
        const applyBtn = document.getElementById('applyFilterBtn');

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

            window.location.href = url.toString();
        }

        if (toggleBtn && content) {
            toggleBtn.addEventListener('click', function() {
                content.classList.toggle('open');
                toggleBtn.classList.toggle('active');
            });
        }

        sliderMin.addEventListener('input', updateSlider);
        sliderMax.addEventListener('input', updateSlider);
        
        if (applyBtn) {
            applyBtn.addEventListener('click', applyFilter);
        }

        const resetBtn = document.getElementById('resetFilterBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                const url = new URL(window.location.href);
                url.searchParams.delete('min_price');
                url.searchParams.delete('max_price');
                window.location.href = url.toString();
            });
        }

        updateSlider();
    });
})();
