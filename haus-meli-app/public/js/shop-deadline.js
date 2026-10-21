/* Handy-Navigation folgt der Scrollrichtung: nach unten weg,
   nach oben sofort wieder da. Ganz oben ist sie immer sichtbar.
   Der Lieferhinweis scrollt mit den Produkten, das Skript fasst ihn nicht an.
   Der Pfeil springt einmal an den Anfang der Shop-Fläche.
   scrollTop wird im Scroll-Listener nie geschrieben, sonst hängt die Seite. */
(function () {
    function start() {
        var shop = document.querySelector('.tab-pane[data-tab="order"]');
        if (!shop || shop.dataset.deadlineBound) return;
        shop.dataset.deadlineBound = '1';

        var phone = window.matchMedia('(max-width: 767px)');
        var anchor = shop.scrollTop || 0;
        var away = false;

        function syncTop() {
            var topBtn = shop.querySelector('.shop-to-top');
            if (!topBtn) return;
            var y = shop.scrollTop || 0;
            topBtn.hidden = !(y > 48 && !document.body.hasAttribute('data-nav-hold'));
        }

        function paint() {
            if (away && phone.matches && !document.body.hasAttribute('data-nav-hold')) {
                document.body.setAttribute('data-nav-away', '');
            } else {
                document.body.removeAttribute('data-nav-away');
            }
            syncTop();
        }

        /* Kleine Schritte sammeln, damit ein kurzes Hochscrollen reicht. */
        function onScroll() {
            if (!phone.matches || document.body.hasAttribute('data-nav-hold')) {
                syncTop();
                return;
            }
            var y = shop.scrollTop || 0;
            if (y < 0) y = 0;
            if (y <= 4) {
                away = false;
                anchor = y;
            } else {
                var delta = y - anchor;
                if (delta > 6) {
                    away = true;
                    anchor = y;
                } else if (delta < -6) {
                    away = false;
                    anchor = y;
                }
            }
            paint();
        }

        window.hausMeliShopNav = function (onShop) {
            if (!onShop) {
                document.body.setAttribute('data-nav-hold', '');
                away = false;
                paint();
                return;
            }
            document.body.removeAttribute('data-nav-hold');
            var y = shop.scrollTop || 0;
            away = phone.matches && y > 4;
            anchor = y;
            paint();
        };

        shop.addEventListener('click', function (event) {
            var hit = event.target && event.target.closest ? event.target.closest('.shop-to-top') : null;
            if (!hit || !shop.contains(hit)) return;
            var reduce = false;
            try {
                reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            } catch (e) {
                reduce = false;
            }
            shop.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
        });

        shop.addEventListener('scroll', onScroll, { passive: true });
        shop.style.removeProperty('--deadline-stick');
        window.hausMeliShopNav(true);

        window.addEventListener('resize', function () {
            anchor = shop.scrollTop || 0;
            if (!phone.matches) away = false;
            paint();
        });

        if (window.ResizeObserver) {
            var world = shop.querySelector('.world') || shop;
            new ResizeObserver(function () {
                shop.style.removeProperty('--deadline-stick');
            }).observe(world);
        }

        var hooked = false;
        function hookLivewire() {
            if (hooked || typeof Livewire === 'undefined' || !Livewire.hook) return;
            hooked = true;
            Livewire.hook('morph.updated', function () {
                shop.style.removeProperty('--deadline-stick');
                syncTop();
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
