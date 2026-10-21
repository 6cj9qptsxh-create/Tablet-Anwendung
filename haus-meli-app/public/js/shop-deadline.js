/* Handy-Navigation folgt der Scrollrichtung: nach unten weg,
   nach oben sofort wieder da. Ganz oben ist sie immer sichtbar.
   Der Lieferhinweis scrollt mit den Produkten, das Skript fasst ihn nicht an.
   Der Pfeil springt einmal an den Anfang der Shop-Fläche und wird beim
   Scrollen kurz mitgezogen, damit er auffällt.
   scrollTop wird im Scroll-Listener nie geschrieben, sonst hängt die Seite. */
(function () {
    function start() {
        var shop = document.querySelector('.tab-pane[data-tab="order"]');
        if (!shop || shop.dataset.deadlineBound) return;
        shop.dataset.deadlineBound = '1';

        var phone = window.matchMedia('(max-width: 767px)');
        var anchor = shop.scrollTop || 0;
        var away = false;
        var shown = 0;
        var target = 0;
        var pullY = shop.scrollTop || 0;
        var pullFrame = 0;
        var lastMove = 0;

        function motionOk() {
            try {
                return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            } catch (e) {
                return true;
            }
        }

        function clampPull(n, min, max) {
            if (n < min) return min;
            if (n > max) return max;
            return n;
        }

        function applyPull() {
            var topBtn = shop.querySelector('.shop-to-top');
            if (!topBtn) return;
            var y = motionOk() ? shown : 0;
            topBtn.style.setProperty('--shop-top-pull', y.toFixed(2) + 'px');
        }

        function stopPull() {
            if (pullFrame) {
                cancelAnimationFrame(pullFrame);
                pullFrame = 0;
            }
            shown = 0;
            target = 0;
            pullY = shop.scrollTop || 0;
            applyPull();
        }

        /* Ziel kommt aus dem Scrollen. Die sichtbare Lage holt es Bild für Bild
           ein, höchstens ein kleines Stück pro Frame, und gleitet danach zurück. */
        function tickPull() {
            if (performance.now() - lastMove > 70) target *= 0.9;
            var diff = target - shown;
            var step = diff * 0.28;
            if (step > 1.4) step = 1.4;
            if (step < -1.4) step = -1.4;
            shown += step;
            if (Math.abs(shown) < 0.2 && Math.abs(target) < 0.2) {
                shown = 0;
                target = 0;
                pullFrame = 0;
                applyPull();
                return;
            }
            applyPull();
            pullFrame = requestAnimationFrame(tickPull);
        }

        function tug() {
            var y = shop.scrollTop || 0;
            var delta = y - pullY;
            pullY = y;
            if (!motionOk()) {
                if (shown || target) stopPull();
                return;
            }
            if (!delta) return;
            lastMove = performance.now();
            var next = clampPull(-delta * 0.45, -10, 6);
            var keep = (next > 0 && target < 0) || (next < 0 && target > 0) ? 0.2 : 0.45;
            target = target * keep + next * (1 - keep);
            if (!pullFrame) pullFrame = requestAnimationFrame(tickPull);
        }

        function syncTop() {
            var topBtn = shop.querySelector('.shop-to-top');
            if (!topBtn) return;
            var y = shop.scrollTop || 0;
            topBtn.hidden = !(y > 48 && !document.body.hasAttribute('data-nav-hold'));
            if (topBtn.hidden) stopPull();
        }

        function paint() {
            if (away && phone.matches && !document.body.hasAttribute('data-nav-hold')) {
                document.body.setAttribute('data-nav-away', '');
            } else {
                document.body.removeAttribute('data-nav-away');
            }
            syncDrop();
            syncTop();
        }

        /* Die Karte rückt nur unter die Navigation, wenn sie schon klebt.
           Vorher bleibt der Andockpunkt bei 12px, sonst setzt sie zu tief an. */
        var dropped = false;
        function syncDrop() {
            var bar = shop.querySelector('.shop-sticky');
            var navOpen = phone.matches
                && !document.body.hasAttribute('data-nav-away')
                && !document.body.hasAttribute('data-nav-hold');
            if (!bar || !navOpen) {
                dropped = false;
                document.body.removeAttribute('data-bar-drop');
                return;
            }
            var barTop = bar.getBoundingClientRect().top - shop.getBoundingClientRect().top;
            if (!dropped && barTop <= 16) dropped = true;
            if (dropped && barTop > 70) dropped = false;
            if (dropped) document.body.setAttribute('data-bar-drop', '');
            else document.body.removeAttribute('data-bar-drop');
        }

        /* Kleine Schritte sammeln, damit ein kurzes Hochscrollen reicht. */
        function onScroll() {
            tug();
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
