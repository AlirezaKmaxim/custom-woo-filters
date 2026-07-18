/**
 * Mobile bottom-sheets for WCLF filters / sorting.
 * Triggers (Elementor CSS classes):
 *   .wclf-open-filters  → filters sheet
 *   .wclf-open-sorting  → sorting sheet
 * Sources:
 *   .wclf-mobile-filters-source
 *   .wclf-mobile-sorting-source
 */
(function() {
    'use strict';

    var MQ = '(max-width: 767px)';
    var SHEET_ROOT_ID = 'wclf-mobile-sheets-root';
    var homeSlots = {
        filters: null,
        sorting: null
    };
    var bound = false;
    var applyHooked = false;
    var dragState = null;
    var CLOSE_DRAG_PX = 100;

    function isMobile() {
        return window.matchMedia(MQ).matches;
    }

    function i18n(key, fallback) {
        var pack = (window.wclfDebugConfig && window.wclfDebugConfig.i18n) || {};
        return pack[key] || fallback;
    }

    function ensureDom() {
        var root = document.getElementById(SHEET_ROOT_ID);
        if (root) {
            if (!root.querySelector('.wclf-sheet__grab')) {
                root.parentNode.removeChild(root);
            } else {
                var existingFilters = root.querySelector('#wclf-sheet-filters');
                var existingSorting = root.querySelector('#wclf-sheet-sorting');
                if (existingFilters) {
                    bindSheetDrag(existingFilters);
                }
                if (existingSorting) {
                    bindSheetDrag(existingSorting);
                }
                return root;
            }
        }

        root = document.createElement('div');
        root.id = SHEET_ROOT_ID;
        root.innerHTML =
            '<div class="wclf-sheet-backdrop" data-wclf-sheet-close="1" hidden></div>' +
            '<div class="wclf-sheet" id="wclf-sheet-filters" role="dialog" aria-modal="true" aria-label="" hidden>' +
            '  <div class="wclf-sheet__grab">' +
            '    <div class="wclf-sheet__handle" aria-hidden="true"></div>' +
            '    <div class="wclf-sheet__header">' +
            '      <h3 class="wclf-sheet__title"></h3>' +
            '      <button type="button" class="wclf-sheet__close" data-wclf-sheet-close="1" aria-label=""></button>' +
            '    </div>' +
            '  </div>' +
            '  <div class="wclf-sheet__body" data-wclf-sheet-body="filters"></div>' +
            '</div>' +
            '<div class="wclf-sheet" id="wclf-sheet-sorting" role="dialog" aria-modal="true" aria-label="" hidden>' +
            '  <div class="wclf-sheet__grab">' +
            '    <div class="wclf-sheet__handle" aria-hidden="true"></div>' +
            '    <div class="wclf-sheet__header">' +
            '      <h3 class="wclf-sheet__title"></h3>' +
            '      <button type="button" class="wclf-sheet__close" data-wclf-sheet-close="1" aria-label=""></button>' +
            '    </div>' +
            '  </div>' +
            '  <div class="wclf-sheet__body" data-wclf-sheet-body="sorting"></div>' +
            '</div>';

        document.body.appendChild(root);

        var filterSheet = root.querySelector('#wclf-sheet-filters');
        var sortSheet = root.querySelector('#wclf-sheet-sorting');
        filterSheet.setAttribute('aria-label', i18n('sheetFiltersTitle', 'فیلترها'));
        sortSheet.setAttribute('aria-label', i18n('sheetSortingTitle', 'مرتب‌سازی'));
        filterSheet.querySelector('.wclf-sheet__title').textContent = i18n('sheetFiltersTitle', 'فیلترها');
        sortSheet.querySelector('.wclf-sheet__title').textContent = i18n('sheetSortingTitle', 'مرتب‌سازی');

        var closeLabel = i18n('sheetClose', 'بستن');
        root.querySelectorAll('.wclf-sheet__close').forEach(function(btn) {
            btn.setAttribute('aria-label', closeLabel);
            btn.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">' +
                '<path fill="currentColor" d="M18.3 5.71a1 1 0 0 0-1.41 0L12 10.59 7.11 5.7A1 1 0 0 0 5.7 7.11L10.59 12l-4.9 4.89a1 1 0 1 0 1.41 1.42L12 13.41l4.89 4.9a1 1 0 0 0 1.42-1.41L13.41 12l4.9-4.89a1 1 0 0 0-.01-1.4z"/>' +
                '</svg>';
        });

        bindSheetDrag(filterSheet);
        bindSheetDrag(sortSheet);

        return root;
    }

    function rememberHome(kind, source) {
        if (!source || homeSlots[kind]) {
            return;
        }
        homeSlots[kind] = {
            parent: source.parentNode,
            next: source.nextSibling,
            source: source
        };
    }

    function mountSource(kind) {
        var selector = kind === 'filters'
            ? '.wclf-mobile-filters-source'
            : '.wclf-mobile-sorting-source';
        var source = document.querySelector(selector);
        if (!source) {
            window.wclf_log && window.wclf_log('warn', 'Mobile sheet source missing', { kind: kind, selector: selector });
            return null;
        }

        rememberHome(kind, source);

        var body = document.querySelector('[data-wclf-sheet-body="' + kind + '"]');
        if (!body) {
            return null;
        }

        if (source.parentNode !== body) {
            body.appendChild(source);
        }
        source.classList.add('wclf-sheet-mounted');
        return source;
    }

    function unmountSource(kind) {
        var slot = homeSlots[kind];
        if (!slot || !slot.source || !slot.parent) {
            return;
        }

        slot.source.classList.remove('wclf-sheet-mounted');

        if (slot.next && slot.next.parentNode === slot.parent) {
            slot.parent.insertBefore(slot.source, slot.next);
        } else {
            slot.parent.appendChild(slot.source);
        }
    }

    function closeAllSheets() {
        var root = document.getElementById(SHEET_ROOT_ID);
        if (!root) {
            return;
        }

        var backdrop = root.querySelector('.wclf-sheet-backdrop');
        var sheets = root.querySelectorAll('.wclf-sheet');

        sheets.forEach(function(sheet) {
            sheet.classList.remove('is-open', 'is-dragging');
            sheet.style.transform = '';
            sheet.style.transition = '';
            sheet.setAttribute('hidden', 'hidden');
        });
        if (backdrop) {
            backdrop.classList.remove('is-open');
            backdrop.style.opacity = '';
            backdrop.setAttribute('hidden', 'hidden');
        }

        root.classList.remove('wclf-sheets-active');
        document.documentElement.classList.remove('wclf-sheet-open');
        document.body.classList.remove('wclf-sheet-open');
        dragState = null;

        unmountSource('filters');
        unmountSource('sorting');
    }

    function bindSheetDrag(sheet) {
        if (!sheet || sheet.getAttribute('data-wclf-drag-bound') === '1') {
            return;
        }
        sheet.setAttribute('data-wclf-drag-bound', '1');

        var grab = sheet.querySelector('.wclf-sheet__grab');
        if (!grab) {
            return;
        }

        function onTouchStart(e) {
            if (!sheet.classList.contains('is-open') || !e.touches || !e.touches[0]) {
                return;
            }
            // Don't start drag from the close button itself.
            if (e.target.closest && e.target.closest('[data-wclf-sheet-close]')) {
                return;
            }
            var t = e.touches[0];
            dragState = {
                sheet: sheet,
                startY: t.clientY,
                dy: 0,
                active: true
            };
            sheet.classList.add('is-dragging');
            sheet.style.transition = 'none';
        }

        function onTouchMove(e) {
            if (!dragState || !dragState.active || dragState.sheet !== sheet || !e.touches || !e.touches[0]) {
                return;
            }
            var dy = Math.max(0, e.touches[0].clientY - dragState.startY);
            dragState.dy = dy;
            sheet.style.transform = 'translateY(' + dy + 'px)';

            var backdrop = document.querySelector('.wclf-sheet-backdrop');
            if (backdrop) {
                backdrop.style.opacity = String(Math.max(0.12, 0.45 * (1 - dy / 320)));
            }

            if (dy > 6) {
                e.preventDefault();
            }
        }

        function onTouchEnd() {
            if (!dragState || !dragState.active || dragState.sheet !== sheet) {
                return;
            }
            var dy = dragState.dy || 0;
            var shouldClose = dy >= CLOSE_DRAG_PX;

            sheet.classList.remove('is-dragging');
            sheet.style.transition = '';

            var backdrop = document.querySelector('.wclf-sheet-backdrop');
            if (backdrop) {
                backdrop.style.opacity = '';
            }

            dragState = null;

            if (shouldClose) {
                sheet.style.transform = '';
                closeAllSheets();
                return;
            }

            // Snap back open.
            sheet.style.transform = 'translateY(0)';
            window.requestAnimationFrame(function() {
                if (sheet.classList.contains('is-open')) {
                    sheet.style.transform = '';
                }
            });
        }

        grab.addEventListener('touchstart', onTouchStart, { passive: true });
        grab.addEventListener('touchmove', onTouchMove, { passive: false });
        grab.addEventListener('touchend', onTouchEnd);
        grab.addEventListener('touchcancel', onTouchEnd);
    }

    function applyMobileFilterAccordionDefaults(sourceEl) {
        if (!isMobile()) {
            return;
        }

        var source = sourceEl || document.querySelector('.wclf-mobile-filters-source');
        if (!source) {
            return;
        }

        var priceContent = source.querySelector('#priceFilterContent');
        var priceToggle = source.querySelector('#priceToggle');

        source.querySelectorAll('.dropdown-content').forEach(function(el) {
            if (el.id === 'priceFilterContent') {
                el.classList.add('open');
            } else {
                el.classList.remove('open');
            }
        });

        source.querySelectorAll('.dropdown-toggle').forEach(function(btn) {
            if (btn.id === 'priceToggle') {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        if (priceContent) {
            priceContent.classList.add('open');
        }
        if (priceToggle) {
            priceToggle.classList.add('active');
        }
    }

    function openSheet(kind) {
        if (!isMobile()) {
            return;
        }

        ensureDom();
        closeAllSheets();

        var source = mountSource(kind);
        if (!source) {
            return;
        }

        if (kind === 'filters') {
            applyMobileFilterAccordionDefaults(source);
        }

        var root = document.getElementById(SHEET_ROOT_ID);
        var backdrop = root.querySelector('.wclf-sheet-backdrop');
        var sheet = document.getElementById(kind === 'filters' ? 'wclf-sheet-filters' : 'wclf-sheet-sorting');

        root.classList.add('wclf-sheets-active');
        backdrop.removeAttribute('hidden');
        sheet.removeAttribute('hidden');

        // Force reflow then animate.
        void sheet.offsetHeight;
        backdrop.classList.add('is-open');
        sheet.classList.add('is-open');

        document.documentElement.classList.add('wclf-sheet-open');
        document.body.classList.add('wclf-sheet-open');

        window.wclf_log && window.wclf_log('info', 'Mobile sheet opened', { kind: kind });
    }

    function onDocumentClick(e) {
        var closeEl = e.target.closest('[data-wclf-sheet-close]');
        if (closeEl) {
            e.preventDefault();
            closeAllSheets();
            return;
        }

        var openFilters = e.target.closest('.wclf-open-filters');
        if (openFilters) {
            e.preventDefault();
            openSheet('filters');
            return;
        }

        var openSorting = e.target.closest('.wclf-open-sorting');
        if (openSorting) {
            e.preventDefault();
            openSheet('sorting');
        }
    }

    function onKeydown(e) {
        if (e.key === 'Escape' && document.body.classList.contains('wclf-sheet-open')) {
            closeAllSheets();
        }
    }

    function hookApplyFilters() {
        if (applyHooked || typeof window.wclf_apply_filters !== 'function') {
            return;
        }
        applyHooked = true;
        var original = window.wclf_apply_filters;
        window.wclf_apply_filters = function(url) {
            if (document.body.classList.contains('wclf-sheet-open')) {
                closeAllSheets();
            }
            return original.apply(this, arguments);
        };
    }

    function bindUi() {
        if (bound) {
            return;
        }
        bound = true;
        document.addEventListener('click', onDocumentClick, true);
        document.addEventListener('keydown', onKeydown);
    }

    window.wclf_close_mobile_sheets = closeAllSheets;

    window.wclf_init_mobile_sheets = function() {
        // Do not inject sheet DOM until first open — avoids bottom shadow over site mobile nav.
        bindUi();
        hookApplyFilters();

        if (!isMobile()) {
            closeAllSheets();
        }

        // Refresh home refs if sources were re-rendered by Elementor/AJAX.
        ['filters', 'sorting'].forEach(function(kind) {
            var selector = kind === 'filters'
                ? '.wclf-mobile-filters-source'
                : '.wclf-mobile-sorting-source';
            var source = document.querySelector(selector);
            if (source && !source.classList.contains('wclf-sheet-mounted')) {
                homeSlots[kind] = {
                    parent: source.parentNode,
                    next: source.nextSibling,
                    source: source
                };
            }
        });

        if (isMobile()) {
            applyMobileFilterAccordionDefaults();
        }

        window.wclf_log && window.wclf_log('info', 'Mobile sheets initialized', {
            mobile: isMobile(),
            hasFiltersSource: !!document.querySelector('.wclf-mobile-filters-source'),
            hasSortingSource: !!document.querySelector('.wclf-mobile-sorting-source'),
            hasFiltersTrigger: !!document.querySelector('.wclf-open-filters'),
            hasSortingTrigger: !!document.querySelector('.wclf-open-sorting')
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.wclf_init_mobile_sheets);
    } else {
        window.wclf_init_mobile_sheets();
    }

    if (window.matchMedia) {
        window.matchMedia(MQ).addEventListener('change', function() {
            if (!isMobile()) {
                closeAllSheets();
            }
        });
    }
})();
