/**
 * Leaflet: Detailkarte + Touren-Übersicht.
 * Segmente nach Modus; Touch: Tippen = Infos; Desktop: Hover + Klick öffnet Tour.
 */
(function () {
    const MODE_COLORS = {
        hike: '#3d9b6a',
        ebike: '#4a90d9',
        bike: '#e76f51',
        sled: '#e63946',
        ski: '#457b9d',
        cable: '#1a1a1a',
    };

    const MODE_LABELS = {
        hike: 'Wandern',
        ebike: 'E-Bike',
        bike: 'Rad',
        sled: 'Rodeln',
        ski: 'Langlauf',
        cable: 'Seilbahn',
    };

    const START_KIND_COLORS = {
        ferienwohnung: '#6c63ff',
        bus: '#2563eb',
        auto: '#ca8a04',
    };

    /** Braun = unter aktiven Filtern mehrere Fortbewegungs-Optionen */
    const MULTI_OPTION_COLOR = '#8B5E3C';
    const NODE_COLOR = '#6c757d';
    const START_NODE_COLOR = START_KIND_COLORS.ferienwohnung;
    const TIP_NODE_COLOR = '#e85d04';
    const HIGHLIGHT_NODE_COLOR = '#e9c46a';
    const HUT_NODE_COLOR = '#b45309';

    function tourMediaUrl(path) {
        if (typeof window.tourImageUrl === 'function') return window.tourImageUrl(path);
        if (!path) return '';
        if (/^https?:\/\//i.test(path)) return path;
        return String(path).startsWith('/') ? path : ('/' + path);
    }

    function isHutNode(node) {
        if (!node) return false;
        if (node.is_hut) return true;
        return !!(node.offers_food || node.offers_lodging);
    }

    function startKindColor(node) {
        const kinds = (node && Array.isArray(node.start_kinds)) ? node.start_kinds : [];
        const prefer = ['ferienwohnung', 'bus', 'auto'];
        for (let i = 0; i < prefer.length; i++) {
            if (kinds.includes(prefer[i])) return START_KIND_COLORS[prefer[i]];
        }
        return START_NODE_COLOR;
    }

    /**
     * Touch-UI nur wenn kein Hover-fähiges Gerät da ist.
     * (any-hover/any-pointer: auch PCs mit Touchscreen behalten Maus-Hover)
     */
    function isTouchUi() {
        try {
            if (window.matchMedia('(any-hover: hover)').matches) return false;
            if (window.matchMedia('(any-pointer: fine)').matches) return false;
        } catch (e) {}
        return true;
    }

    function tripTitle(trip) {
        const lang = (typeof APP !== 'undefined' && APP.lang === 'en') ? 'en' : 'de';
        if (trip.title && trip.title[lang]) return trip.title[lang];
        return (trip.title && trip.title.de) || 'Tour';
    }

    function defaultVariant(trip) {
        const variants = trip.variants || [];
        return variants.find(v => v.is_default) || variants[0] || null;
    }

    function fmtKm(v) {
        const n = Number(v);
        if (!Number.isFinite(n)) return '–';
        return (Math.round(n * 100) / 100).toString().replace('.', ',');
    }

    function fmtHm(v) {
        const n = Number(v);
        if (!Number.isFinite(n)) return '–';
        return String(Math.round(n));
    }

    function tripTotals(trip, variant) {
        const v = variant || defaultVariant(trip);
        let km = Number(trip && trip.dist);
        let hm = Number(trip && trip.alt);
        if (!Number.isFinite(km) || km <= 0) km = Number(v && v.dist);
        if (!Number.isFinite(hm) || hm <= 0) hm = Number(v && v.alt);
        const segs = (v && v.segments) || [];
        if ((!Number.isFinite(km) || km <= 0) && segs.length) {
            km = segs.reduce((s, seg) => s + (Number(seg.distance_km) || 0), 0);
        }
        if ((!Number.isFinite(hm) || hm <= 0) && segs.length) {
            hm = segs.reduce((s, seg) => s + (Number(seg.elevation_m) || 0), 0);
        }
        return {
            km: Number.isFinite(km) ? km : 0,
            hm: Number.isFinite(hm) ? hm : 0,
        };
    }

    function isMapVisible(el) {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        return rect.width > 40 && rect.height > 40;
    }

    function overviewSelectionEls() {
        return {
            box: document.getElementById('tours-map-selection'),
            title: document.getElementById('tours-sel-title'),
            meta: document.getElementById('tours-sel-meta'),
            hut: document.getElementById('tours-sel-hut'),
            photos: document.getElementById('tours-sel-photos'),
            stats: document.getElementById('tours-sel-stats'),
            routeBtn: document.getElementById('tours-sel-route-btn'),
            startBtn: document.getElementById('tours-sel-start-btn'),
            goalBtn: document.getElementById('tours-sel-goal-btn'),
            viaBtn: document.getElementById('tours-sel-via-btn'),
            openBtn: document.getElementById('tours-sel-open'),
        };
    }

    function detailSelectionEls() {
        return {
            box: document.getElementById('trip-detail-seg-info'),
            title: document.getElementById('trip-detail-seg-title'),
            meta: document.getElementById('trip-detail-seg-meta'),
            hut: null,
            photos: null,
            stats: document.getElementById('trip-detail-seg-stats'),
            routeBtn: null,
            startBtn: null,
            goalBtn: null,
            viaBtn: null,
            openBtn: null,
        };
    }

    function hidePanel(els) {
        if (els && els.box) els.box.hidden = true;
        ['routeBtn', 'startBtn', 'goalBtn', 'viaBtn'].forEach(key => {
            const btn = els && els[key];
            if (!btn) return;
            btn.hidden = true;
            btn.style.display = 'none';
            btn.onclick = null;
        });
    }

    function renderPanelPhotos(photosEl, images) {
        if (!photosEl) return;
        const all = (images || []).filter(Boolean);
        const list = all.slice(0, 2);
        if (!list.length) {
            photosEl.hidden = true;
            photosEl.innerHTML = '';
            return;
        }
        photosEl.innerHTML = list.map((src, i) =>
            `<img src="${tourMediaUrl(src)}" alt="" loading="lazy" data-lb-index="${i}" class="tours-sel-photo">`
        ).join('');
        photosEl.hidden = false;
        photosEl.querySelectorAll('img.tours-sel-photo').forEach((img) => {
            img.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const idx = parseInt(img.getAttribute('data-lb-index'), 10) || 0;
                if (typeof window.openImageLightbox === 'function') {
                    window.openImageLightbox(all, idx);
                }
            });
        });
    }

    function segmentPanelDescription(seg, modes) {
        const lang = (typeof APP !== 'undefined' && APP.lang === 'en') ? 'en' : 'de';
        if (typeof window.segmentDisplayProfile === 'function') {
            const p = window.segmentDisplayProfile(seg, modes);
            if (p && p.description) {
                return String(p.description[lang] || p.description.de || '').trim();
            }
        }
        const profiles = (seg && seg.modes) || [];
        for (let i = 0; i < profiles.length; i++) {
            const m = profiles[i];
            if (!m || !m.is_active || !m.description) continue;
            const d = m.description;
            const text = typeof d === 'object'
                ? String(d[lang] || d.de || '').trim()
                : String(d).trim();
            if (text) return text;
        }
        return '';
    }

    function renderHutInfo(hutEl, info) {
        if (!hutEl) return;
        if (!info || !info.isHut) {
            hutEl.hidden = true;
            hutEl.innerHTML = '';
            return;
        }
        const bits = [];
        if (info.offersFood) bits.push('Essen');
        if (info.offersLodging) bits.push('Schlafen');
        if (info.seasonOpen) bits.push('offen ' + info.seasonOpen);
        hutEl.textContent = bits.join(' · ') || 'Hütte';
        hutEl.hidden = false;
    }

    function showPanel(els, info, { showOpenBtn, showRouteBtn }) {
        if (!els || !els.box || !els.title) return;

        els.title.textContent = info.title || info.modeLabel || 'Segment';
        if (els.meta) {
            const metaParts = info.kind === 'node'
                ? [info.modeLabel, info.segName].filter(Boolean)
                : [info.modeLabel, info.segName].filter(Boolean);
            els.meta.textContent = metaParts.join(' · ');
            els.meta.hidden = !els.meta.textContent;
        }

        renderPanelPhotos(els.photos, info.images);
        renderHutInfo(els.hut, info);

        if (els.stats) {
            els.stats.textContent = '';
            if (info.kind === 'node') {
                const desc = (info.description || '').trim();
                if (desc) {
                    const span = document.createElement('span');
                    span.className = 'tours-sel-seg';
                    span.textContent = desc;
                    els.stats.appendChild(span);
                }
                els.stats.hidden = !desc;
            } else {
                const hmHint = info.showHm
                    ? ` · ${fmtHm(info.tripHm)} Hm`
                    : '';
                const kmLine = document.createElement('span');
                kmLine.textContent = `${fmtKm(info.tripKm)} km${hmHint}`;
                els.stats.appendChild(kmLine);
                const segLine = document.createElement('span');
                segLine.className = 'tours-sel-seg';
                segLine.textContent = `Segment: ${fmtKm(info.segKm)} km`;
                els.stats.appendChild(segLine);
                const desc = (info.description || '').trim();
                if (desc) {
                    const descLine = document.createElement('span');
                    descLine.className = 'tours-sel-desc';
                    descLine.textContent = desc;
                    els.stats.appendChild(descLine);
                }
                els.stats.hidden = false;
            }
        }

        if (els.openBtn) {
            els.openBtn.style.display = showOpenBtn ? '' : 'none';
            els.openBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (typeof window.openTripDetail === 'function' && info.tripId != null) {
                    window.openTripDetail(info.tripId);
                }
            };
        }

        if (els.routeBtn) {
            const label = (info.routeBtnLabel || '').trim();
            const canRoute = !!showRouteBtn && !!label && typeof info.onAddRoute === 'function';
            els.routeBtn.hidden = !canRoute;
            els.routeBtn.style.display = canRoute ? '' : 'none';
            if (canRoute) {
                els.routeBtn.textContent = label;
                els.routeBtn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    info.onAddRoute();
                };
            } else {
                els.routeBtn.textContent = '';
                els.routeBtn.onclick = null;
            }
        }

        const wirePlanBtn = (btn, show, label, handler) => {
            if (!btn) return;
            const ok = !!show && typeof handler === 'function';
            btn.hidden = !ok;
            btn.style.display = ok ? '' : 'none';
            if (ok) {
                if (label) btn.textContent = label;
                btn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    handler();
                    hidePanel(els);
                    const mapEl = document.getElementById('tours-overview-map');
                    if (mapEl) mapEl._selectedKey = null;
                };
            } else {
                btn.onclick = null;
            }
        };
        wirePlanBtn(els.startBtn, info.showStartBtn, 'Als Start', info.onSetStart);
        wirePlanBtn(els.goalBtn, info.showGoalBtn, 'Als Ziel', info.onSetGoal);
        wirePlanBtn(els.viaBtn, info.showViaBtn, 'Zwischenziel', info.onSetVia);

        // Route immer über Button — nie is-desktop (das blendet den Button aus)
        els.box.classList.remove('is-desktop');
        els.box.hidden = false;
    }

    function ensureNodePane(map) {
        if (!map || map.getPane('tourNodes')) return;
        map.createPane('tourNodes');
        const pane = map.getPane('tourNodes');
        // Über den Strecken (overlay ~400). pointer-events:none am Pane —
        // nur die SVG-Kreise (interactive) fangen Maus, nicht die ganze Fläche.
        pane.style.zIndex = 650;
        pane.style.pointerEvents = 'none';
    }

    function createMap(container) {
        container.style.height = '';
        container.style.maxHeight = '';
        container.style.width = '100%';
        container.innerHTML = '';

        const phone = window.matchMedia('(max-width: 767px)').matches;
        const map = L.map(container, {
            zoomControl: true,
            attributionControl: true,
            dragging: !phone,
            tap: !phone,
            preferCanvas: true,
            fadeAnimation: false,
            zoomAnimation: false,
            // Mausrad: höher = weniger empfindlich (Leaflet-Default: 60)
            wheelPxPerZoomLevel: 180,
            zoomSnap: 0.5,
            zoomDelta: 0.5,
            renderer: L.canvas({ tolerance: 16 }),
        });
        ensureNodePane(map);

        const hideLoading = () => {
            const el = document.getElementById('tours-map-loading');
            if (el) el.hidden = true;
        };
        const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);
        const onTilesLoaded = () => {
            if (container.clientWidth < 10 || container.clientHeight < 10) {
                tiles.once('load', onTilesLoaded);
                return;
            }
            hideLoading();
        };
        tiles.once('load', onTilesLoaded);
        setTimeout(hideLoading, 15000);

        map.setView([47.13, 9.82], 11);
        return map;
    }

    function clearHighlight(lines) {
        (lines || []).forEach((line) => {
            if (!line || !line.setStyle) return;
            line.setStyle({
                color: line._baseColor || '#3d9b6a',
                weight: line._baseWeight || 5,
                opacity: line._baseOpacity != null ? line._baseOpacity : 0.95,
            });
        });
    }

    function highlightLine(lines, activeLine) {
        clearHighlight(lines);
        (lines || []).forEach((line) => {
            if (!line || !line.setStyle) return;
            if (line === activeLine) {
                line.setStyle({ weight: (line._baseWeight || 5) + 3, opacity: 1 });
                if (line.bringToFront) line.bringToFront();
            } else {
                line.setStyle({
                    weight: line._baseWeight || 5,
                    opacity: Math.min(0.35, line._baseOpacity != null ? line._baseOpacity : 0.35),
                });
            }
        });
    }

    function ensureOverviewMap(container) {
        const bindArrowZoom = (map) => {
            if (container._routeArrowZoomBound) return;
            container._routeArrowZoomBound = true;
            map.on('zoomend', () => {
                if (window.GUEST_ROUTE && window.GUEST_ROUTE.steps && window.GUEST_ROUTE.steps.length) {
                    window.drawGuestRouteDirectionArrows(map, container);
                }
            });
        };

        if (container._toursMap && container._toursLayer) {
            bindArrowZoom(container._toursMap);
            return container._toursMap;
        }
        if (container._toursMap) {
            container._toursMap.remove();
            container._toursMap = null;
        }
        const map = createMap(container);
        container._toursMap = map;
        container._toursLayer = L.featureGroup().addTo(map);

        map.on('click', () => {
            clearHighlight(container._lineRefs);
            hidePanel(overviewSelectionEls());
            container._selectedKey = null;
        });

        bindArrowZoom(map);

        const selBox = document.getElementById('tours-map-selection');
        if (selBox && !selBox._stopMapClick) {
            selBox._stopMapClick = true;
            L.DomEvent.disableClickPropagation(selBox);
            L.DomEvent.on(selBox, 'click', L.DomEvent.stopPropagation);
        }

        return map;
    }

    function destroyOverviewMap(container) {
        if (container._routeArrowLayer && container._toursMap) {
            try { container._toursMap.removeLayer(container._routeArrowLayer); } catch (e) { /* ignore */ }
            container._routeArrowLayer = null;
        }
        if (container._toursMap) {
            container._toursMap.remove();
            container._toursMap = null;
            container._toursLayer = null;
            container._lastBounds = null;
            container._selectedKey = null;
            container._lineRefs = null;
            container._routeArrowZoomBound = false;
        }
        container.innerHTML = '';
        hidePanel(overviewSelectionEls());
    }

    function segmentInfoFromTrip(trip, variant, seg) {
        const mode = seg.mode || 'hike';
        const totals = tripTotals(trip, variant);
        return {
            tripId: trip.id,
            title: tripTitle(trip),
            modeLabel: MODE_LABELS[mode] || mode,
            segName: seg.name || '',
            tripKm: totals.km,
            tripHm: totals.hm,
            segKm: seg.distance_km,
            segHm: seg.elevation_m,
            key: String(trip.id) + ':' + String(seg.id),
        };
    }

    /**
     * Detailkarte einer Tour — Segmente mit Infos (Tap/Hover).
     * data: { segments, title, tripKm, tripHm }
     */
    window.renderToursMap = function (container, data) {
        if (!container || typeof L === 'undefined') return;

        const touchUi = isTouchUi();
        const panel = detailSelectionEls();
        hidePanel(panel);

        if (container._toursMap) {
            container._toursMap.remove();
            container._toursMap = null;
        }

        const map = createMap(container);
        container._toursMap = map;
        container._lineRefs = [];
        container._selectedKey = null;

        map.on('click', () => {
            clearHighlight(container._lineRefs);
            hidePanel(panel);
            container._selectedKey = null;
        });

        const bounds = [];
        const tripKm = data && data.tripKm;
        const tripHm = data && data.tripHm;
        const title = (data && data.title) || 'Tour';

        if (data && Array.isArray(data.segments)) {
            data.segments.forEach((seg, idx) => {
                const coords = (seg.geojson && seg.geojson.coordinates) || [];
                if (coords.length < 2) return;
                const latlngs = coords
                    .map((c) => [Number(c[1]), Number(c[0])])
                    .filter((ll) => Number.isFinite(ll[0]) && Number.isFinite(ll[1]));
                if (latlngs.length < 2) return;

                const mode = seg.mode || 'hike';
                const color = MODE_COLORS[mode] || '#3d9b6a';
                const modeLabel = MODE_LABELS[mode] || mode;
                const baseWeight = touchUi ? 8 : 5;
                const info = {
                    title,
                    modeLabel,
                    segName: seg.name || '',
                    tripKm,
                    tripHm,
                    segKm: seg.distance_km,
                    segHm: seg.elevation_m,
                    key: 'detail:' + String(seg.id || idx),
                };

                const line = L.polyline(latlngs, {
                    color,
                    weight: baseWeight,
                    opacity: 0.95,
                    lineJoin: 'round',
                    lineCap: 'round',
                    renderer: map.options.renderer,
                });
                line._baseWeight = baseWeight;

                if (!touchUi) {
                    line.bindTooltip(
                        `<strong>${modeLabel}</strong>`
                        + (info.segName ? ` · ${info.segName}` : '')
                        + `<br>Tour ${fmtKm(tripKm)} km · ${fmtHm(tripHm)} Hm`
                        + `<br>Segment ${fmtKm(info.segKm)} km · ${fmtHm(info.segHm)} Hm`,
                        { sticky: true, opacity: 0.95 }
                    );
                    // Hover: nur Highlight — Panel erst per Klick (sonst Flackern über der Leiste)
                    line.on('mouseover', function () {
                        if (container._selectedKey) return;
                        highlightLine(container._lineRefs, this);
                    });
                    line.on('mouseout', function () {
                        if (container._selectedKey) return;
                        clearHighlight(container._lineRefs);
                    });
                }

                line.on('click', function (e) {
                    L.DomEvent.stopPropagation(e);
                    if (touchUi) {
                        if (container._selectedKey === info.key) {
                            container._selectedKey = null;
                            clearHighlight(container._lineRefs);
                            hidePanel(panel);
                            return;
                        }
                        container._selectedKey = info.key;
                        highlightLine(container._lineRefs, this);
                        showPanel(panel, info, { showOpenBtn: false });
                    } else {
                        highlightLine(container._lineRefs, this);
                        showPanel(panel, info, { showOpenBtn: false });
                        container._selectedKey = info.key;
                    }
                });

                line.addTo(map);
                container._lineRefs.push(line);
                latlngs.forEach((ll) => bounds.push(ll));
            });
        }

        const finish = () => {
            map.invalidateSize(true);
            if (bounds.length >= 2) {
                map.fitBounds(bounds, { padding: [28, 28], maxZoom: 15 });
            } else if (bounds.length === 1) {
                map.setView(bounds[0], 13);
            }
        };

        requestAnimationFrame(() => setTimeout(finish, 50));
    };

    /**
     * Übersichtskarte: Segmente + Nodes + Probe-Route.
     * Braun = mehrere Sportarten; Schwarz = Seilbahn.
     */
    window.updateToursOverviewMap = function (segments, options) {
        const opts = options || {};
        const fitBounds = opts.fitBounds === true;
        const modes = opts.modes || (typeof window.getActiveModes === 'function' ? window.getActiveModes() : ['hike']);
        const touchUi = isTouchUi();
        const panel = overviewSelectionEls();

        const container = document.getElementById('tours-overview-map');
        const emptyEl = document.getElementById('tours-overview-empty');
        const legendEl = document.getElementById('tours-map-legend');
        if (!container || typeof L === 'undefined') return;

        container._pendingSegments = segments || [];
        container._pendingModes = modes;

        if (!isMapVisible(container)) {
            return;
        }

        const map = ensureOverviewMap(container);
        const layer = container._toursLayer;
        layer.clearLayers();
        container._lineRefs = [];
        container._selectedKey = null;
        hidePanel(panel);
        if (container._routeArrowLayer) {
            try { map.removeLayer(container._routeArrowLayer); } catch (e) { /* ignore */ }
            container._routeArrowLayer = null;
        }

        const bounds = [];
        let withTrack = 0;
        let hasMulti = false;
        let hasCable = false;
        let hasHighlightNode = false;
        let hasHutNode = false;
        const usedSingleModes = new Set();
        const usedStartKinds = new Set();
        let hasRoute = false;

        const routeIds = (typeof window.getGuestRouteSegmentIds === 'function')
            ? window.getGuestRouteSegmentIds()
            : new Set();
        const connectIds = (typeof window.getConnectableSegmentIds === 'function')
            ? window.getConnectableSegmentIds(container._pendingSegments)
            : new Set();
        const routeActive = typeof window.getRouteTipNodeId === 'function'
            && window.getRouteTipNodeId() != null;
        const routeModeBySeg = {};
        ((window.GUEST_ROUTE && window.GUEST_ROUTE.steps) || []).forEach(step => {
            if (step && step.segmentId != null && step.mode) {
                routeModeBySeg[Number(step.segmentId)] = step.mode;
            }
        });

        const drawOverviewSeg = (seg) => {
            const coords = (seg.geojson && seg.geojson.coordinates) || [];
            if (coords.length < 2) return;
            let latlngs = coords
                .map((c) => [Number(c[1]), Number(c[0])])
                .filter((ll) => Number.isFinite(ll[0]) && Number.isFinite(ll[1]));
            if (latlngs.length < 2) return;

            withTrack++;
            const colorInfo = (typeof window.segmentMapColorInfo === 'function')
                ? window.segmentMapColorInfo(seg, modes)
                : { multi: false, mode: 'hike', modes: ['hike'] };

            if (colorInfo.cable || colorInfo.mode === 'cable') hasCable = true;
            else if (colorInfo.multi) hasMulti = true;
            else if (colorInfo.mode) usedSingleModes.add(colorInfo.mode);

            const inRoute = routeIds.has(Number(seg.id));
            const canConnect = connectIds.has(Number(seg.id));
            if (inRoute) hasRoute = true;

            let color = colorInfo.cable || colorInfo.mode === 'cable'
                ? MODE_COLORS.cable
                : (colorInfo.multi
                    ? MULTI_OPTION_COLOR
                    : (MODE_COLORS[colorInfo.mode] || MODE_COLORS.hike));
            let opacity = routeActive && !inRoute && !canConnect ? 0.28 : 0.95;
            let baseWeight = touchUi ? 8 : 5;
            const isCableSeg = !!(colorInfo.cable || colorInfo.mode === 'cable'
                || (typeof window.isSeilbahnSegment === 'function' && window.isSeilbahnSegment(seg)));
            if (inRoute) {
                // Wie bisher: Seilbahn behält eigene Farbe; sonst Sportart der Route
                if (isCableSeg) {
                    color = MODE_COLORS.cable;
                    hasCable = true;
                } else {
                    const stepMode = routeModeBySeg[Number(seg.id)];
                    if (stepMode && MODE_COLORS[stepMode] && stepMode !== 'cable') {
                        color = MODE_COLORS[stepMode];
                        usedSingleModes.add(stepMode);
                    }
                }
                opacity = 1;
                baseWeight = (touchUi ? 8 : 5) + 3;
            } else if (canConnect) {
                // Sportartfarbe belassen — nur etwas kräftiger/dicker als verblasste Segmente
                opacity = 1;
                baseWeight = (touchUi ? 8 : 5) + 1;
            }

            const modeLabel = isCableSeg
                ? MODE_LABELS.cable
                : (colorInfo.multi
                    ? (colorInfo.modes || []).map(m => MODE_LABELS[m] || m).join(' / ')
                    : (MODE_LABELS[colorInfo.mode] || colorInfo.mode || ''));
            const baseKm = Number(seg.distance_km) || 0;
            const baseHm = Number(seg.elevation_m) || 0;
            const effKm = seg.out_and_back ? baseKm * 2 : baseKm;
            const effHm = baseHm;
            const isCable = isCableSeg;
            const routeAction = (typeof window.getSegmentRouteAction === 'function')
                ? window.getSegmentRouteAction(seg, {
                    canConnect,
                    connectIds,
                    inRoute,
                })
                : null;
            const info = {
                kind: 'segment',
                tripId: null,
                segmentId: seg.id,
                title: seg.name || 'Segment',
                modeLabel: colorInfo.multi ? ('Mehrere Optionen: ' + modeLabel) : modeLabel,
                segName: [
                    inRoute ? 'In Route' : (canConnect ? 'Anschließbar' : ''),
                    isCable ? 'Seilbahn' : '',
                    seg.out_and_back ? 'Sackgasse · Hin+Retour' : '',
                    seg.is_highlight ? 'Highlight-Ort' : '',
                ].filter(Boolean).join(' · '),
                tripKm: effKm,
                tripHm: effHm,
                segKm: baseKm,
                segHm: baseHm,
                showHm: false,
                description: segmentPanelDescription(seg, modes),
                images: Array.isArray(seg.images) ? seg.images : [],
                key: 'seg:' + String(seg.id),
                routeBtnLabel: routeAction ? routeAction.label : '',
                onAddRoute: routeAction ? () => {
                    if (typeof window.onGuestRouteSegmentClick === 'function') {
                        window.onGuestRouteSegmentClick(routeAction.targetSeg);
                    }
                } : null,
            };

            const line = L.polyline(latlngs, {
                color,
                weight: baseWeight,
                opacity,
                lineJoin: 'round',
                lineCap: 'round',
            });
            line._baseColor = color;
            line._baseWeight = baseWeight;
            line._baseOpacity = opacity;
            line._segmentId = seg.id;

            const tipHtml =
                `<strong>${info.title}</strong><br>`
                + `${info.modeLabel}`
                + (colorInfo.multi && !inRoute ? ' · Braun = wählen' : '')
                + (inRoute ? ' · In Route' : (canConnect ? ' · Anschließbar' : ''))
                + (isCable ? ' · Seilbahn' : '')
                + (seg.out_and_back ? ' · Sackgasse' : '')
                + `<br>${isCable ? '0 km (Transport)' : (fmtKm(effKm) + ' km')}`
                + (seg.out_and_back ? ` <span style="opacity:.75">(2× ${fmtKm(baseKm)})</span>` : '')
                + (routeAction
                    ? `<br><span style="opacity:.75">Klick → ${routeAction.label}</span>`
                    : '');

            const showRoute = !!routeAction;

            if (!touchUi) {
                line.bindTooltip(tipHtml, { sticky: true, opacity: 0.95 });
                // Hover: nur Highlight — Panel erst per Klick (sonst Flackern über der Leiste)
                line.on('mouseover', function () {
                    if (container._selectedKey) return;
                    highlightLine(container._lineRefs, this);
                });
                line.on('mouseout', function () {
                    if (container._selectedKey) return;
                    clearHighlight(container._lineRefs);
                });
            }

            line.on('click', function (e) {
                L.DomEvent.stopPropagation(e);
                if (container._selectedKey === info.key) {
                    container._selectedKey = null;
                    clearHighlight(container._lineRefs);
                    hidePanel(panel);
                    return;
                }
                container._selectedKey = info.key;
                highlightLine(container._lineRefs, this);
                // Aktion frisch berechnen (Route kann sich geändert haben)
                const freshConnect = (typeof window.getConnectableSegmentIds === 'function')
                    ? window.getConnectableSegmentIds(container._pendingSegments || [])
                    : connectIds;
                const fresh = (typeof window.getSegmentRouteAction === 'function')
                    ? window.getSegmentRouteAction(seg, {
                        canConnect: freshConnect.has(Number(seg.id)),
                        connectIds: freshConnect,
                        inRoute: (typeof window.getGuestRouteSegmentIds === 'function')
                            ? window.getGuestRouteSegmentIds().has(Number(seg.id))
                            : inRoute,
                    })
                    : null;
                info.routeBtnLabel = fresh ? fresh.label : '';
                info.onAddRoute = fresh ? () => {
                    if (typeof window.onGuestRouteSegmentClick === 'function') {
                        window.onGuestRouteSegmentClick(fresh.targetSeg);
                    }
                } : null;
                showPanel(panel, info, { showOpenBtn: false, showRouteBtn: !!fresh });
            });

            layer.addLayer(line);
            container._lineRefs.push(line);
            latlngs.forEach((ll) => bounds.push(ll));
        };

        const finishOverview = () => {

        // Anschließbare Segmente nach vorn; offene Rad-Retouren zuerst darunter,
        // damit Abstecher (Wandern) zwischen E-Bike-Hin und -Retour klickbar bleiben
        const openRevIds = (typeof window.getOpenWheeledReturns === 'function')
            ? new Set([...window.getOpenWheeledReturns().keys()].map(Number))
            : new Set();
        container._lineRefs.forEach((line) => {
            const id = Number(line._segmentId);
            if (connectIds.has(id) && openRevIds.has(id)) line.bringToFront();
        });
        container._lineRefs.forEach((line) => {
            const id = Number(line._segmentId);
            if (connectIds.has(id) && !openRevIds.has(id)) line.bringToFront();
        });

        // Alle Nodes der sichtbaren Segmente (+ Startkandidaten)
        const nodes = opts.nodes || window.TOUR_NODES || [];
        const nodeById = new Map(nodes.map(n => [Number(n.id), n]));
        const visibleNodeIds = new Set();
        container._pendingSegments.forEach(seg => {
            visibleNodeIds.add(Number(seg.from_node_id));
            visibleNodeIds.add(Number(seg.to_node_id));
        });
        const hutOvernight = typeof window.getHutOvernightEnabled === 'function'
            && window.getHutOvernightEnabled();
        nodes.forEach(n => {
            const kinds = Array.isArray(n.start_kinds) ? n.start_kinds : [];
            if (n.is_start_candidate || kinds.length) visibleNodeIds.add(Number(n.id));
            if (hutOvernight && n.offers_lodging) visibleNodeIds.add(Number(n.id));
            // Highlights / Sackgassen immer anzeigen (auch wenn Spur-Segment gerade gefiltert ist)
            if (n.is_highlight || n.is_dead_end) visibleNodeIds.add(Number(n.id));
        });

        // Via-/Ziel-Knoten aus dem Planer ebenfalls sichtbar halten
        const plan = window.GUEST_PLAN || {};
        [plan.startNodeId, plan.goalNodeId].concat(plan.vias || []).forEach(id => {
            if (id != null) visibleNodeIds.add(Number(id));
        });

        const routeStartId = (window.GUEST_ROUTE && window.GUEST_ROUTE.startNodeId)
            ? Number(window.GUEST_ROUTE.startNodeId)
            : null;
        const tipNodeId = (typeof window.getRouteTipNodeId === 'function')
            ? window.getRouteTipNodeId()
            : null;
        const filterStartId = opts.startNodeId ? Number(opts.startNodeId) : null;
        let hasLodgingStart = false;

        visibleNodeIds.forEach(id => {
            const n = nodeById.get(id);
            if (!n || n.lat == null || n.lng == null) return;
            const kinds = Array.isArray(n.start_kinds) ? n.start_kinds : [];
            const isKindStart = !!n.is_start_candidate || kinds.length > 0;
            const isLodgingStart = hutOvernight && !!n.offers_lodging;
            const isStartCand = isKindStart || isLodgingStart;
            const isHighlight = !!n.is_highlight;
            const isHut = isHutNode(n);
            const isTip = tipNodeId != null && id === tipNodeId;
            const isRouteStart = routeStartId != null && id === routeStartId;
            const isFilterStart = filterStartId != null && id === filterStartId;
            const kindFill = startKindColor(n);
            kinds.forEach(k => usedStartKinds.add(k));
            if (isHighlight) hasHighlightNode = true;
            if (isHut) hasHutNode = true;
            if (isLodgingStart) hasLodgingStart = true;

            // Etwas größer + eigenes Pane → Hover/Klick vor den Strecken
            let radius = isStartCand ? 11 : ((isHighlight || isHut) ? 10 : 8);
            let fill = isKindStart
                ? kindFill
                : (isLodgingStart || isHut ? HUT_NODE_COLOR : (isHighlight ? HIGHLIGHT_NODE_COLOR : NODE_COLOR));
            let weight = (isHighlight || isHut || isLodgingStart) ? 3 : 2;
            let border = isLodgingStart
                ? '#fff'
                : (isHut ? '#7c2d12' : (isHighlight ? '#b08900' : '#fff'));
            if (isTip) {
                fill = TIP_NODE_COLOR;
                radius = 12;
                weight = 3;
                border = '#fff';
            } else if (isRouteStart || isFilterStart) {
                fill = isKindStart ? kindFill : HUT_NODE_COLOR;
                radius = 11;
            }

            ensureNodePane(map);
            // SVG (nicht Canvas): Canvas-Pane blockiert sonst die ganze Karte für Segmente
            if (!map._tourNodesRenderer) {
                map._tourNodesRenderer = L.svg({ pane: 'tourNodes' });
            }
            const marker = L.circleMarker([n.lat, n.lng], {
                radius,
                color: border,
                weight,
                fillColor: fill,
                fillOpacity: 1,
                pane: 'tourNodes',
                renderer: map._tourNodesRenderer,
                bubblingMouseEvents: false,
                interactive: true,
            });
            const kindLabel = kinds.map(k => (
                (typeof window.TOUR_START_KIND_LABELS === 'object' && window.TOUR_START_KIND_LABELS[k]) || k
            )).join('/');
            const label = (n.name || 'Punkt')
                + (kindLabel ? ` (${kindLabel})` : '')
                + (isLodgingStart ? ' · Übernachtung/Start' : (isStartCand && isKindStart ? ' (Start)' : ''))
                + (isHut && !isLodgingStart ? ' · Hütte' : '')
                + (isHighlight ? ' ★' : '')
                + (n.is_dead_end ? ' · Sackgasse' : '')
                + (isTip ? ' · hier' : '');
            marker.bindTooltip(label, { permanent: false, direction: 'top' });

            const lang = (typeof APP !== 'undefined' && APP.lang === 'en') ? 'en' : 'de';
            const descObj = n.description && typeof n.description === 'object' ? n.description : null;
            const description = descObj
                ? (descObj[lang] || descObj.de || '')
                : (n.notes || '');
            const nodeAction = (typeof window.getNodeRouteAction === 'function')
                ? window.getNodeRouteAction(n)
                : null;
            const applyNodeActions = (info, action) => {
                info.routeBtnLabel = '';
                info.onAddRoute = null;
                info.showStartBtn = !!(action && action.canStart);
                info.showGoalBtn = !!(action && action.canGoal);
                info.showViaBtn = !!(action && (action.canVia || action.canGoal));
                info.onSetStart = action ? () => {
                    if (typeof window.setPlannerStart === 'function') window.setPlannerStart(n.id);
                    else if (typeof window.setGuestRouteStart === 'function') window.setGuestRouteStart(n.id, true);
                } : null;
                info.onSetGoal = action ? () => {
                    if (typeof window.setPlannerGoal === 'function') window.setPlannerGoal(n.id);
                } : null;
                info.onSetVia = action ? () => {
                    if (typeof window.addPlannerVia === 'function') window.addPlannerVia(n.id);
                } : null;
            };
            const nodeInfo = {
                kind: 'node',
                title: n.name || 'Punkt',
                modeLabel: [
                    isLodgingStart ? 'Hütte · Start/Ende' : (isHut ? 'Hütte' : ''),
                    isHighlight ? 'Highlight' : '',
                    kindLabel || (isKindStart ? 'Start' : ''),
                ].filter(Boolean).join(' · '),
                segName: '',
                images: Array.isArray(n.images) ? n.images : [],
                isHut,
                offersFood: !!n.offers_food,
                offersLodging: !!n.offers_lodging,
                seasonOpen: n.season_open || '',
                description,
                notes: n.notes || '',
                key: 'node:' + String(n.id),
            };
            applyNodeActions(nodeInfo, nodeAction);

            // Hover: nur Tooltip — Panel erst per Klick (Highlight über der Leiste flackerte sonst)
            marker.on('click', function (e) {
                L.DomEvent.stopPropagation(e);
                if (container._selectedKey === nodeInfo.key) {
                    container._selectedKey = null;
                    hidePanel(panel);
                    return;
                }
                container._selectedKey = nodeInfo.key;
                const freshNode = (typeof window.getNodeRouteAction === 'function')
                    ? window.getNodeRouteAction(n)
                    : null;
                applyNodeActions(nodeInfo, freshNode);
                showPanel(panel, nodeInfo, {
                    showOpenBtn: false,
                    showRouteBtn: false,
                });
            });
            layer.addLayer(marker);
            bounds.push([n.lat, n.lng]);
        });

        if (emptyEl) emptyEl.style.display = withTrack === 0 ? 'block' : 'none';
        if (legendEl) {
            const order = ['hike', 'bike', 'ebike', 'ski', 'sled'];
            let html = order
                .filter(m => usedSingleModes.has(m))
                .concat(Array.from(usedSingleModes).filter(m => !order.includes(m)))
                .map(mode =>
                    `<span class="tours-legend-item"><i style="background:${MODE_COLORS[mode] || '#555'}"></i>${MODE_LABELS[mode] || mode}</span>`
                ).join('');
            if (hasCable) {
                html += `<span class="tours-legend-item"><i style="background:${MODE_COLORS.cable}"></i>Seilbahn</span>`;
            }
            if (hasMulti) {
                html += `<span class="tours-legend-item"><i style="background:${MULTI_OPTION_COLOR}"></i>Mehrere Optionen</span>`;
            }
            const kindOrder = ['ferienwohnung', 'bus', 'auto'];
            const kindLabels = { ferienwohnung: 'Ferienwohnung', bus: 'Bus', auto: 'Auto' };
            kindOrder.filter(k => usedStartKinds.has(k)).forEach(k => {
                html += `<span class="tours-legend-item"><i class="tours-legend-dot" style="background:${START_KIND_COLORS[k]}"></i>${kindLabels[k]}</span>`;
            });
            if (!usedStartKinds.size) {
                html += `<span class="tours-legend-item"><i class="tours-legend-dot" style="background:${START_NODE_COLOR}"></i>Startpunkt</span>`;
            }
            if (hasHighlightNode) {
                html += `<span class="tours-legend-item"><i class="tours-legend-dot" style="background:${HIGHLIGHT_NODE_COLOR}"></i>Highlight</span>`;
            }
            if (hasHutNode) {
                html += `<span class="tours-legend-item"><i class="tours-legend-dot" style="background:${HUT_NODE_COLOR}"></i>${hasLodgingStart ? 'Hütte / Übernachtung' : 'Hütte'}</span>`;
            }
            html += `<span class="tours-legend-item"><i class="tours-legend-dot" style="background:${NODE_COLOR}"></i>Ort</span>`;
            if (hasRoute) {
                html += `<span class="tours-legend-item tours-legend-note">Route = dickere Linie in Sportartfarbe</span>`;
            }
            legendEl.innerHTML = html;
        }

        window.drawGuestRouteDirectionArrows(map, container);

        const applyView = () => {
            map.invalidateSize(true);
            if (!fitBounds) return;
            if (bounds.length >= 2) {
                const b = L.latLngBounds(bounds);
                container._lastBounds = b;
                map.fitBounds(b, { padding: [40, 40], maxZoom: 14 });
            } else if (bounds.length === 1) {
                map.setView(bounds[0], 13);
            } else {
                map.setView([47.13, 9.82], 11);
            }
        };

        requestAnimationFrame(() => {
            applyView();
            if (fitBounds) setTimeout(applyView, 80);
        });
        };

        const segList = container._pendingSegments || [];
        const markBooted = () => {
            if (!window._toursChunkMap || window._toursWanted === false) return;
            window._toursChunkMap = false;
            window._toursGuestBooted = true;
            window._toursBooting = false;
            window.syncToursLoadButton && window.syncToursLoadButton();
            if (window._toursObs) {
                try { window._toursObs.disconnect(); } catch (e) {}
                window._toursObs = null;
            }
        };
        if (!window._toursChunkMap) {
            segList.forEach(drawOverviewSeg);
            finishOverview();
            return;
        }
        let segAt = 0;
        const drawSome = () => {
            if (window._toursWanted === false) {
                window._toursMapDrawTimer = null;
                window._toursChunkMap = false;
                window._toursBooting = false;
                return;
            }
            const t0 = performance.now();
            while (segAt < segList.length && performance.now() - t0 < 12) {
                drawOverviewSeg(segList[segAt]);
                segAt += 1;
            }
            if (segAt < segList.length) {
                window._toursMapDrawTimer = setTimeout(drawSome, 0);
                return;
            }
            window._toursMapDrawTimer = null;
            finishOverview();
            markBooted();
        };
        window._toursMapDrawTimer = setTimeout(drawSome, 0);
    };

    /**
     * Richtungspfeile auf der Routenlinie.
     * Segmente, die hin+retour vorkommen (auch im Rundweg) → ein zusammenhängendes ↔.
     * Zoom-abhängig dichter (Komoot-ähnlich).
     */
    window.drawGuestRouteDirectionArrows = function (map, container) {
        if (!map || !container || typeof L === 'undefined') return;
        if (container._routeArrowLayer) {
            try { map.removeLayer(container._routeArrowLayer); } catch (e) { /* ignore */ }
            container._routeArrowLayer = null;
        }
        const allSteps = (window.GUEST_ROUTE && window.GUEST_ROUTE.steps) || [];
        if (!allSteps.length) return;

        const pool = window.ALL_SEGMENTS || [];
        const tripShape = (window.GUEST_PLAN && window.GUEST_PLAN.tripShape) || '';
        const forceAllDual = tripShape === 'out_and_back';

        const undirectedKey = (seg) => {
            const sid = Number(seg.id);
            const rev = typeof window.findReverseSegment === 'function'
                ? window.findReverseSegment(seg, pool)
                : null;
            const a = Math.min(sid, rev ? Number(rev.id) : sid);
            const b = Math.max(sid, rev ? Number(rev.id) : sid);
            return a + ':' + b;
        };

        // Wie oft kommt die undirected Strecke in der Route vor? (≥2 = Hin+Retour)
        const keyVisits = new Map();
        allSteps.forEach(step => {
            const seg = pool.find(s => Number(s.id) === Number(step.segmentId));
            if (!seg) return;
            const key = undirectedKey(seg);
            keyVisits.set(key, (keyVisits.get(key) || 0) + 1);
        });

        const isDualKey = (key) => forceAllDual || (keyVisits.get(key) || 0) >= 2;

        // Retour überspringen; Stücke nach dual/einfach trennen
        const MAX_JUMP_M = 90;
        const pieces = [];
        let cur = [];
        let curDual = null;
        const flush = () => {
            if (cur.length >= 2) pieces.push({ dual: !!curDual, path: cur });
            cur = [];
            curDual = null;
        };
        const pushPt = (ll, dual) => {
            if (cur.length && curDual !== null && curDual !== dual) flush();
            if (cur.length && cur[cur.length - 1].distanceTo(ll) < 2) return;
            if (cur.length && cur[cur.length - 1].distanceTo(ll) > MAX_JUMP_M) flush();
            if (!cur.length) curDual = dual;
            cur.push(ll);
        };

        const seen = new Set();
        allSteps.forEach(step => {
            const seg = pool.find(s => Number(s.id) === Number(step.segmentId));
            if (!seg || !seg.geojson || !Array.isArray(seg.geojson.coordinates)) return;
            const key = undirectedKey(seg);
            if (seen.has(key)) return;
            seen.add(key);
            const dual = isDualKey(key);
            let coords = seg.geojson.coordinates.slice();
            if (step.reversed) coords.reverse();
            coords.forEach(c => {
                const lat = Number(c[1]);
                const lng = Number(c[0]);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                pushPt(L.latLng(lat, lng), dual);
            });
        });
        flush();
        if (!pieces.length) return;

        const bearingDeg = (a, b) => {
            const toRad = Math.PI / 180;
            const dLon = (b.lng - a.lng) * toRad;
            const lat1 = a.lat * toRad;
            const lat2 = b.lat * toRad;
            const y = Math.sin(dLon) * Math.cos(lat2);
            const x = Math.cos(lat1) * Math.sin(lat2)
                - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);
            return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
        };

        const angleDiff = (a, b) => {
            let d = Math.abs(a - b) % 360;
            if (d > 180) d = 360 - d;
            return d;
        };

        const arrowGapForZoom = (zoom) => {
            const z = Number(zoom);
            if (!Number.isFinite(z)) return 1800;
            if (z <= 11) return 3200;
            if (z <= 12) return 2400;
            if (z <= 13) return 1600;
            if (z <= 14) return 1000;
            if (z <= 15) return 650;
            if (z <= 16) return 400;
            return 250;
        };

        const gap = arrowGapForZoom(map.getZoom());
        const lookAhead = Math.min(120, Math.max(40, gap * 0.08));
        const straightWin = Math.min(90, Math.max(30, gap * 0.05));
        const searchRadius = Math.min(350, Math.max(80, gap * 0.2));
        const maxArrows = 80;

        const samplePiece = (path) => {
            const cum = [0];
            for (let i = 1; i < path.length; i++) {
                cum.push(cum[i - 1] + path[i - 1].distanceTo(path[i]));
            }
            const totalM = cum[cum.length - 1];
            if (totalM < 80) return [];

            const pointAt = (distM) => {
                const d = Math.max(0, Math.min(totalM, distM));
                let i = 1;
                while (i < cum.length && cum[i] < d) i++;
                const a = path[i - 1];
                const b = path[Math.min(i, path.length - 1)];
                const segStart = cum[i - 1];
                const segLen = Math.max(1e-6, (cum[i] || totalM) - segStart);
                const t = (d - segStart) / segLen;
                return L.latLng(
                    a.lat + (b.lat - a.lat) * t,
                    a.lng + (b.lng - a.lng) * t
                );
            };

            const out = [];
            const count = Math.max(1, Math.round(totalM / Math.max(120, gap)));
            for (let i = 0; i < count && out.length < maxArrows; i++) {
                const target = ((i + 0.5) / count) * totalM;
                let bestD = target;
                let bestBend = 999;
                const from = Math.max(straightWin, target - searchRadius);
                const to = Math.min(totalM - straightWin, target + searchRadius);
                if (to > from) {
                    for (let d = from; d <= to; d += 40) {
                        const p0 = pointAt(d - straightWin);
                        const p1 = pointAt(d);
                        const p2 = pointAt(d + straightWin);
                        const bend = angleDiff(bearingDeg(p0, p1), bearingDeg(p1, p2));
                        if (bend < bestBend) {
                            bestBend = bend;
                            bestD = d;
                        }
                    }
                }
                const p1 = pointAt(bestD);
                const ahead = pointAt(Math.min(totalM, bestD + lookAhead));
                if (p1.distanceTo(ahead) < 8) continue;
                out.push({ ll: p1, brng: bearingDeg(p1, ahead) });
            }
            return out;
        };

        const candidates = [];
        pieces.forEach(piece => {
            samplePiece(piece.path).forEach(c => {
                candidates.push({ ll: c.ll, brng: c.brng, dual: piece.dual });
            });
        });
        if (!candidates.length) return;

        const layer = L.layerGroup().addTo(map);
        container._routeArrowLayer = layer;

        // Einzeln: →  |  Doppel: ein ←→ mit durchgängigem Schaft (kein 2× Icon)
        const singleSvg = `<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">`
            + `<path fill="#fff" d="M4 11h10.2l-3.6-3.6L12 6l6 6-6 6-1.4-1.4 3.6-3.6H4z"/></svg>`;
        const dualSvg = `<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">`
            + `<path fill="#fff" d="M2 12l5.5-5.5 1.2 1.2L7 10h10l1.3-2.3L19.5 6.5 22 12l-5.5 5.5-1.2-1.2L17 14H7l-1.3 2.3L5.5 17.5 2 12z"/></svg>`;

        candidates.forEach(m => {
            const rot = m.brng - 90;
            const icon = L.divIcon({
                className: 'tours-route-dir-icon',
                html: `<div class="tours-route-dir-arrow${m.dual ? ' tours-route-dir-arrow--dual' : ''}" style="transform:rotate(${rot}deg)">`
                    + (m.dual ? dualSvg : singleSvg)
                    + `</div>`,
                iconSize: [22, 22],
                iconAnchor: [11, 11],
            });
            L.marker(m.ll, {
                icon,
                interactive: false,
                keyboard: false,
                zIndexOffset: 400,
            }).addTo(layer);
        });
    };

    window.invalidateToursOverviewMap = function () {
        const container = document.getElementById('tours-overview-map');
        if (!container || !container._pendingSegments) return;
        if (!isMapVisible(container)) return;
        destroyOverviewMap(container);
        window.updateToursOverviewMap(container._pendingSegments, {
            fitBounds: true,
            modes: container._pendingModes || (typeof window.getActiveModes === 'function' ? window.getActiveModes() : ['hike']),
            startNodeId: container._pendingStartId || null,
            nodes: window.TOUR_NODES || [],
        });
    };
})();
