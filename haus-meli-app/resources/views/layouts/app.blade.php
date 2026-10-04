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
    
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v=104">
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v=91">
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
            toursMap: 72,
            toursPlanner: 31,
            toursJs: 80,
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
        window._toursWanted = false;
        window._toursGen = 0;
        window.cancelToursLoad = function () {
            window._toursWanted = false;
            window._toursGen += 1;
            window._toursBundle = false;
            window._toursBooting = false;
            window._toursBootQueued = false;
            window._toursBootToken = (window._toursBootToken || 0) + 1;
            window._toursPendingGen = null;
            window._toursSliceBudget = 0;
            window._toursBootFiltered = null;
            window._toursChunkMap = false;
            window._toursGraphLoading = false;
            clearTimeout(window._toursBootTimer);
            clearTimeout(window._toursBootSoon);
            clearTimeout(window._toursMapTimer);
            clearTimeout(window._toursMapDrawTimer);
            window._toursMapDrawTimer = null;
            if (window._toursObs) {
                try { window._toursObs.disconnect(); } catch (e) {}
            }
            window.syncToursLoadButton && window.syncToursLoadButton();
        };
        window.toursIsPhone = function () {
            return window.matchMedia('(max-width: 767px)').matches;
        };
        window.syncToursLoadButton = function () {
            const phone = window.toursIsPhone();
            const ready = !!window._toursGuestBooted;
            const busy = !!window._toursWanted && !ready;
            document.body.classList.toggle('tours-map-busy', phone && busy);
            document.body.classList.toggle('tours-map-ready', ready);
            const loading = document.getElementById('tours-map-loading');
            if (loading && !ready) loading.hidden = !busy;
        };
        window.maybeLoadTours = function () {
            if (window.toursIsPhone()) {
                window.syncToursLoadButton();
                return;
            }
            window._toursWanted = true;
            window.syncToursLoadButton();
            window.loadToursBundle && window.loadToursBundle();
        };
        window.startToursMapLoad = function () {
            if (window._toursGuestBooted) {
                window.syncToursLoadButton();
                return;
            }
            window._toursWanted = true;
            window.syncToursLoadButton();
            window.loadToursBundle && window.loadToursBundle();
        };
        document.addEventListener('click', function (event) {
            const btn = event.target && event.target.closest && event.target.closest('#tours-map-load');
            if (!btn) return;
            event.preventDefault();
            window.startToursMapLoad();
        });
        window.loadToursBundle = function () {
            window._toursWanted = true;
            if (window._toursReady) {
                if (window.scheduleToursBoot) window.scheduleToursBoot();
                else if (window.bootToursGuestApp) window.bootToursGuestApp();
                return;
            }
            if (window._toursBundle) return;
            window._toursBundle = true;
            const gen = window._toursGen;
            console.info('[Haus Meli] loadToursBundle → map v' + window.HAUS_MELI_BUILD.toursMap
                + ' / planner v' + window.HAUS_MELI_BUILD.toursPlanner
                + ' / tours v' + window.HAUS_MELI_BUILD.toursJs);
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = @json(asset('vendor/leaflet/leaflet.css'));
            document.head.appendChild(css);
            const chain = [
                @json(asset('vendor/leaflet/leaflet.js')),
                @json(asset('js/tours-map.js')) + '?v=' + window.HAUS_MELI_BUILD.toursMap,
                @json(asset('js/tours-planner.js')) + '?v=' + window.HAUS_MELI_BUILD.toursPlanner,
                @json(asset('js/tours.js')) + '?v=' + window.HAUS_MELI_BUILD.toursJs,
            ];
            let step = 0;
            const next = function () {
                if (!window._toursWanted || gen !== window._toursGen) return;
                if (step >= chain.length) {
                    window._toursReady = true;
                    window._toursBundle = false;
                    if (window.scheduleToursBoot) window.scheduleToursBoot();
                    else if (window.bootToursGuestApp) window.bootToursGuestApp();
                    clearTimeout(window._toursBootTimer);
                    window._toursBootTimer = setTimeout(function () {
                        if (!window._toursWanted) return;
                        window.invalidateToursOverviewMap && window.invalidateToursOverviewMap();
                    }, 80);
                    return;
                }
                const src = chain[step++];
                const existing = document.querySelector('script[data-tour-src="' + src + '"]');
                if (existing && existing.dataset.loaded === '1') { setTimeout(next, 0); return; }
                const tag = existing || document.createElement('script');
                const go = function () {
                    tag.dataset.loaded = '1';
                    next();
                };
                if (!existing) {
                    tag.src = src;
                    tag.dataset.tourSrc = src;
                    tag.onload = go;
                    document.body.appendChild(tag);
                } else {
                    tag.addEventListener('load', go, { once: true });
                }
            };
            next();
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
        wheelIndex: (function () {
          const allowed = ['order', 'knx', 'events', 'info', 'tours'];
          const fromHash = (location.hash || '').replace(/^#/, '');
          const name = allowed.includes(fromHash) ? fromHash : (window.hausMeliReadTab(allowed) || 'order');
          const idx = allowed.indexOf(name);
          return idx < 0 ? 0 : idx;
        })(),
        wheelOn: false,
        wheelDrag: false,
        wheelStretch: 0,
        wheelW: 0,
        tabName(tab) {
          return ({ order: 'Shop', knx: 'Wohnung', events: 'Events', info: 'Infos', tours: 'Touren' })[tab] || '';
        },
        neighbor(step) {
          const index = this.tabs.indexOf(this.currentTab) + step;
          if (index < 0 || index >= this.tabs.length) return null;
          return this.tabs[index];
        },
        goNeighbor(step) {
          const tab = this.neighbor(step);
          if (tab) this.setTab(tab);
        },
        // Alle Namen stehen immer in der Leiste. Unter der Pille volle Groesse, daneben kleiner.
        wheelInset(width) {
          return Math.min(56, Math.max(40, width * 0.12));
        },
        wheelBar() {
          const nav = document.querySelector('.shell-nav');
          const measured = nav && nav.clientWidth ? nav.clientWidth : 0;
          const width = this.wheelW || measured || 320;
          const max = Math.max(1, this.tabs.length - 1);
          return { width: width, max: max, inset: this.wheelInset(width) };
        },
        wheelX(index) {
          const bar = this.wheelBar();
          const clamped = Math.max(0, Math.min(bar.max, index));
          const span = Math.max(1, bar.width - bar.inset * 2);
          return bar.inset + (clamped / bar.max) * span;
        },
        wheelIndexFromX(clientX) {
          const nav = document.querySelector('.shell-nav');
          const rect = nav ? nav.getBoundingClientRect() : null;
          const width = rect && rect.width ? rect.width : (this.wheelW || 320);
          const max = Math.max(1, this.tabs.length - 1);
          const inset = this.wheelInset(width);
          const span = Math.max(1, width - inset * 2);
          const local = rect ? clientX - rect.left : 0;
          let index = (local - inset) / span * max;
          if (index < 0) index = index * 0.35;
          else if (index > max) index = max + (index - max) * 0.35;
          return index;
        },
        lensFor(index, bar) {
          const max = this.tabs.length - 1;
          const i = Math.max(0, Math.min(max, index));
          const label = this.tabName(this.tabs[i] || this.currentTab);
          const text = 24 + label.length * 13;
          return Math.max(64, Math.min(text, bar.width * 0.42));
        },
        wheelStyle() {
          const bar = this.wheelBar();
          const max = this.tabs.length - 1;
          const at = Math.max(0, Math.min(max, this.wheelIndex));
          const x = this.wheelX(at);
          const left = x / bar.width * 100;
          const lo = Math.floor(at);
          const hi = Math.min(max, lo + 1);
          const span = hi === lo ? 0 : (at - lo);
          let lens = this.lensFor(lo, bar) + (this.lensFor(hi, bar) - this.lensFor(lo, bar)) * span;
          const edge = 4;
          const room = Math.max(48, Math.min(x - edge, bar.width - edge - x) * 2);
          if (lens > room) lens = room;
          const frac = this.wheelIndex - Math.round(this.wheelIndex);
          const shine = Math.max(-1, Math.min(1, frac * 2));
          return '--lens-x:' + left.toFixed(2) + '%;--lens:' + lens.toFixed(1) + 'px;--shine:' + shine.toFixed(3) + ';--stretch:' + Number(this.wheelStretch).toFixed(3) + ';--press:' + (this.wheelDrag ? 1 : 0) + ';';
        },
        wheelItemStyle(i) {
          const bar = this.wheelBar();
          const left = this.wheelX(i) / bar.width * 100;
          const d = Math.abs(i - this.wheelIndex);
          const zoom = Math.exp(-d * d * 2.2);
          const scale = 0.5 + 0.5 * zoom;
          const opacity = 0.72 + 0.28 * zoom;
          const z = Math.round(2 + zoom * 8);
          return 'left:' + left.toFixed(2) + '%;opacity:' + opacity.toFixed(3) + ';z-index:' + z + ';transform:translate(-50%, -50%) scale(' + scale.toFixed(3) + ')';
        },
        wheelSettle(index, dur, soft) {
          cancelAnimationFrame(this._wheelRaf);
          const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
          if (reduce || dur === 0 || Math.abs(index - this.wheelIndex) < 0.001) {
            this.wheelIndex = index;
            return;
          }
          const from = this.wheelIndex;
          const ms = dur || 360;
          const start = performance.now();
          const step = (now) => {
            if (this._wheel) return;
            const t = Math.min(1, (now - start) / ms);
            const c = 1.35;
            const e = soft ? (1 - Math.pow(1 - t, 3)) : (1 + (c + 1) * Math.pow(t - 1, 3) + c * Math.pow(t - 1, 2));
            this.wheelIndex = from + (index - from) * e;
            if (t < 1) this._wheelRaf = requestAnimationFrame(step);
            else this.wheelIndex = index;
          };
          this._wheelRaf = requestAnimationFrame(step);
        },
        wheelDown(event) {
          if (event.pointerType === 'mouse' && event.button !== 0) return;
          cancelAnimationFrame(this._wheelRaf);
          clearTimeout(this._wheelOff);
          this._wheel = {
            id: event.pointerId,
            x: event.clientX,
            y: event.clientY,
            start: this.wheelIndex,
            moved: false,
            ignore: false
          };
          this.wheelStretch = 0;
          try { event.currentTarget.setPointerCapture(event.pointerId); } catch (err) {}
        },
        wheelClick(event) {
          if (!this._swallowClick) return;
          this._swallowClick = 0;
          event.preventDefault();
          event.stopPropagation();
        },
        wheelMove(event) {
          const g = this._wheel;
          if (!g || event.pointerId !== g.id) return;
          const dx = event.clientX - g.x;
          const dy = event.clientY - g.y;
          if (!g.moved) {
            if (Math.abs(dx) < 8 && Math.abs(dy) < 8) return;
            g.moved = true;
            if (Math.abs(dy) > Math.abs(dx)) {
              g.ignore = true;
              return;
            }
            this.wheelDrag = true;
            this.wheelOn = true;
          }
          if (g.ignore) return;
          if (event.cancelable) event.preventDefault();
          const index = this.wheelIndexFromX(event.clientX);
          const max = this.tabs.length - 1;
          this.wheelIndex = index;
          const over = index < 0 ? -index : (index > max ? index - max : 0);
          this.wheelStretch = Math.min(1, over);
        },
        wheelUp(event) {
          const g = this._wheel;
          if (!g || event.pointerId !== g.id) return;
          this._wheel = null;
          this.wheelDrag = false;
          this.wheelStretch = 0;
          this._swallowN = (this._swallowN || 0) + 1;
          this._swallowClick = this._swallowN;
          const mark = this._swallowN;
          const selfClick = this;
          setTimeout(() => {
            if (selfClick._swallowN === mark) selfClick._swallowClick = 0;
          }, 400);
          const max = this.tabs.length - 1;
          if (g.ignore || !g.moved) {
            clearTimeout(this._wheelOff);
            this.wheelOn = false;
            if (g.ignore) {
              const back = this.tabs.indexOf(this.currentTab);
              if (back >= 0) this.wheelIndex = back;
              return;
            }
            let index = Math.round(this.wheelIndexFromX(g.x));
            if (index < 0) index = 0;
            if (index > max) index = max;
            this._wheelHold = true;
            this.wheelIndex = index;
            const picked = this.tabs[index];
            if (picked && picked !== this.currentTab) this.setTab(picked);
            this._wheelHold = false;
            return;
          }
          let index = Math.round(this.wheelIndex);
          if (index < 0) index = 0;
          if (index > max) index = max;
          this._wheelHold = true;
          this.wheelSettle(index, 520, false);
          const tab = this.tabs[index];
          if (tab && tab !== this.currentTab) this.setTab(tab);
          this._wheelHold = false;
          clearTimeout(this._wheelOff);
          const self = this;
          this._wheelOff = setTimeout(() => {
            if (!self._wheel) self.wheelOn = false;
          }, 640);
        },
        animateNav(next, prev) {
          const nav = document.querySelector('.shell-nav');
          if (!nav || !prev || next === prev) return;
          if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
          const play = (el, frames, duration, easing) => {
            if (!el || !el.animate) return;
            el.getAnimations().forEach((anim) => anim.cancel());
            el.animate(frames, { duration: duration, easing: easing });
          };
          nav.querySelectorAll('.shell-nav-label').forEach((el) => {
            play(el, [
              { opacity: 0.2 },
              { opacity: 1 }
            ], 200, 'ease-out');
          });
        },
        trackPane(name) {
          const track = this.$refs.track;
          if (!track) return null;
          return track.querySelector(':scope > [data-tab=' + name + ']');
        },
        visiblePane() {
          const track = this.$refs.track;
          if (!track) return null;
          const viewLeft = track.getBoundingClientRect().left;
          let best = null;
          let bestAbs = Infinity;
          track.querySelectorAll(':scope > .tab-pane').forEach((pane) => {
            const dist = Math.abs(pane.getBoundingClientRect().left - viewLeft);
            if (dist < bestAbs) {
              best = pane;
              bestAbs = dist;
            }
          });
          return best;
        },
        alignPane(pane) {
          const track = this.$refs.track;
          if (!track || !pane) return;
          const delta = pane.getBoundingClientRect().left - track.getBoundingClientRect().left;
          if (Math.abs(delta) > 0.5) track.scrollLeft += delta;
        },
        syncPaneBox() {
          const el = this.$refs.pager;
          if (!el) return;
          document.documentElement.style.setProperty('--tab-pane-h', el.clientHeight + 'px');
        },
        scrollToTab(tab, smooth) {
          const track = this.$refs.track;
          const pane = this.trackPane(tab);
          if (!track || !pane) return;
          const delta = pane.getBoundingClientRect().left - track.getBoundingClientRect().left;
          clearTimeout(this._scrollEnd);
          if (Math.abs(delta) < 2) return;
          this._prog = true;
          if (smooth) {
            track.scrollTo({ left: track.scrollLeft + delta, behavior: 'smooth' });
          } else {
            track.scrollLeft += delta;
            requestAnimationFrame(() => {
              const again = pane.getBoundingClientRect().left - track.getBoundingClientRect().left;
              if (Math.abs(again) > 1) track.scrollLeft += again;
            });
          }
          clearTimeout(this._progTimer);
          this._progTimer = setTimeout(() => {
            this._prog = false;
            if (!this._touching) this.scheduleHash();
          }, smooth ? 520 : 80);
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
        correctSnap() {
          if (this._touching || this._prog || this._wrapping) return;
          const track = this.$refs.track;
          const pane = this.visiblePane();
          if (!track || !pane) return;
          const delta = pane.getBoundingClientRect().left - track.getBoundingClientRect().left;
          if (Math.abs(delta) < 2) return;
          this._prog = true;
          track.scrollLeft += delta;
          clearTimeout(this._progTimer);
          this._progTimer = setTimeout(() => { this._prog = false; }, 80);
        },
        onPagerScroll() {
          if (this._prog) return;
          this._scrolling = true;
          const tab = this.tabFromScroll();
          if (tab && tab !== this.currentTab) this.setTab(tab, true);
          clearTimeout(this._scrollEnd);
          this._scrollEnd = setTimeout(() => {
            this._scrolling = false;
            if (this._fingerSnap) return;
            this.finishChrome();
            this.correctSnap();
            this.syncPill();
            if (!this._touching) this.scheduleHash();
            if (this.currentTab === 'tours' && !this._touching) {
              window.maybeLoadTours && window.maybeLoadTours();
            }
          }, 160);
        },
        syncHash() {
          if (this._touching || this._scrolling) return;
          const tab = this.currentTab;
          if (!tab || location.hash === '#' + tab) return;
          history.replaceState({ hausMeli: 1 }, '', '#' + tab);
        },
        scheduleHash() {
          clearTimeout(this._edgeTimer);
          this._edgeTimer = setTimeout(() => {
            if (this._touching || this._scrolling) return;
            this.syncHash();
          }, 480);
        },
        setTab(tab, fromScroll) {
          if (!this.tabs.includes(tab)) return;
          const changed = tab !== this.currentTab;
          const from = this.tabs.indexOf(this.currentTab);
          const to = this.tabs.indexOf(tab);
          const far = from >= 0 && Math.abs(to - from) > 1;
          if (fromScroll && !this._wheel && !this._wheelHold && to >= 0) {
            cancelAnimationFrame(this._wheelRaf);
            this.wheelIndex = to;
          }
          this._fromScroll = !!fromScroll;
          this.currentTab = tab;
          this._fromScroll = false;
          if (!fromScroll) this.chromeTab = tab;
          this.menuOpen = false;
          window.hausMeliStoreTab(tab);
          this.syncHash();
          if (!fromScroll) this.scrollToTab(tab, changed && !far);
        },
        syncPill(hold) {
          if (this._wheel || this.wheelDrag) return;
          const pane = this.visiblePane();
          const name = pane ? pane.getAttribute('data-tab') : null;
          if (name && this.tabs.includes(name)) {
            if (hold && name === hold && this.currentTab !== hold) {
              const kept = this.tabs.indexOf(this.currentTab);
              if (kept >= 0) this.wheelIndex = kept;
            } else {
              if (name !== this.currentTab && !this._prog) this.setTab(name, true);
              const shown = this.tabs.indexOf(name);
              if (shown >= 0) this.wheelIndex = shown;
            }
          }
          this.finishChrome();
        },
        followRetour(dx) {
          const track = this.$refs.track;
          const width = track && track.clientWidth ? track.clientWidth : 390;
          if (dx < Math.max(36, width * 0.12)) return;
          const back = this.neighbor(-1);
          if (!back) return;
          const start = this.tabs[this._snapFrom] || this.currentTab;
          const self = this;
          const look = () => {
            if (self._touching || self._wheel) return;
            self.syncPill(start);
            if (self.currentTab !== start) return;
            self.setTab(back, true);
            self.finishChrome();
          };
          setTimeout(look, 160);
          setTimeout(look, 420);
        },
        watchSnap() {
          clearTimeout(this._snapTimer);
          let last = -1;
          let stable = 0;
          let released = false;
          const check = () => {
            const track = this.$refs.track;
            if (!track) return;
            if (this._touching) {
              released = false;
              stable = 0;
              last = track.scrollLeft;
              this._snapTimer = setTimeout(check, 70);
              return;
            }
            if (!released) {
              released = true;
              stable = 0;
              last = track.scrollLeft;
            }
            const left = track.scrollLeft;
            if (Math.abs(left - last) > 0.5) { stable = 0; last = left; }
            else stable++;
            if (stable < 4) {
              this._snapTimer = setTimeout(check, 70);
              return;
            }
            this._fingerSnap = false;
            this._scrolling = false;
            clearTimeout(this._scrollEnd);
            if (!(this._swipeDx > 36)) this.syncPill();
            if (!this._touching) this.scheduleHash();
            if (this.currentTab === 'tours' && !this._touching) {
              window.maybeLoadTours && window.maybeLoadTours();
            }
          };
          this._snapTimer = setTimeout(check, 70);
        },
        edgeGo(step) {
          const tab = this.neighbor(step);
          const track = this.$refs.track;
          const pane = tab ? this.trackPane(tab) : null;
          if (!tab || !track || !pane) return;
          const delta = pane.getBoundingClientRect().left - track.getBoundingClientRect().left;
          this._prog = true;
          track.style.scrollSnapType = 'none';
          if (Math.abs(delta) > 1) track.scrollLeft += delta;
          track.style.scrollSnapType = '';
          this.setTab(tab, true);
          this.finishChrome();
          const self = this;
          setTimeout(() => { self._prog = false; }, 80);
        }
      }"
      :class="{ 'is-cal-tab': chromeTab === 'events' }"
      x-on:set-app-tab.window="setTab(($event.detail && $event.detail.tab) ? $event.detail.tab : $event.detail)"
      x-init="
        wheelIndex = Math.max(0, tabs.indexOf(currentTab));
        chromeTab = currentTab;
        $nextTick(() => requestAnimationFrame(() => {
          syncPaneBox();
          scrollToTab(currentTab, false);
          const pagerEl = $refs.pager;
          const trackEl = $refs.track;
          if (pagerEl && window.ResizeObserver) {
            new ResizeObserver(() => {
              syncPaneBox();
            }).observe(pagerEl);
          }
          const navEl = document.querySelector('.shell-nav');
          if (navEl) {
            this.wheelW = navEl.clientWidth || 0;
            if (window.ResizeObserver) {
              new ResizeObserver(() => {
                const w = navEl.clientWidth || 0;
                if (w && w !== this.wheelW) this.wheelW = w;
              }).observe(navEl);
            }
          }
          if (trackEl) trackEl.addEventListener('scroll', () => onPagerScroll(), { passive: true });
          if (trackEl) {
            let sx = 0, sy = 0, carting = false;
            trackEl.addEventListener('touchstart', (event) => {
              this._scrolling = false;
              this._prog = false;
              clearTimeout(this._progTimer);
              clearTimeout(this._scrollEnd);
              clearTimeout(this._edgeTimer);
              const t = event.touches[0];
              sx = t ? t.clientX : 0;
              sy = t ? t.clientY : 0;
              carting = !!document.querySelector('#tab-order.cart-is-open');
              this._touching = true;
              this._fingerSnap = true;
              this._swipeDx = 0;
              this._snapFrom = Math.max(0, this.tabs.indexOf(this.currentTab));
              this.watchSnap();
            }, { passive: true });
            trackEl.addEventListener('touchmove', (event) => {
              const t = event.touches[0];
              if (!t) return;
              const dx = t.clientX - sx;
              const dy = t.clientY - sy;
              if (carting && Math.abs(dx) > 12 && Math.abs(dx) > Math.abs(dy)) {
                event.preventDefault();
              }
              if (!carting && dx > 16 && Math.abs(dx) > Math.abs(dy) && !this._wheel) {
                this._swipeDx = dx;
                const width = trackEl.clientWidth || 1;
                const progress = Math.min(1, dx / (width * 0.42));
                this.wheelIndex = Math.max(0, this._snapFrom - progress);
              }
              if (!carting && this.currentTab === 'tours' && Math.abs(dx) > 28 && Math.abs(dx) > Math.abs(dy)) {
                window.cancelToursLoad && window.cancelToursLoad();
              }
            }, { passive: false, capture: true });
            const release = (event) => {
              const fromNav = this._navFrom;
              this._navFrom = null;
              const before = this.currentTab;
              this._touching = false;
              const t = event.changedTouches && event.changedTouches[0];
              const dx = t ? t.clientX - sx : 0;
              const dy = t ? t.clientY - sy : 0;
              const horizontal = Math.abs(dx) > 12 && Math.abs(dx) > Math.abs(dy);
              if (horizontal) this._swipeDx = dx;
              if (!carting && horizontal && dx > 0) this.followRetour(dx);
              if (carting && horizontal && dx > 0) {
                window.dispatchEvent(new CustomEvent('close-cart'));
              }
              if (!carting && before === 'tours' && horizontal) {
                window.cancelToursLoad && window.cancelToursLoad();
              }
              if (this.currentTab === before && fromNav && fromNav !== this.currentTab) {
                this.animateNav(this.currentTab, fromNav);
              }
              if (!this._scrolling) this.scheduleHash();
              carting = false;
              setTimeout(() => {
                if (!this._scrolling && this.currentTab === 'tours' && !this._touching) {
                  window.maybeLoadTours && window.maybeLoadTours();
                }
              }, 200);
            };
            trackEl.addEventListener('touchend', release, { passive: true });
            trackEl.addEventListener('touchcancel', release, { passive: true });
          }
        }));
        window.hausMeliStoreTab(currentTab);
        if (location.hash !== '#' + currentTab) location.hash = currentTab;
        if (currentTab === 'events') {
          requestAnimationFrame(() => window.dispatchEvent(new CustomEvent('cal-remeasure')));
          setTimeout(() => window.dispatchEvent(new CustomEvent('cal-remeasure')), 80);
        }
        if (currentTab === 'tours') {
          window.maybeLoadTours && window.maybeLoadTours();
        }
        $watch('currentTab', (v, prev) => {
          if (!this._wheel && !this._wheelHold && !this._fromScroll) {
            const to = this.tabs.indexOf(v);
            if (to >= 0) this.wheelSettle(to, 340, true);
          }
          if (this._touching) {
            if (!this._navFrom) this._navFrom = prev;
          } else if (!this.wheelOn && !this._wheelHold) {
            animateNav(v, prev);
          }
          window.hausMeliStoreTab(v);
          if (v !== 'tours' && window.cancelToursLoad) window.cancelToursLoad();
        });
        if (!history.state || !history.state.hausMeli) {
          history.pushState({ hausMeli: 1 }, '', location.href);
        }
        window.addEventListener('popstate', () => {
          history.pushState({ hausMeli: 1 }, '', location.pathname + location.search + '#' + currentTab);
        });
        document.querySelectorAll('.edge-swipe-left').forEach((el) => {
          let ex = 0, ey = 0, horizontal = false;
          el.addEventListener('touchstart', (event) => {
            const t = event.touches[0];
            ex = t ? t.clientX : 0;
            ey = t ? t.clientY : 0;
            horizontal = false;
            if (event.cancelable) event.preventDefault();
          }, { passive: false });
          el.addEventListener('touchmove', (event) => {
            const t = event.touches[0];
            if (!t) return;
            const dx = t.clientX - ex;
            const dy = t.clientY - ey;
            if (!horizontal && Math.abs(dx) < 8 && Math.abs(dy) < 8) return;
            if (!horizontal && Math.abs(dy) > Math.abs(dx)) return;
            horizontal = true;
            if (event.cancelable) event.preventDefault();
          }, { passive: false });
          el.addEventListener('touchend', (event) => {
            if (!horizontal) return;
            const t = event.changedTouches && event.changedTouches[0];
            const dx = t ? t.clientX - ex : 0;
            if (Math.abs(dx) < 24) return;
            edgeGo(dx > 0 ? -1 : 1);
          }, { passive: true });
        });
        document.addEventListener('touchstart', (event) => {
          const t = event.touches[0];
          const hit = t && t.target && t.target.closest && t.target.closest('.cal-week-times, .time-col-header');
          if (!hit) return;
          const x = t.clientX;
          const y = t.clientY;
          let horizontal = false;
          let vertical = false;
          const move = (ev) => {
            const p = ev.touches[0];
            if (!p || vertical) return;
            const dx = p.clientX - x;
            const dy = p.clientY - y;
            if (!horizontal) {
              if (Math.abs(dx) < 8 && Math.abs(dy) < 8) return;
              if (Math.abs(dy) > Math.abs(dx)) {
                vertical = true;
                return;
              }
              horizontal = true;
            }
            if (ev.cancelable) ev.preventDefault();
          };
          const end = (ev) => {
            document.removeEventListener('touchmove', move, true);
            document.removeEventListener('touchend', end, true);
            document.removeEventListener('touchcancel', end, true);
            if (!horizontal || ev.type === 'touchcancel') return;
            const p = ev.changedTouches && ev.changedTouches[0];
            const dx = p ? p.clientX - x : 0;
            if (Math.abs(dx) < 24) return;
            edgeGo(dx > 0 ? -1 : 1);
          };
          document.addEventListener('touchmove', move, { passive: false, capture: true });
          document.addEventListener('touchend', end, { passive: true, capture: true });
          document.addEventListener('touchcancel', end, { passive: true, capture: true });
        }, { passive: true, capture: true });
        window.addEventListener('hashchange', () => {
          const h = (location.hash || '').replace(/^#/, '');
          if (tabs.includes(h) && h !== currentTab) setTab(h);
        });
      "
      x-cloak>

    <div class="edge-swipe-left" aria-hidden="true"></div>

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
>
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
                root.setProperty('--nav-shift', shift + 'px');
            }
            var away = false;
            function syncNav() {
                if (!phone.matches) {
                    document.body.style.removeProperty('--nav-shift');
                    away = false;
                    return;
                }
                var width = Math.max(track.clientWidth, 1);
                if (track.scrollLeft < width * 0.5) {
                    away = false;
                    update();
                    return;
                }
                if (away) return;
                away = true;
                document.body.style.setProperty('--nav-shift', '0px');
            }
            shop.addEventListener('scroll', function () {
                if (track.scrollLeft < 6) update();
            }, { passive: true });
            /* Erst wenn die Seite steht. Mitten in der Bewegung reißt
               transform/clip-path das Einrasten ab (Shop bleibt halb stehen). */
            var finger = false;
            var navTimer;
            track.addEventListener('touchstart', function () {
                finger = true;
                clearTimeout(navTimer);
            }, { passive: true });
            track.addEventListener('touchend', function () { finger = false; }, { passive: true });
            track.addEventListener('touchcancel', function () { finger = false; }, { passive: true });
            track.addEventListener('scrollend', function () { if (!finger) syncNav(); }, { passive: true });
            track.addEventListener('scroll', function () {
                if (finger) return;
                clearTimeout(navTimer);
                navTimer = setTimeout(syncNav, 70);
            }, { passive: true });
            window.addEventListener('resize', syncNav);
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
