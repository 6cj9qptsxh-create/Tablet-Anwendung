<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta name="theme-color" content="#23272c">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Haus Meli">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Haus Meli</title>
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}">
    
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v=85">
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v=88">
    <link rel="stylesheet" href="{{ asset('css/leaflet-fix.css') }}?v=10">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@48,400,1,0&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@48,400,1,0&display=swap" /></noscript>

    <style> [x-cloak] { display: none !important; } </style>
    <script>
        // In der iPhone-Home-Bildschirm-App ist 100dvh um die Statusleiste zu kurz,
        // obwohl die App darunter zeichnet. Die Seitenhöhe kommt deshalb aus JS:
        // dort die volle Bildschirmhöhe, sonst die gemessene Fensterhöhe.
        (function () {
            const root = document.documentElement;
            const standalone = function () {
                if (navigator.standalone === true) return true;
                try {
                    return window.matchMedia('(display-mode: standalone)').matches
                        || window.matchMedia('(display-mode: fullscreen)').matches;
                } catch (e) {
                    return false;
                }
            };
            const apply = function () {
                let h = window.innerHeight;
                if (standalone() && window.screen) {
                    const portrait = window.innerHeight >= window.innerWidth;
                    const full = portrait
                        ? Math.max(screen.width, screen.height)
                        : Math.min(screen.width, screen.height);
                    if (full >= h && full - h <= 100) h = full;
                }
                if (h > 0) root.style.setProperty('--app-h', h + 'px');
            };
            apply();
            window.addEventListener('resize', apply);
            window.addEventListener('orientationchange', function () { setTimeout(apply, 80); });
        })();
        // Alpine-Ausdrücke dürfen kein try/catch — Helfer hier (echtes JS).
        window.HAUS_MELI_BUILD = {
            id: '2026-09-26-boot-fix',
            path: @json(base_path()),
            toursMap: 68,
            toursPlanner: 31,
            toursJs: 73,
            alpineFix: true,
        };
        console.info('[Haus Meli Build]', window.HAUS_MELI_BUILD);
        window.hausMeliStoreTab = function (tab) {
            try { localStorage.setItem('hausMeliTab', tab); } catch (e) {}
        };
        window.hausMeliReadTab = function (allowed) {
            try {
                const saved = localStorage.getItem('hausMeliTab');
                if (allowed && allowed.includes(saved)) return saved;
            } catch (e) {}
            return null;
        };
        window.loadToursBundle = function () {
            if (window._toursBundle) return;
            window._toursBundle = true;
            console.info('[Haus Meli] loadToursBundle → map v' + window.HAUS_MELI_BUILD.toursMap
                + ' / planner v' + window.HAUS_MELI_BUILD.toursPlanner
                + ' / tours v' + window.HAUS_MELI_BUILD.toursJs);
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = @json(asset('vendor/leaflet/leaflet.css'));
            document.head.appendChild(css);
            const s1 = document.createElement('script');
            s1.src = @json(asset('vendor/leaflet/leaflet.js'));
            s1.onload = function () {
                const a = document.createElement('script'); a.src = @json(asset('js/tours-map.js')) + '?v=' + window.HAUS_MELI_BUILD.toursMap;
                a.onload = function () {
                    const b = document.createElement('script'); b.src = @json(asset('js/tours-planner.js')) + '?v=' + window.HAUS_MELI_BUILD.toursPlanner;
                    b.onload = function () {
                        const c = document.createElement('script'); c.src = @json(asset('js/tours.js')) + '?v=' + window.HAUS_MELI_BUILD.toursJs;
                        c.onload = function () {
                            console.info('[Haus Meli] Tours-Bundle geladen. L=', typeof window.L, 'updateToursOverviewMap=', typeof window.updateToursOverviewMap);
                            if (window.bootToursGuestApp) window.bootToursGuestApp();
                            setTimeout(function () {
                                window.bootToursGuestApp && window.bootToursGuestApp();
                                window.invalidateToursOverviewMap && window.invalidateToursOverviewMap();
                            }, 80);
                        };
                        document.body.appendChild(c);
                    };
                    document.body.appendChild(b);
                };
                document.body.appendChild(a);
            };
            document.body.appendChild(s1);
        };
    </script>
    @livewireStyles
</head>

