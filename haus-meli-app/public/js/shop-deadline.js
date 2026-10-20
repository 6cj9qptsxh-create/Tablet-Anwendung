/* Lieferhinweis und Handy-Navigation folgen dem Shop-Scroll 1:1.
   Der Hinweis gleitet nur per Verschiebung und Deckkraft. Der Platz
   darunter wechselt in einem Frame, sonst ruckelt die Produktliste.
   Hochscrollen allein holt ihn nicht. Die Navigation kommt beim
   Hochscrollen zurück und schiebt den Shop-Kopf mit.
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
        var motionToken = 0;
        var phone = window.matchMedia('(max-width: 767px)');
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        var quiet = false;

        function clip() {
            return shop.querySelector('.deadline-clip');
        }

        function setShift(px) {
            var value = px <= 0.2 ? 0 : px;
            shop.style.setProperty('--deadline-shift', value.toFixed(2) + 'px');
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
            setFlag('data-deadline-reveal', false);
            setFlag('data-deadline-sliding', false);
        }

        function finishOpen() {
            opening = false;
            away = false;
            hide = 0;
            setShift(0);
            setFlag('data-deadline-hidden', false);
            setFlag('data-deadline-gone', false);
            setFlag('data-deadline-reveal', false);
            setFlag('data-deadline-opening', false);
            setFlag('data-deadline-closing', false);
            setFlag('data-deadline-sliding', false);
        }

        function beginClose() {
            if (away || box <= 0) return;
            away = true;
            opening = false;
            motionToken++;
            hide = box;
            setShift(box);
            setFlag('data-deadline-sliding', false);
            setFlag('data-deadline-opening', false);
            setFlag('data-deadline-reveal', false);
            setFlag('data-deadline-closing', false);
            setFlag('data-deadline-hidden', true);
            /* Zeile ist schon aus dem Bild. Platz in diesem Frame weg. */
            finishClose();
        }

        function beginOpen() {
            if (opening) return;
            var parked = away || shop.hasAttribute('data-deadline-hidden') || shop.hasAttribute('data-deadline-gone');
            if (!parked && hide <= 0.2) {
                hide = 0;
                setShift(0);
                return;
            }
            opening = true;
            away = false;
            var token = ++motionToken;
            setFlag('data-deadline-closing', false);
            setFlag('data-deadline-sliding', false);
            setFlag('data-deadline-opening', true);
            if (!parked) {
                /* Platz ist noch da. Nur die Zeile gleitet zurück. */
                setFlag('data-deadline-reveal', true);
                if (reduced.matches) {
                    finishOpen();
                    return;
                }
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        if (token !== motionToken || !opening) return;
                        hide = 0;
                        setShift(0);
                    });
                });
                setTimeout(function () {
                    if (token === motionToken && opening) finishOpen();
                }, 420);
                return;
            }
            /* Platz sofort auf. Die Zeile startet darüber und gleitet herein. */
            setFlag('data-deadline-gone', false);
            setFlag('data-deadline-hidden', false);
            setFlag('data-deadline-reveal', false);
            hide = box;
            setShift(box);
            if (reduced.matches) {
                finishOpen();
                return;
            }
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    if (token !== motionToken || !opening) return;
                    hide = 0;
                    setShift(0);
                    setFlag('data-deadline-reveal', true);
                });
            });
            setTimeout(function () {
                if (token === motionToken && opening) finishOpen();
            }, 420);
        }

        function restoreAfterMeasure() {
            setBox(box);
            if (away) {
                hide = box;
                setShift(box);
                setFlag('data-deadline-sliding', false);
                setFlag('data-deadline-opening', false);
                setFlag('data-deadline-reveal', false);
                setFlag('data-deadline-closing', false);
                setFlag('data-deadline-hidden', true);
                setFlag('data-deadline-gone', true);
                return;
            }
            if (hide > 0.2) {
                if (hide > box) hide = box;
                setShift(hide);
                setFlag('data-deadline-sliding', true);
                setFlag('data-deadline-reveal', false);
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
            opening = false;
            motionToken++;
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
                setFlag('data-deadline-reveal', false);
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
            setFlag('data-deadline-reveal', false);
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

            /* Ganz oben gleitet die Zeile herein. Hochscrollen allein reicht nicht. */
            if (y <= 0) {
                beginOpen();
                return;
            }
            if (opening && delta > 0) {
                opening = false;
                motionToken++;
                setFlag('data-deadline-opening', false);
                setFlag('data-deadline-reveal', false);
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
            if (!el || !el.contains(e.target)) return;
            if (e.propertyName !== 'opacity' && e.propertyName !== 'transform') return;
            if (opening) finishOpen();
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
