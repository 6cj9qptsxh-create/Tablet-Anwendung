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
        var pull = 0;
        var velocity = 0;
        var pullY = shop.scrollTop || 0;
        var pullFrame = 0;
        var pullTimer = 0;

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
            var y = motionOk() ? pull : 0;
            topBtn.style.setProperty('--shop-top-pull', y.toFixed(1) + 'px');
        }

        function restPull() {
            if (pullTimer) {
                clearTimeout(pullTimer);
                pullTimer = 0;
            }
            if (pullFrame) {
                cancelAnimationFrame(pullFrame);
                pullFrame = 0;
            }
            pull = 0;
            velocity = 0;
            pullY = shop.scrollTop || 0;
            applyPull();
        }

        function releasePull() {
            pull *= 0.88;
            if (Math.abs(pull) < 0.4) {
                pull = 0;
                velocity = 0;
                pullFrame = 0;
                applyPull();
                return;
            }
            applyPull();
            pullFrame = requestAnimationFrame(releasePull);
        }

        /* Der Inhalt zieht den Pfeil ein Stück mit. Danach gleitet er zurück. */
        function tug() {
            var y = shop.scrollTop || 0;
            var delta = y - pullY;
            pullY = y;
            if (!motionOk()) {
                if (pull) restPull();
                return;
            }
            if (!delta) return;
            if (pullFrame) {
                cancelAnimationFrame(pullFrame);
                pullFrame = 0;
            }
            if (pullTimer) {
                clearTimeout(pullTimer);
                pullTimer = 0;
            }
            velocity = velocity * 0.35 + delta * 0.65;
            pull = clampPull(-velocity * 0.55, -10, 6);
            applyPull();
            pullTimer = setTimeout(function () {
                pullTimer = 0;
                velocity = 0;
                if (!pullFrame) pullFrame = requestAnimationFrame(releasePull);
            }, 80);
        }

        function syncTop() {
            var topBtn = shop.querySelector('.shop-to-top');
            if (!topBtn) return;
            var y = shop.scrollTop || 0;
            topBtn.hidden = !(y > 48 && !document.body.hasAttribute('data-nav-hold'));
            if (topBtn.hidden) restPull();
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