<body class="page-content"
      x-data="{
        tabs: ['order', 'knx', 'events', 'info', 'tours'],
        currentTab: (function () {
          const allowed = ['order', 'knx', 'events', 'info', 'tours'];
          const fromHash = (location.hash || '').replace(/^#/, '');
          if (allowed.includes(fromHash)) return fromHash;
          const saved = window.hausMeliReadTab(allowed);
          if (saved) return saved;
          return 'order';
        })(),
        menuOpen: false,
        chromeTab: 'order',
        tabName(tab) {
          return ({ order: 'Shop', knx: 'Wohnung', events: 'Events', info: 'Infos', tours: 'Touren' })[tab] || '';
        },
        neighbor(step) {
          const count = this.tabs.length;
          return this.tabs[(this.tabs.indexOf(this.currentTab) + step + count) % count] || null;
        },
        goNeighbor(step) {
          const tab = this.neighbor(step);
          if (!tab) return;
          if (Math.abs(this.tabs.indexOf(tab) - this.tabs.indexOf(this.currentTab)) > 1) this.wrapTo(tab, step);
          else this.setTab(tab);
        },
        animateNav(next, prev) {
          const nav = document.querySelector('.shell-nav');
          if (!nav || !prev || next === prev) return;
          const last = this.tabs.length - 1;
          const from = this.tabs.indexOf(prev);
          const to = this.tabs.indexOf(next);
          const wrapped = Math.abs(to - from) > 1 && ((from === 0 && to === last) || (from === last && to === 0));
          const forward = wrapped ? from === last : to > from;
          nav.dataset.dir = forward ? 'next' : 'prev';
          nav.classList.remove('is-animating');
          void nav.offsetWidth;
          nav.classList.add('is-animating');
          clearTimeout(this._navTimer);
          this._navTimer = setTimeout(() => nav.classList.remove('is-animating'), 560);
        },
        wrapTo(tab, step) {
          const track = this.$refs.track;
          if (!track || this._wrapping || !track.animate) { this.setTab(tab); return; }
          this._wrapping = true;
          const away = step > 0 ? -22 : 22;
          const out = track.animate(
            [{ transform: 'translateX(0)', opacity: 1 }, { transform: 'translateX(' + away + '%)', opacity: 0 }],
            { duration: 140, easing: 'ease-in', fill: 'forwards' }
          );
          out.onfinish = () => {
            this.setTab(tab, true);
            this.scrollToTab(tab, false);
            out.cancel();
            const back = track.animate(
              [{ transform: 'translateX(' + (-away) + '%)', opacity: 0 }, { transform: 'translateX(0)', opacity: 1 }],
              { duration: 200, easing: 'ease-out' }
            );
            back.onfinish = () => { this._wrapping = false; this.finishChrome(); };
          };
        },
        wireWrapSwipe(track) {
          let startX = 0, startY = 0, armed = false;
          const blocked = (start) => {
            for (let node = start; node && node !== track; node = node.parentElement) {
              if (node.classList && node.classList.contains('leaflet-container')) return true;
              if (/^(INPUT|TEXTAREA|SELECT)$/.test(node.tagName)) return true;
              if (node.scrollWidth > node.clientWidth + 2) {
                const overflow = getComputedStyle(node).overflowX;
                if (overflow === 'auto' || overflow === 'scroll') return true;
              }
            }
            return false;
          };
          const lastIndex = this.tabs.length - 1;
          track.addEventListener('touchstart', (event) => {
            const index = this.tabs.indexOf(this.currentTab);
            armed = event.touches.length === 1 && (index === 0 || index === lastIndex) && !blocked(event.target);
            if (!armed) return;
            startX = event.touches[0].clientX;
            startY = event.touches[0].clientY;
          }, { passive: true });
          track.addEventListener('touchend', (event) => {
            if (!armed) return;
            armed = false;
            const touch = event.changedTouches[0];
            const dx = touch.clientX - startX;
            const dy = touch.clientY - startY;
            if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 1.6) return;
            const index = this.tabs.indexOf(this.currentTab);
            if (dx < 0 && index === lastIndex) this.wrapTo(this.tabs[0], 1);
            else if (dx > 0 && index === 0) this.wrapTo(this.tabs[lastIndex], -1);
          }, { passive: true });
        },
        syncPaneBox() {
          const el = this.$refs.pager;
          if (!el) return;
          document.documentElement.style.setProperty('--tab-pane-h', el.clientHeight + 'px');
        },
        scrollToTab(tab, smooth) {
          const track = this.$refs.track;
          const index = this.tabs.indexOf(tab);
          if (!track || index < 0) return;
          const left = index * track.clientWidth;
          if (Math.abs(track.scrollLeft - left) < 2) return;
          this._prog = true;
          track.scrollTo({ left: left, behavior: smooth ? 'smooth' : 'auto' });
          clearTimeout(this._progTimer);
          this._progTimer = setTimeout(() => { this._prog = false; }, smooth ? 520 : 80);
        },
        tabFromScroll() {
          const track = this.$refs.track;
          if (!track) return null;
          const width = track.clientWidth || 1;
          const index = Math.max(0, Math.min(this.tabs.length - 1, Math.round(track.scrollLeft / width)));
          return this.tabs[index] || null;
        },
        finishChrome() {
          this.chromeTab = this.currentTab;
          if (this.currentTab !== 'events') return;
          requestAnimationFrame(() => window.dispatchEvent(new CustomEvent('cal-remeasure')));
          setTimeout(() => window.dispatchEvent(new CustomEvent('cal-remeasure')), 80);
        },
        onPagerScroll() {
          if (this._prog) return;
          const tab = this.tabFromScroll();
          if (tab && tab !== this.currentTab) this.setTab(tab, true);
          clearTimeout(this._scrollEnd);
          this._scrollEnd = setTimeout(() => this.finishChrome(), 140);
        },
        setTab(tab, fromScroll) {
          if (!this.tabs.includes(tab)) return;
          const changed = tab !== this.currentTab;
          this.currentTab = tab;
          this.menuOpen = false;
          window.hausMeliStoreTab(tab);
          if (location.hash !== '#' + tab) location.hash = tab;
          if (!fromScroll) this.scrollToTab(tab, changed);
          if (!changed) this.chromeTab = tab;
        }
      }"
      :class="{ 'is-cal-tab': chromeTab === 'events' }"
      x-on:set-app-tab.window="setTab(($event.detail && $event.detail.tab) ? $event.detail.tab : $event.detail)"
      x-init="
        chromeTab = currentTab;
        $nextTick(() => requestAnimationFrame(() => {
          syncPaneBox();
          scrollToTab(currentTab, false);
          const pagerEl = $refs.pager;
          const trackEl = $refs.track;
          if (pagerEl && window.ResizeObserver) {
            new ResizeObserver(() => {
              syncPaneBox();
              scrollToTab(currentTab, false);
            }).observe(pagerEl);
          }
          if (trackEl) trackEl.addEventListener('scroll', () => onPagerScroll(), { passive: true });
          if (trackEl) wireWrapSwipe(trackEl);
        }));
        window.hausMeliStoreTab(currentTab);
        if (location.hash !== '#' + currentTab) location.hash = currentTab;
        if (currentTab === 'events') {
          requestAnimationFrame(() => window.dispatchEvent(new CustomEvent('cal-remeasure')));
          setTimeout(() => window.dispatchEvent(new CustomEvent('cal-remeasure')), 80);
        }
        if (currentTab === 'tours') {
          window.loadToursBundle && window.loadToursBundle();
        }
        $watch('currentTab', (v, prev) => {
          animateNav(v, prev);
          window.hausMeliStoreTab(v);
          if (location.hash !== '#' + v) location.hash = v;
          if (v === 'events') {
            requestAnimationFrame(() => window.dispatchEvent(new CustomEvent('cal-remeasure')));
            setTimeout(() => window.dispatchEvent(new CustomEvent('cal-remeasure')), 80);
            setTimeout(() => window.dispatchEvent(new CustomEvent('cal-remeasure')), 600);
          }
          if (v === 'tours') {
            window.loadToursBundle && window.loadToursBundle();
            setTimeout(() => window.bootToursGuestApp && window.bootToursGuestApp(), 50);
            setTimeout(() => window.invalidateToursOverviewMap && window.invalidateToursOverviewMap(), 80);
            setTimeout(() => window.invalidateToursOverviewMap && window.invalidateToursOverviewMap(), 250);
          }
        });
        window.addEventListener('hashchange', () => {
          const h = (location.hash || '').replace(/^#/, '');
          if (tabs.includes(h) && h !== currentTab) setTab(h);
        });
      "
      x-cloak>

    @include('livewire.general-partials.header')

	@include('livewire.general-partials.navigation')

    <div class="tab-pager" x-ref="pager">
        <div class="tab-track" x-ref="track">
            <section class="tab-pane" data-tab="order">
                @livewire('shop')
            </section>

            <section class="tab-pane" data-tab="knx"></section>

            <section class="tab-pane tab-pane-events cal-tab-pane" data-tab="events">
                @livewire('events')
            </section>

            <section class="tab-pane" data-tab="info">
                @livewire('info', ['defer' => true])
            </section>

            <section class="tab-pane"
                     data-tab="tours"
                     x-effect="if (currentTab === 'tours') { window.loadToursBundle && window.loadToursBundle(); $nextTick(() => { setTimeout(() => { window.bootToursGuestApp && window.bootToursGuestApp(); window.invalidateToursOverviewMap && window.invalidateToursOverviewMap(); }, 30); }); }">
                @livewire('tours', ['defer' => true])
            </section>
        </div>
    </div>

    @include('livewire.general-modals.lightbox')
    @include('livewire.general-modals.video')
    @include('livewire.general-modals.welcome')
    @include('livewire.general-modals.checkout')

    <div id="toast-container"></div>

    @livewireScripts

    <script>
        // Verhindert den nervigen „This page has expired“-Spam (Shop-Poll → 419).
        document.addEventListener('livewire:init', () => {
            let reloading = false;
            Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status !== 419) return;
                    preventDefault();
                    if (reloading) return;
                    reloading = true;
                    window.location.reload();
                });
            });
        });
    </script>

    <script>
        window.APP = Object.assign(window.APP || {}, {
            // Sprache kommt vom Gerät (Browser/Handy), Laravel-Locale nur als Fallback
            lang: (function (fallback) {
                var l = (navigator.languages && navigator.languages[0]) || navigator.language || fallback || 'de';
                return String(l).toLowerCase().indexOf('de') === 0 ? 'de' : 'en';
            })(@json(str_replace('_', '-', app()->getLocale()))),
            // Fallback wenn Bilder nur auf dem Admin-Server liegen (lokal Port 8000)
            toursMediaUrl: @json(rtrim((string) env('TOURS_MEDIA_URL', ''), '/')),
            plannedShareUrl: '/tours/planned/share',
            // Optional: http://192.168.x.x:8000 — damit QR vom Handy im WLAN erreichbar ist
            sharePublicOrigin: @json(rtrim((string) env('SHARE_PUBLIC_ORIGIN', ''), '/')),
            csrfToken: @json(csrf_token()),
        });
    </script>
    <script>document.documentElement.lang = window.APP.lang;</script>
    <script src="{{ asset('js/weather.js') }}?v=6"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var track = document.querySelector('.tab-track');
            var shop = document.querySelector('.tab-pane[data-tab="order"]');
            var nav = document.querySelector('.shell-nav');
            var phone = window.matchMedia('(max-width: 767px)');
            if (!track || !shop || !nav) return;
            function update() {
                var root = document.body.style;
                if (!phone.matches) {
                    root.removeProperty('--nav-shift');
                    return;
                }
                var navH = nav.offsetHeight;
                var shift = Math.min(Math.max(shop.scrollTop, 0), navH);
                var visible = 1 - Math.min(1, track.scrollLeft / Math.max(track.clientWidth, 1));
                root.setProperty('--nav-shift', (shift * visible) + 'px');
            }
            shop.addEventListener('scroll', update, { passive: true });
            track.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update);
            update();
        });
    </script>
    <script>document.addEventListener('touchstart', function () {}, { passive: true });</script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            function updateClock() {
                const now = new Date();
                const clockEl = document.getElementById('header-clock');
                if (clockEl) {
                    clockEl.textContent = now.toLocaleTimeString('de-DE', {
                        hour: '2-digit', minute: '2-digit', second: '2-digit'
                    });
                }
            }

            // 1. Einmal sofort ausführen, damit nicht 1 Sekunde lang "00:00:00" dort steht
            updateClock();

            // 2. Den Motor starten: Führt die Funktion ab sofort alle 1000 Millisekunden aus
            setInterval(updateClock, 1000);
            
        });
    </script>
</body>
</html>
