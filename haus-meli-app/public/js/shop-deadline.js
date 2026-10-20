/* Lieferhinweis und Handy-Navigation folgen dem Shop-Scroll 1:1.
   Der Hinweis gleitet nur per Verschieben weg. Der Platz schließt und
   öffnet sich je einmal, oben langsam. Hochscrollen allein holt ihn nicht.
   Die Navigation kommt beim Hochscrollen zurück und schiebt den Shop-Kopf mit.
   scrollTop wird nie angefasst, sonst hängt die Seite. */
(function () {
    var SPEED = 1;

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
        var away = false;
        var opening = false;
        var phone = window.matchMedia('(max-width: 767px)');
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        var quiet = false;

        function clip() {
            return shop.querySelector('.deadline-clip');
        }

        function setShift(px) {
            if (px <= 0.2) shop.style.removeProperty('--deadline-shift');
            else shop.style.setProperty('--deadline-shift', px.toFixed(2) + 'px');
        }

        function setBox(px) {
            if (px > 0) shop.style.setProperty('--deadline-box', px.toFixed(2) + 'px');
            else shop.style.removeProperty('--deadline-box');
        }

        function setFlag(name, on) {
            if (on) shop.setAttribute(name, '');
            else shop.removeAttribute(name);
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

        function finishClose() {
            if (!away) return;
            setFlag('data-deadline-gone', true);
            setFlag('data-deadline-closing', false);
            setFlag('data-deadline-sliding', false);
        }

        function finishOpen() {
            opening = false;
            away = false;
            hide = 0;
            setShift(0);
            setFlag('data-deadline-hidden', false);
            setFlag('data-deadline-gone', false);
            setFlag('data-deadline-opening', false);
            setFlag('data-deadline-closing', false);
            setFlag('data-deadline-sliding', false);
        }

        function beginClose() {
            if (away || box <= 0) return;
            away = true;
            opening = false;
            hide = box;
            setShift(box);
            setFlag('data-deadline-sliding', false);
            setFlag('data-deadline-opening', false);
            setFlag('data-deadline-closing', true);
            setFlag('data-deadline-hidden', true);
            if (reduced.matches) finishClose();
        }

        function beginOpen() {
            if (opening) return;
            if (!away && hide <= 0.2 && !shop.hasAttribute('data-deadline-hidden')) {
                hide = 0;
                setShift(0);
                return;
            }
            opening = true;
            away = false;
            setFlag('data-deadline-gone', false);
            setFlag('data-deadline-closing', false);
            setFlag('data-deadline-sliding', false);
            setFlag('data-deadline-opening', true);
            if (!shop.hasAttribute('data-deadline-hidden')) {
                hide = 0;
                setShift(0);
                if (reduced.matches) finishOpen();
                return;
            }
            if (reduced.matches) {
                hide = 0;
                setShift(0);
                finishOpen();
                return;
            }
            /* Ein Frame auf Höhe 0 lassen, sonst springt die Zeile statt zu wachsen. */
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    if (!opening) return;
                    hide = 0;
                    setShift(0);
                    setFlag('data-deadline-hidden', false);
                });
            });
        }

        function restoreAfterMeasure() {
            setBox(box);
            if (away) {
                hide = box;
                setShift(box);
                setFlag('data-deadline-sliding', false);
                setFlag('data-deadline-opening', false);
                setFlag('data-deadline-closing', false);
                setFlag('data-deadline-hidden', true);
                setFlag('data-deadline-gone', true);
                return;
            }
            if (hide > 0.2) {
                if (hide > box) hide = box;
                setShift(hide);
                setFlag('data-deadline-sliding', true);
                setFlag('data-deadline-hidden', false);
                setFlag('data-deadline-gone', false);
                return;
            }
            finishOpen();
        }

        function measure() {
            var el = clip();
            var banner = el && el.querySelector('.deadline-info');
            quiet = true;
            if (!el || !banner) {
                box = 0;
                hide = 0;
                away = false;
                opening = false;
                setBox(0);
                setShift(0);
                shop.style.removeProperty('--deadline-outer-gap');
                shop.style.removeProperty('--deadline-row-gap');
                setFlag('data-deadline-hidden', false);
                setFlag('data-deadline-gone', false);
                setFlag('data-deadline-closing', false);
                setFlag('data-deadline-opening', false);
                setFlag('data-deadline-sliding', false);
                quiet = false;
                lastY = shop.scrollTop;
                return;
            }
            var raw = el.parentElement ? getComputedStyle(el.parentElement).rowGap : '0';
            var n = parseFloat(raw);
            var rowGap = isFinite(n) ? n : 0;
            /* Am Desktop ist der Zeilenabstand 0, sonst bleibt er bei Höhe 0
               als Sprung stehen. Der sichtbare Abstand liegt dann im Clip. */
            var outer = rowGap;
            gap = rowGap;
            if (rowGap <= 0) {
                var spacing = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--spacing'));
                if (isFinite(spacing) && spacing > 0) gap = spacing;
                outer = 0;
            }
            shop.style.setProperty('--deadline-row-gap', gap.toFixed(2) + 'px');
            shop.style.setProperty('--deadline-outer-gap', outer.toFixed(2) + 'px');
            setFlag('data-deadline-gone', false);
            setFlag('data-deadline-hidden', false);
            setFlag('data-deadline-closing', false);
            setFlag('data-deadline-opening', false);
            setFlag('data-deadline-sliding', false);
            shop.style.removeProperty('--deadline-box');
            shop.style.removeProperty('--deadline-shift');
            var cs = getComputedStyle(banner);
            var mt = parseFloat(cs.marginTop) || 0;
            var mb = parseFloat(cs.marginBottom) || 0;
            box = banner.offsetHeight + mt + mb + gap;
            if (box <= 0) {
                hide = 0;
                away = false;
                setBox(0);
            } else {
                restoreAfterMeasure();
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

            /* Ganz oben langsam aufklappen. Hochscrollen allein reicht nicht. */
            if (y <= 0) {
                beginOpen();
                return;
            }
            if (delta <= 0 || away) return;
            if (box <= 0) measure();
            if (box <= 0 || away) return;
            var next = hide + delta * SPEED;
            if (next > box) next = box;
            if (next !== hide) {
                hide = next;
                setFlag('data-deadline-sliding', true);
                setShift(hide);
            }
            if (hide >= box - 0.4) beginClose();
        }

        shop.addEventListener('scroll', onScroll, { passive: true });
        shop.addEventListener('transitionend', function (e) {
            var el = clip();
            if (!el || (e.target !== el && !el.contains(e.target))) return;
            if (e.propertyName !== 'height' && e.propertyName !== 'opacity' && e.propertyName !== 'transform') return;
            if (away) finishClose();
            else if (opening) finishOpen();
        });
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
