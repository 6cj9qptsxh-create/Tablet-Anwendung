/* Lieferhinweis und Handy-Navigation.
   Der Browser scrollt den Hinweis weg. Die Leiste hängt an derselben
   Scroll-Zeitlinie, damit sie nicht hinter dem Kopf herläuft.
   Weiter unten schiebt ein gemeinsamer Versatz beide gleichzeitig.
   scrollTop wird nie angefasst, sonst hängt die Seite. */
(function () {
    function start() {
        var shop = document.querySelector('.tab-pane[data-tab="order"]');
        if (!shop || shop.dataset.deadlineBound) return;
        shop.dataset.deadlineBound = '1';

        var navHide = 0;
        var cover = 0;
        var lastY = shop.scrollTop || 0;
        var navH = 40;
        var navTravel = 40;
        var stickEngage = 0;
        var phone = window.matchMedia('(max-width: 767px)');

        function readNavH() {
            var nav = document.querySelector('.shell-nav');
            var safe = 0;
            if (nav) {
                var h = nav.offsetHeight;
                if (h > 0) navH = h;
                /* Berechnete Pixel. Der Text von env() ist keine Zahl,
                   sonst bleibt die Leiste unter der Statusleiste stehen. */
                var top = parseFloat(getComputedStyle(nav).top);
                if (isFinite(top) && top > 0) safe = top;
            }
            navTravel = safe + navH;
        }

        function px(name, value) {
            if (value <= 0.2) document.body.style.removeProperty(name);
            else document.body.style.setProperty(name, value.toFixed(2) + 'px');
        }

        function linked() {
            return document.body.hasAttribute('data-nav-linked');
        }

        function detectLink() {
            var nav = document.querySelector('.shell-nav');
            var anims = nav && nav.getAnimations ? nav.getAnimations() : [];
            var ok = false;
            for (var i = 0; i < anims.length; i++) {
                var timeline = anims[i].timeline;
                if (timeline && timeline.source === shop) ok = true;
            }
            if (ok && phone.matches) {
                document.body.setAttribute('data-nav-linked', '');
                document.body.removeAttribute('data-nav-fallback');
            } else if (phone.matches) {
                document.body.removeAttribute('data-nav-linked');
                document.body.setAttribute('data-nav-fallback', '');
            } else {
                document.body.removeAttribute('data-nav-linked');
                document.body.removeAttribute('data-nav-fallback');
            }
        }

        /* Scrollposition, ab der der Kopf klebt. Nur messbar, solange
           er noch im Fluss ist: im Kleben steht die Box still. */
        function measureEngage() {
            var wrap = shop.querySelector('.shop-header-wrap');
            if (!wrap) {
                stickEngage = 0;
                return;
            }
            var paneTop = shop.getBoundingClientRect().top;
            var pad = parseFloat(getComputedStyle(shop).paddingTop) || 0;
            var cssTop = parseFloat(getComputedStyle(wrap).top) || 0;
            var tr = getComputedStyle(wrap).translate;
            var ty = 0;
            if (tr && tr !== 'none') {
                var parts = tr.split(' ');
                ty = parseFloat(parts.length > 1 ? parts[1] : parts[0]) || 0;
            }
            var y = shop.scrollTop || 0;
            var rel = wrap.getBoundingClientRect().top - paneTop - pad - ty;
            if (y > 0 && Math.abs(rel - cssTop) < 1) return;
            stickEngage = rel + y - cssTop;
            if (stickEngage < 0) stickEngage = 0;
        }

        function applyCovers() {
            if (!phone.matches || !linked()) {
                px('--nav-cover', 0);
                px('--header-cover', 0);
                return;
            }
            var y = shop.scrollTop || 0;
            if (y < 0) y = 0;
            px('--nav-cover', cover);
            /* Die ersten Pixel der Leiste liegen in der Statusleiste.
               Der Kopf klebt schon darunter und rückt erst mit, wenn die
               Leiste ihn berührt. Ohne Statusleiste ist das die ganze Strecke. */
            var safe = navTravel - navH;
            if (safe < 0) safe = 0;
            var follow = cover - safe;
            if (follow < 0) follow = 0;
            var need = y + cover - safe - stickEngage;
            var headerCover = 0;
            if (need > 0) headerCover = need < follow ? need : follow;
            px('--header-cover', headerCover);
        }

        function applyNav(forceShow) {
            if (!phone.matches) {
                document.body.style.removeProperty('--nav-shift');
                px('--nav-cover', 0);
                px('--header-cover', 0);
                return;
            }
            if (forceShow) {
                navHide = 0;
                cover = 0;
            }
            if (navHide < 0) navHide = 0;
            if (navHide > navH) navHide = navH;
            document.body.style.setProperty('--nav-shift', navHide.toFixed(2) + 'px');
            applyCovers();
        }

        /* Andere Tabs zeigen die Leiste. Zurück im Shop gilt der letzte Stand. */
        window.hausMeliShopNav = function (onShop) {
            if (!phone.matches) {
                document.body.style.removeProperty('--nav-shift');
                document.body.removeAttribute('data-nav-hold');
                px('--nav-cover', 0);
                px('--header-cover', 0);
                return;
            }
            if (!onShop) {
                document.body.setAttribute('data-nav-hold', '');
                document.body.style.setProperty('--nav-shift', '0px');
                px('--nav-cover', 0);
                px('--header-cover', 0);
                return;
            }
            document.body.removeAttribute('data-nav-hold');
            readNavH();
            applyNav(false);
        };

        /* Abstand vom Kopf der Platte bis unter den Hinweis.
           Der Abstand darunter bleibt stehen: das ist das Padding,
           das der klebende Kopf oben behält. */
        function measure() {
            var wrap = shop.querySelector('.shop-header-wrap');
            var el = shop.querySelector('.deadline-clip');
            if (!wrap || !el) {
                shop.style.removeProperty('--deadline-stick');
                measureEngage();
                return;
            }
            var stick = el.getBoundingClientRect().bottom - wrap.getBoundingClientRect().top;
            if (stick < 1) shop.style.removeProperty('--deadline-stick');
            else shop.style.setProperty('--deadline-stick', stick.toFixed(2) + 'px');
            measureEngage();
        }

        function onScroll() {
            var y = shop.scrollTop;
            if (y < 0) y = 0;
            var delta = y - lastY;
            lastY = y;
            if (!phone.matches) return;
            if (linked()) {
                if (y <= navTravel) cover = 0;
                else cover -= delta;
                if (cover < 0) cover = 0;
                if (cover > navTravel) cover = navTravel;
                applyCovers();
                return;
            }
            if (y <= 0) navHide = 0;
            else navHide += delta;
            applyNav(false);
        }

        shop.addEventListener('scroll', onScroll, { passive: true });
        readNavH();
        measure();
        applyNav(true);
        lastY = shop.scrollTop;
        requestAnimationFrame(function () {
            detectLink();
            measure();
            applyCovers();
        });

        window.addEventListener('resize', function () {
            readNavH();
            detectLink();
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
                applyCovers();
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
