/* Lieferhinweis und Handy-Navigation folgen dem Shop-Scroll.
   Der Hinweis gleitet nur beim Runterscrollen weg, mit halber
   Finger-Geschwindigkeit, und kommt erst ganz oben wieder.
   Die Navigation ist das Gegenteil: sie kommt sofort zurück,
   sobald nach oben gescrollt wird.
   scrollTop wird nie angefasst, sonst hängt die Seite. */
(function () {
    var SPEED = 0.5;

    function start() {
        var shop = document.querySelector('.tab-pane[data-tab="order"]');
        if (!shop || shop.dataset.deadlineBound) return;
        shop.dataset.deadlineBound = '1';

        var hide = 0;
        var navHide = 0;
        var lastY = shop.scrollTop || 0;
        var box = 0;
        var gap = 0;
        var navH = 40;
        var phone = window.matchMedia('(max-width: 767px)');
        var quiet = false;

        function clip() {
            return shop.querySelector('.deadline-clip');
        }

        function clearMotion() {
            shop.style.removeProperty('--deadline-hide');
            shop.style.removeProperty('--deadline-shift');
            shop.style.removeProperty('--deadline-clip-h');
            shop.removeAttribute('data-deadline-hidden');
        }

        function readNavH() {
            var nav = document.querySelector('.shell-nav');
            if (!nav) return;
            var h = nav.offsetHeight;
            if (h > 0) navH = h;
        }

        function applyNav(forceShow) {
            if (!phone.matches) {
                document.body.style.removeProperty('--nav-shift');
                return;
            }
            if (forceShow) navHide = 0;
            if (navHide < 0) navHide = 0;
            if (navHide > navH) navHide = navH;
            document.body.style.setProperty('--nav-shift', navHide.toFixed(2) + 'px');
        }

        /* Andere Tabs zeigen die Leiste. Zurück im Shop gilt der letzte Stand. */
        window.hausMeliShopNav = function (onShop) {
            if (!phone.matches) {
                document.body.style.removeProperty('--nav-shift');
                return;
            }
            if (!onShop) {
                document.body.style.setProperty('--nav-shift', '0px');
                return;
            }
            readNavH();
            applyNav(false);
        };

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
            quiet = true;
            if (!el || !banner) {
                box = 0;
                gap = 0;
                hide = 0;
                clearMotion();
                quiet = false;
                lastY = shop.scrollTop;
                return;
            }
            var raw = el.parentElement ? getComputedStyle(el.parentElement).rowGap : '0';
            var n = parseFloat(raw);
            gap = isFinite(n) ? n : 0;
            shop.style.setProperty('--deadline-row-gap', gap.toFixed(2) + 'px');
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
            } else {
                if (hide > box) hide = box;
                apply();
            }
            quiet = false;
            lastY = shop.scrollTop;
        }

        function onScroll() {
            if (quiet) {
                lastY = shop.scrollTop;
                return;
            }
            var y = shop.scrollTop;
            if (y < 0) y = 0;
            var delta = y - lastY;
            lastY = y;

            if (phone.matches) {
                if (y <= 0) navHide = 0;
                else if (delta > 0) navHide += delta;
                else if (delta < 0) navHide += delta;
                applyNav(false);
            }

            /* Ganz oben: Hinweis wieder zeigen. Hochscrollen allein reicht nicht. */
            if (y <= 0) {
                if (hide !== 0) {
                    hide = 0;
                    apply();
                }
                return;
            }
            if (delta <= 0) return;
            if (box <= 0) measure();
            if (box <= 0) return;
            var next = hide + delta * SPEED;
            if (next > box) next = box;
            if (next === hide) return;
            hide = next;
            apply();
        }

        shop.addEventListener('scroll', onScroll, { passive: true });
        readNavH();
        measure();
        applyNav(true);
        lastY = shop.scrollTop;

        window.addEventListener('resize', function () {
            readNavH();
            measure();
            applyNav(false);
            lastY = shop.scrollTop;
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
