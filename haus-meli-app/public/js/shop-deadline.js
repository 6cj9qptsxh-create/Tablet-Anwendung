/* Lieferhinweis im Shop: beim Scrollen weggleiten, mit halber Geschwindigkeit.
   Wie bei YouTube wandert die Leiste nur halb so weit wie der Finger.
   Der frei gewordene Platz wird aus dem Scroll herausgerechnet, damit die
   Produkte weiterhin 1:1 mit dem Finger laufen. Hochscrollen holt die
   Zeile auf demselben Weg zurück. */
(function () {
    var SPEED = 0.5;

    function start() {
        var shop = document.querySelector('.tab-pane[data-tab="order"]');
        if (!shop || shop.dataset.deadlineBound) return;
        shop.dataset.deadlineBound = '1';

        var hide = 0;
        var lastY = shop.scrollTop || 0;
        var box = 0;
        var gap = 0;
        var lock = false;
        var phone = window.matchMedia('(max-width: 767px)');

        function clip() {
            return shop.querySelector('.deadline-clip');
        }

        function clearMotion() {
            shop.style.removeProperty('--deadline-hide');
            shop.style.removeProperty('--deadline-shift');
            shop.style.removeProperty('--deadline-clip-h');
            shop.removeAttribute('data-deadline-hidden');
        }

        function syncNav() {
            if (!phone.matches) return;
            var nav = document.querySelector('.shell-nav');
            if (!nav) return;
            var navH = nav.offsetHeight;
            var consumed = parseFloat(shop.style.getPropertyValue('--deadline-hide')) || 0;
            var shift = Math.min(Math.max(shop.scrollTop, 0) + consumed, navH);
            document.body.style.setProperty('--nav-shift', shift + 'px');
        }

        function apply() {
            var el = clip();
            var range = box;
            if (!el || range <= 0) {
                hide = 0;
                clearMotion();
                return;
            }
            if (hide < 0) hide = 0;
            if (hide > range) hide = range;
            if (hide <= 0.2) {
                hide = 0;
                clearMotion();
                return;
            }
            shop.style.setProperty('--deadline-hide', hide.toFixed(2) + 'px');
            shop.style.setProperty('--deadline-shift', hide.toFixed(2) + 'px');
            shop.style.setProperty('--deadline-clip-h', Math.max(0, box - hide).toFixed(2) + 'px');
            if (hide >= range - 0.4) shop.setAttribute('data-deadline-hidden', '');
            else shop.removeAttribute('data-deadline-hidden');
        }

        function measure() {
            var el = clip();
            var banner = el && el.querySelector('.deadline-info');
            if (!el || !banner) {
                box = 0;
                gap = 0;
                hide = 0;
                clearMotion();
                return;
            }
            var kept = shop.scrollTop;
            var raw = el.parentElement ? getComputedStyle(el.parentElement).rowGap : '0';
            var n = parseFloat(raw);
            gap = isFinite(n) ? n : 0;
            shop.style.setProperty('--deadline-row-gap', gap.toFixed(2) + 'px');
            /* Zum Messen kurz aufklappen, sonst ist die Zeile bei display:none 0 hoch. */
            shop.removeAttribute('data-deadline-hidden');
            shop.style.removeProperty('--deadline-clip-h');
            shop.style.removeProperty('--deadline-shift');
            var cs = getComputedStyle(banner);
            var mt = parseFloat(cs.marginTop) || 0;
            var mb = parseFloat(cs.marginBottom) || 0;
            box = banner.offsetHeight + mt + mb + gap;
            if (box <= 0) {
                hide = 0;
                clearMotion();
                return;
            }
            if (hide > box) hide = box;
            apply();
            if (Math.abs(shop.scrollTop - kept) > 0.5) {
                lock = true;
                shop.scrollTop = kept;
                lock = false;
            }
        }

        function onScroll() {
            if (lock) return;
            var y = shop.scrollTop;
            if (y <= 0) {
                lastY = 0;
                if (hide !== 0) {
                    hide = 0;
                    apply();
                    syncNav();
                }
                return;
            }
            if (box <= 0) measure();
            var delta = y - lastY;
            lastY = y;
            if (delta === 0) return;
            var range = box;
            if (range <= 0) return;
            var next = hide + delta * SPEED;
            if (next < 0) next = 0;
            if (next > range) next = range;
            var applied = next - hide;
            if (applied === 0) return;
            var headerEl = clip() ? clip().parentElement : null;
            var beforeH = headerEl ? headerEl.offsetHeight : 0;
            hide = next;
            apply();
            var shrunk = headerEl ? beforeH - headerEl.offsetHeight : applied;
            var target = y - shrunk;
            if (Math.abs(shop.scrollTop - target) > 0.2) {
                lock = true;
                shop.scrollTop = target;
                lastY = shop.scrollTop;
                lock = false;
            } else {
                lastY = shop.scrollTop;
            }
            syncNav();
        }

        shop.addEventListener('scroll', onScroll, { passive: true });
        measure();
        lastY = shop.scrollTop;

        window.addEventListener('resize', function () {
            measure();
            lastY = shop.scrollTop;
            syncNav();
        });

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                measure();
                lastY = shop.scrollTop;
            });
        }

        var hooked = false;
        function hookLivewire() {
            if (hooked || typeof Livewire === 'undefined' || !Livewire.hook) return;
            hooked = true;
            Livewire.hook('morph.updated', function () {
                measure();
                lastY = shop.scrollTop;
            });
        }
        document.addEventListener('livewire:init', hookLivewire);
        hookLivewire();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
