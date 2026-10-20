/* Lieferhinweis und Handy-Navigation bewegen sich gleich:
   beide nur mit dem Scrollen, beide erst ganz oben wieder da.
   Die Leiste hängt an der Scroll-Zeitlinie über die ganze Strecke,
   damit sie im selben Bild läuft. Ohne Zeitlinie setzt das Skript
   dieselbe Strecke. Bestellungen und Warenkorb kleben per CSS.
   Am Desktop bleibt der Hinweis die wegscrollende oberste Zeile.
   scrollTop wird nie angefasst, sonst hängt die Seite. */
(function () {
    function start() {
        var shop = document.querySelector('.tab-pane[data-tab="order"]');
        if (!shop || shop.dataset.deadlineBound) return;
        shop.dataset.deadlineBound = '1';

        var phone = window.matchMedia('(max-width: 767px)');
        var timeline = false;
        try {
            timeline = window.CSS && CSS.supports('animation-timeline: scroll()');
        } catch (e) {
            timeline = false;
        }
        var lastSpan = -1;

        function px(name, value) {
            if (value <= 0.2) document.body.style.removeProperty(name);
            else document.body.style.setProperty(name, value.toFixed(2) + 'px');
        }

        /* Ganze Scrollstrecke. Die Zeitlinie rechnet daraus genau -scrollTop. */
        function publishSpan() {
            var max = 0;
            if (phone.matches) max = Math.max(0, shop.scrollHeight - shop.clientHeight);
            if (Math.abs(max - lastSpan) < 0.4) return;
            lastSpan = max;
            if (max <= 0.2) document.body.style.removeProperty('--nav-span');
            else document.body.style.setProperty('--nav-span', max.toFixed(2));
        }

        function linked() {
            if (phone.matches && timeline) document.body.setAttribute('data-nav-linked', '');
            else document.body.removeAttribute('data-nav-linked');
        }

        /* Abstand vom Kopf der Platte bis unter den Hinweis.
           Nur der Desktop nutzt das, um genau diese Zeile wegzulassen. */
        function measure() {
            linked();
            publishSpan();
            if (phone.matches) {
                shop.style.removeProperty('--deadline-stick');
                return;
            }
            var wrap = shop.querySelector('.shop-header-wrap');
            var el = shop.querySelector('.deadline-clip');
            if (!wrap || !el) {
                shop.style.removeProperty('--deadline-stick');
                return;
            }
            var stick = el.getBoundingClientRect().bottom - wrap.getBoundingClientRect().top;
            if (stick < 1) shop.style.removeProperty('--deadline-stick');
            else shop.style.setProperty('--deadline-stick', stick.toFixed(2) + 'px');
        }

        /* Dieselbe Strecke wie der Inhalt, falls die Zeitlinie fehlt. */
        function applyNav() {
            publishSpan();
            if (!phone.matches || document.body.hasAttribute('data-nav-hold') || document.body.hasAttribute('data-nav-linked')) {
                px('--nav-shift', 0);
                return;
            }
            var y = shop.scrollTop || 0;
            if (y < 0) y = 0;
            px('--nav-shift', y);
        }

        window.hausMeliShopNav = function (onShop) {
            if (!phone.matches) {
                document.body.removeAttribute('data-nav-hold');
                px('--nav-shift', 0);
                return;
            }
            if (!onShop) {
                document.body.setAttribute('data-nav-hold', '');
                px('--nav-shift', 0);
                return;
            }
            document.body.removeAttribute('data-nav-hold');
            applyNav();
        };

        shop.addEventListener('scroll', applyNav, { passive: true });
        measure();
        applyNav();

        window.addEventListener('resize', function () {
            lastSpan = -1;
            measure();
            applyNav();
        });

        if (window.ResizeObserver) {
            var world = shop.querySelector('.world') || shop;
            new ResizeObserver(function () {
                lastSpan = -1;
                measure();
                applyNav();
            }).observe(world);
        }

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                lastSpan = -1;
                measure();
                applyNav();
            });
        }

        var hooked = false;
        function hookLivewire() {
            if (hooked || typeof Livewire === 'undefined' || !Livewire.hook) return;
            hooked = true;
            Livewire.hook('morph.updated', function () {
                lastSpan = -1;
                measure();
                applyNav();
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
