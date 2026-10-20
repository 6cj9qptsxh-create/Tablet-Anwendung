/* Lieferhinweis und Handy-Navigation sind getrennt.
   Der Browser scrollt den Hinweis weg: hier wird nur seine Höhe gemessen
   und als --deadline-stick an den Tab geschrieben. Die Navigation ist eine
   eigene Leiste, weg beim Runterscrollen, sofort zurück beim Hochscrollen,
   nur per Verschiebung. scrollTop wird nie angefasst, sonst hängt die Seite. */
(function () {
    function start() {
        var shop = document.querySelector('.tab-pane[data-tab="order"]');
        if (!shop || shop.dataset.deadlineBound) return;
        shop.dataset.deadlineBound = '1';

        var navHide = 0;
        var lastY = shop.scrollTop || 0;
        var navH = 40;
        var phone = window.matchMedia('(max-width: 767px)');

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

        function gapAfter(el) {
            var next = el.nextElementSibling;
            var parent = el.parentElement;
            if (!next || !parent) return 0;
            var cs = getComputedStyle(parent);
            var g = parseFloat(cs.rowGap);
            if (!isFinite(g)) g = parseFloat(cs.gap);
            if (!isFinite(g)) return 0;
            return g;
        }

        /* Abstand vom Kopf der klebenden Platte bis unter den Hinweis,
           inklusive des Abstands zur nächsten Zeile. Der scrollt mit weg,
           sonst bleibt ein leerer Streifen stehen. */
        function measure() {
            var wrap = shop.querySelector('.shop-header-wrap');
            var el = shop.querySelector('.deadline-clip');
            if (!wrap || !el) {
                shop.style.removeProperty('--deadline-stick');
                return;
            }
            var mb = parseFloat(getComputedStyle(el).marginBottom) || 0;
            var stick = el.getBoundingClientRect().bottom + mb + gapAfter(el) - wrap.getBoundingClientRect().top;
            if (stick < 1) shop.style.removeProperty('--deadline-stick');
            else shop.style.setProperty('--deadline-stick', stick.toFixed(2) + 'px');
        }

        function onScroll() {
            var y = shop.scrollTop;
            if (y < 0) y = 0;
            var delta = y - lastY;
            lastY = y;
            if (!phone.matches) return;
            if (y <= 0) navHide = 0;
            else navHide += delta;
            applyNav(false);
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
