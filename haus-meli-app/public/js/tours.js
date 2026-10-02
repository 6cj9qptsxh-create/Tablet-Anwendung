/**
 * ==========================================================================================
 * TOUREN & AKTIVITÄTEN (ehemals Hikes)
 * ==========================================================================================
 */

/**
 * Bilder liegen relativ als /tours/... (gespiegelt in die Tablet-App).
 * Fallback: APP.toursMediaUrl (Admin-Server), falls lokal noch nicht gespiegelt.
 */
window.tourImageUrl = function (src) {
    if (!src) return '';
    if (/^https?:\/\//i.test(src)) return src;
    const base = (typeof APP !== 'undefined' && APP.toursMediaUrl) ? String(APP.toursMediaUrl).replace(/\/$/, '') : '';
    if (base) return base + (src.startsWith('/') ? src : '/' + src);
    return src.startsWith('/') ? src : '/' + src;
};

// 1. Globale Variablen
window.currentLbImages = [];
window.currentLbIndex = 0;
window.hikesFiltersInitialized = false;

// Filter Globals
const filterDefinitions = {
    diff: { 1: "Stufe 1", 2: "Stufe 2", 3: "Stufe 3", 4: "Stufe 4", 5: "Stufe 5" },
    tags: {}
};

/** Leere Liste = kein Filter (alles sichtbar). Anwählen schränkt ein. */
let activeFilters = {
    modes: [],
    startKinds: [],
    diff: [],
    tags: [],
};

const FILTER_TITLES = {
    modes: 'Sportart',
    startKinds: 'Erreichbarkeit',
    diff: 'Schwierigkeit',
    tags: 'Tags',
};

// 2. Deine Logik-Funktionen (1:1 von dir übernommen, nur HIKES zu window.HIKES geändert)
function checkHikeLayout() {
    const container = document.getElementById('hike-results');
    if (!container) return;
    const cardCount = container.querySelectorAll('.hike-card').length;
    if (cardCount > 0 && cardCount < 5) {
        container.classList.add('one-column-mode');
    } else {
        container.classList.remove('one-column-mode');
    }
}

window.segmentEffectiveStats = function (seg) {
    const km = Number(seg.distance_km) || 0;
    const hm = Number(seg.elevation_m) || 0;
    return {
        km: seg.out_and_back ? km * 2 : km,
        hm: hm,
        oneWayKm: km,
    };
};

/**
 * Slider-Maxima: längste gültige Tour (endet an einem Startpunkt).
 * Nur usedSegs — Knoten dürfen auf dem Retourweg erneut betreten werden.
 */
window.maxTourTotalsFromStarts = function (segments) {
    const starts = (window.TOUR_NODES || [])
        .filter(n => n.is_start_candidate || (Array.isArray(n.start_kinds) && n.start_kinds.length))
        .map(n => Number(n.id));
    if (!segments.length) return { km: 1, hm: 1 };

    let maxKm = 0;
    let maxHm = 0;
    segments.forEach(s => {
        const eff = window.segmentEffectiveStats(s);
        maxKm = Math.max(maxKm, eff.km);
        maxHm = Math.max(maxHm, eff.hm);
    });

    if (!starts.length) return { km: maxKm, hm: maxHm };

    const startSet = new Set(starts);
    const { adj } = window.buildSegmentAdj(segments);
    const SAFETY_KM = 200;
    const SAFETY_HM = 20000;

    function dfs(node, km, hm, usedSegs) {
        if (km > 0 && startSet.has(Number(node))) {
            maxKm = Math.max(maxKm, km);
            maxHm = Math.max(maxHm, hm);
        }
        if (km >= SAFETY_KM || hm >= SAFETY_HM) return;

        const edges = adj.get(node) || [];
        for (let i = 0; i < edges.length; i++) {
            const e = edges[i];
            if (usedSegs.has(e.id)) continue;

            const nextKm = km + e.km;
            const nextHm = hm + e.hm;
            if (nextKm > SAFETY_KM || nextHm > SAFETY_HM) continue;

            usedSegs.add(e.id);
            dfs(e.other, nextKm, nextHm, usedSegs);
            usedSegs.delete(e.id);
        }
    }

    starts.forEach(start => dfs(start, 0, 0, new Set()));
    return { km: maxKm, hm: maxHm };
};

window.setupHikeSliders = function () {
    if (window.hikesFiltersInitialized) return;
    if (typeof window.renderModeBudgetSliders === 'function') {
        window.renderModeBudgetSliders();
    }
    window.hikesFiltersInitialized = true;
};

window.snapSlider = function (slider, type, stepSize) {
    let val = parseFloat(slider.value);
    let snappedVal;

    if (type === 'min') {
        snappedVal = Math.floor(val / stepSize) * stepSize;
    } else {
        snappedVal = Math.ceil(val / stepSize) * stepSize;
    }
    slider.value = snappedVal;
    slider.dispatchEvent(new Event('input'));
};

window.initMultiFilters = function () {
    const pool = window.ALL_SEGMENTS || [];
    const allTags = new Set();
    pool.forEach(seg => {
        (seg.modes || []).forEach(m => {
            (m.tags || []).forEach(tag => allTags.add(tag));
        });
    });

    const modeLabels = {};
    const profileModes = (window.TOUR_PROFILE_MODES && typeof window.TOUR_PROFILE_MODES === 'object')
        ? window.TOUR_PROFILE_MODES
        : { hike: 'Wandern', bike: 'Rad', ebike: 'E-Bike' };
    Object.keys(profileModes).forEach((k) => { modeLabels[k] = profileModes[k]; });

    const startLabels = Object.assign({}, window.TOUR_START_KIND_LABELS || {
        ferienwohnung: 'Ferienwohnung',
        bus: 'Bus',
        auto: 'Auto',
    });

    renderFilterOptions('filter-modes', modeLabels, 'modes');
    renderFilterOptions('filter-startKinds', startLabels, 'startKinds');
    renderFilterOptions('filter-diff', filterDefinitions.diff, 'diff');

    const tagObj = {};
    Array.from(allTags).sort().forEach(tag => { tagObj[tag] = capitalize(tag); });
    renderFilterOptions('filter-tags', tagObj, 'tags');

    ['modes', 'startKinds', 'diff', 'tags'].forEach(updateFilterHeader);

    if (!window._toursDdOutsideClose) {
        window._toursDdOutsideClose = true;
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.multi-select-dd')) {
                document.querySelectorAll('.multi-select-dd').forEach(el => el.classList.remove('open'));
            }
        });
    }
};

function filterValueEquals(a, b) {
    return a === b || String(a) === String(b);
}

function renderFilterOptions(elementId, optionsObj, type) {
    const container = document.querySelector(`#${elementId} .dd-list`);
    if (!container) return;
    container.innerHTML = '';

    for (const [key, labelKeyOrString] of Object.entries(optionsObj)) {
        let value;
        if (type === 'tags' || type === 'modes' || type === 'startKinds') {
            value = key;
        } else {
            value = parseInt(key, 10);
        }

        let displayText = '';
        if (type === 'tags') {
            const i18nKey = 'tag_' + labelKeyOrString.toLowerCase();
            displayText = (typeof t === 'function' && t(i18nKey) !== i18nKey) ? t(i18nKey) : labelKeyOrString;
        } else {
            displayText = labelKeyOrString;
        }

        const item = document.createElement('div');
        item.className = 'dd-checkbox-item';
        item.dataset.value = String(value);
        if ((activeFilters[type] || []).some(v => filterValueEquals(v, value))) {
            item.classList.add('checked');
        }
        item.innerHTML = type === 'modes' && typeof window.modeIconHtml === 'function'
            ? `<div class="chk-box"></div>${window.modeIconHtml(value, 'tours-filter-mode-icon')}<span class="tours-filter-mode-label">${displayText}</span>`
            : `<div class="chk-box"></div><span>${displayText}</span>`;

        item.onclick = (e) => {
            e.stopPropagation();
            toggleFilterValue(type, value, item);
        };
        container.appendChild(item);
    }
}

function toggleFilterValue(type, value, domItem) {
    const list = activeFilters[type];
    if (!Array.isArray(list)) return;
    const index = list.findIndex(v => filterValueEquals(v, value));

    if (index === -1) {
        list.push(value);
        domItem.classList.add('checked');
    } else {
        list.splice(index, 1);
        domItem.classList.remove('checked');
    }

    updateFilterHeader(type);
    if (type === 'modes' && typeof window.renderModeBudgetSliders === 'function') {
        window.renderModeBudgetSliders();
    }
    if (typeof window.applyTourGraphFilters === 'function') {
        // Zoom/Ausschnitt behalten
        window.applyTourGraphFilters(false);
    } else {
        window.renderHikes({ updateMap: true, fitBounds: false });
    }
    if (type === 'modes' && typeof window.maybeAutoComputeRoute === 'function') {
        window.maybeAutoComputeRoute();
    }
}

function updateFilterHeader(type) {
    const headerSpan = document.querySelector(`#filter-${type} .dd-header span`);
    if (!headerSpan) return;
    const count = (activeFilters[type] || []).length;
    const baseTitle = FILTER_TITLES[type]
        || ((typeof t === 'function') ? t('filter_' + type) : type);

    if (count > 0) {
        headerSpan.textContent = `${baseTitle} (${count})`;
        headerSpan.style.color = 'var(--accent-soft)';
    } else {
        headerSpan.textContent = `${baseTitle} · alle`;
        headerSpan.style.color = 'var(--text)';
    }
}

function syncFilterItemDom(type) {
    const list = activeFilters[type] || [];
    document.querySelectorAll(`#filter-${type} .dd-checkbox-item`).forEach((item) => {
        const val = item.dataset.value;
        const on = list.some(v => filterValueEquals(v, val) || filterValueEquals(v, parseInt(val, 10)));
        item.classList.toggle('checked', on);
    });
    updateFilterHeader(type);
}

window.toggleMultiSelect = function (id) {
    document.querySelectorAll('.multi-select-dd').forEach(el => {
        if (el.id !== id) el.classList.remove('open');
    });
    const el = document.getElementById(id);
    if (el) el.classList.toggle('open');
}

function capitalize(s) {
    if (!s) return "";
    return s.charAt(0).toUpperCase() + s.slice(1);
}

// =========================================================
// PREMIUM LIGHTBOX LOGIK (Mit 1:1 Swipe-Tracking)
// =========================================================

/** Lightbox mit Bildliste öffnen (Segmente, Knoten, Karten-Panel). */
window.openImageLightbox = function (images, startIndex = 0) {
    const list = (images || []).filter(Boolean);
    if (!list.length) return;

    window.currentLbImages = list;
    window.currentLbIndex = Math.max(0, Math.min(Number(startIndex) || 0, list.length - 1));

    const track = document.getElementById('lightbox-track');
    if (!track) return;

    track.innerHTML = window.currentLbImages.map(src => {
        const fullSrc = window.tourImageUrl(src);
        return `
            <div style="flex: 0 0 100%; height: 100%; display: flex; justify-content: center; align-items: center; padding: 0 15px;">
                <img src="${fullSrc}" draggable="false" style="max-width:100%; max-height:80vh; border-radius:8px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); user-select: none;">
            </div>
        `;
    }).join('');

    updateLightboxView(false);

    const overlay = document.getElementById('lightbox-overlay');
    if (overlay) overlay.style.display = 'flex';
};

window.openHikeGallery = function (hikeId, startIndex = 0) {
    const pool = window.ALL_SEGMENTS || window.ALL_TOURS || window.HIKES || [];
    const hike = pool.find(h => String(h.id) === String(hikeId));
    if (!hike) return;
    const images = hike.images || (hike.image ? [hike.image] : []);
    window.openImageLightbox(images, startIndex);
};

window.changeLbImage = function (step) {
    window.currentLbIndex += step;

    if (window.currentLbIndex >= window.currentLbImages.length) {
        window.currentLbIndex = window.currentLbImages.length - 1;
    }
    if (window.currentLbIndex < 0) {
        window.currentLbIndex = 0;
    }
    updateLightboxView(true);
}

window.updateLightboxView = function (animate = true) {
    const track = document.getElementById('lightbox-track');
    const counter = document.getElementById('lb-counter');

    if (track) {
        track.style.transition = animate ? 'transform 0.3s cubic-bezier(0.25, 1, 0.5, 1)' : 'none';
        track.style.transform = `translateX(-${window.currentLbIndex * 100}%)`;
    }

    if (counter) counter.textContent = `${window.currentLbIndex + 1} / ${window.currentLbImages.length}`;
    if (counter) counter.style.display = window.currentLbImages.length > 1 ? 'block' : 'none';
}


// ==========================================
// 1:1 TOUCH & DRAG LOGIK
// ==========================================
document.addEventListener('DOMContentLoaded', function () {
    const track = document.getElementById('lightbox-track');
    if (!track) return;

    let isDragging = false;
    let startPos = 0;
    let currentTranslate = 0;
    let prevTranslate = 0;

    track.addEventListener('touchstart', touchStart, { passive: true });
    track.addEventListener('touchmove', touchMove, { passive: false });
    track.addEventListener('touchend', touchEnd);

    track.addEventListener('mousedown', touchStart);
    track.addEventListener('mousemove', touchMove);
    track.addEventListener('mouseup', touchEnd);
    track.addEventListener('mouseleave', touchEnd);

    function touchStart(event) {
        isDragging = true;
        window.lightboxHasSwiped = false; // Reset beim ersten Berühren
        startPos = getPositionX(event);

        track.style.transition = 'none';
        const viewportWidth = track.parentElement.clientWidth;
        prevTranslate = -(window.currentLbIndex * viewportWidth);
    }

    function touchMove(event) {
        if (!isDragging) return;

        const currentPosition = getPositionX(event);
        const diff = currentPosition - startPos;

        // Sobald sich der Finger/Maus mehr als 5px bewegt, werten wir es als "Wischen"
        if (Math.abs(diff) > 5) {
            window.lightboxHasSwiped = true;
            if (event.cancelable) event.preventDefault();
        }

        currentTranslate = prevTranslate + diff;

        const viewportWidth = track.parentElement.clientWidth;
        const maxTranslate = -(window.currentLbImages.length - 1) * viewportWidth;

        if (currentTranslate > 0) {
            currentTranslate = diff * 0.3;
        } else if (currentTranslate < maxTranslate) {
            currentTranslate = maxTranslate + ((currentPosition - startPos) * 0.3);
        }

        track.style.transform = `translateX(${currentTranslate}px)`;
    }

    function touchEnd(event) {
        if (!isDragging) return;
        isDragging = false;

        // WICHTIG: Hier schließen wir nichts mehr ab! Das übernimmt der Klick-Event.

        const movedBy = currentTranslate - prevTranslate;
        const viewportWidth = track.parentElement.clientWidth;
        const threshold = viewportWidth * 0.2;

        if (movedBy < -threshold && window.currentLbIndex < window.currentLbImages.length - 1) {
            window.currentLbIndex += 1;
        } else if (movedBy > threshold && window.currentLbIndex > 0) {
            window.currentLbIndex -= 1;
        }

        updateLightboxView(true);

        // Nach einer kurzen Verzögerung den Swipe-Status zurücksetzen
        // (Damit der Klick-Event, der gleich feuert, noch weiß, dass gewischt wurde)
        setTimeout(() => {
            window.lightboxHasSwiped = false;
        }, 50);
    }

    function getPositionX(event) {
        return event.type.includes('mouse') ? event.pageX : event.touches[0].clientX;
    }
});

// ==========================================
// INTELLIGENTES SCHLIESSEN (Verhindert Ghost-Clicks)
// ==========================================
window.lightboxHasSwiped = false; // Globale Variable, die sich merkt, ob gewischt wurde

window.closeLightbox = function (event, forceClose) {
    // 1. Wenn wir gerade gewischt haben, ignoriere den Klick komplett!
    if (window.lightboxHasSwiped) return;

    // 2. Wenn genau auf das Bild geklickt wurde, ignoriere den Klick!
    if (!forceClose && event && event.target && event.target.tagName === 'IMG') return;

    // 3. Andernfalls: Modal schließen
    const overlay = document.getElementById('lightbox-overlay');
    if (overlay) {
        overlay.style.display = 'none';

        // Optional: Video stoppen, falls es ein Video-Modal wäre
        const img = document.getElementById('lightbox-img');
        if (img) img.src = '';
    }
};

window.closeHikeVideo = function (e, force = false) {
    const overlay = document.getElementById('video-overlay');
    if (overlay && (force || e.target === overlay)) {
        const vid = document.getElementById('hike-video-player');
        if (vid) {
            vid.pause();
            vid.currentTime = 0;
            vid.src = "";
        }
        overlay.style.display = 'none';
    }
};

window.playHikeVideo = function (videoFile) {
    const overlay = document.getElementById('video-overlay');
    const vid = document.getElementById('hike-video-player');
    if (overlay && vid) {
        vid.src = videoFile;
        overlay.style.display = 'flex';
        vid.play();
    }
}

window.getAllProfileModes = function () {
    const fromConfig = window.TOUR_PROFILE_MODES && typeof window.TOUR_PROFILE_MODES === 'object'
        ? Object.keys(window.TOUR_PROFILE_MODES)
        : [];
    if (fromConfig.length) return fromConfig;
    return ['hike', 'bike', 'ebike'];
};

window.getActiveModes = function () {
    const modes = activeFilters.modes || [];
    return modes.length ? modes.slice() : window.getAllProfileModes();
};

window.getSelectedStartNodeId = function () {
    return null;
};

window.TOUR_MODE_LABELS = {
    hike: 'Wandern',
    bike: 'Rad',
    ebike: 'E-Bike',
    cable: 'Seilbahn',
};

/** Farben wie Kartenlegende */
window.TOUR_MODE_COLORS = {
    hike: '#3d9b6a',
    bike: '#e76f51',
    ebike: '#4a90d9',
    sled: '#e63946',
    ski: '#457b9d',
    cable: '#1a1a1a',
};
window.TOUR_MULTI_MODE_COLOR = '#8B5E3C';

window.TOUR_START_KIND_LABELS = {
    ferienwohnung: 'Ferienwohnung',
    bus: 'Bus',
    auto: 'Auto',
};

window.getAllStartKinds = function () {
    const fromConfig = window.TOUR_START_KIND_LABELS && typeof window.TOUR_START_KIND_LABELS === 'object'
        ? Object.keys(window.TOUR_START_KIND_LABELS)
        : [];
    if (fromConfig.length) return fromConfig;
    return ['ferienwohnung', 'bus', 'auto'];
};

window.getActiveStartKinds = function () {
    const kinds = activeFilters.startKinds || [];
    return kinds.length ? kinds.slice() : window.getAllStartKinds();
};

window.nodeMatchesStartKinds = function (node, kinds) {
    if (!node) return false;
    const nk = Array.isArray(node.start_kinds) ? node.start_kinds : [];
    if (!kinds || !kinds.length) {
        return !!(node.is_start_candidate || nk.length);
    }
    if (!nk.length) {
        // Legacy: nur Flag, keine Arten → bei jeder Auswahl sichtbar
        return !!node.is_start_candidate;
    }
    return kinds.some(k => nk.includes(k));
};

window.isSeilbahnSegment = function (seg) {
    if (!seg) return false;
    if (seg.is_cable_car) return true;
    const tags = seg.tags || [];
    if (tags.includes('seilbahn') || tags.includes('cable_car')) return true;
    return (seg.modes || []).some(m => {
        const t = m.tags || [];
        return t.includes('seilbahn') || t.includes('cable_car');
    });
};

window.syncStartNodeSelectOptions = function () {
    const el = document.getElementById('filter-start-node');
    if (!el) return;
    const kinds = window.getActiveStartKinds();
    const current = el.value;
    Array.from(el.options).forEach(opt => {
        if (!opt.value) {
            opt.hidden = false;
            return;
        }
        const node = (window.TOUR_NODES || []).find(n => String(n.id) === opt.value);
        const show = window.nodeMatchesStartKinds(node, kinds);
        opt.hidden = !show;
        if (!show && opt.value === current) el.value = '';
    });
};

window.segmentActiveProfiles = function (seg, modes) {
    const selected = modes && modes.length ? modes : window.getActiveModes();
    return (seg.modes || []).filter(m => m.is_active && selected.includes(m.mode));
};

/** Aktive Filter-Modi, die dieses Segment wirklich anbietet */
window.segmentMatchingModes = function (seg, modes) {
    return window.segmentActiveProfiles(seg, modes).map(p => p.mode);
};

/**
 * Kartenfarbe: Seilbahn eigene Farbe; sonst schwarz bei ≥2 Modi, sonst Modusfarbe.
 */
window.segmentMapColorInfo = function (seg, modes) {
    if (window.isSeilbahnSegment(seg)) {
        return { multi: false, mode: 'cable', modes: ['cable'], cable: true };
    }
    const keys = window.segmentMatchingModes(seg, modes);
    if (keys.length >= 2) {
        return { multi: true, mode: null, modes: keys };
    }
    if (keys.length === 1) {
        return { multi: false, mode: keys[0], modes: keys };
    }
    return { multi: false, mode: 'hike', modes: [] };
};

/**
 * Anzeige-Profil: immer das echte Segment-Profil unter den aktiven Filtern —
 * nicht den ersten Checkbox-Modus (sonst wirkt E-Bike wie „Wandern“).
 */
window.segmentDisplayProfile = function (seg, modes) {
    const selected = modes && modes.length ? modes : window.getActiveModes();
    const profiles = window.segmentActiveProfiles(seg, selected);
    if (!profiles.length) return null;
    if (profiles.length === 1) return profiles[0];
    const prefer = ['ebike', 'bike', 'hike'];
    for (let i = 0; i < prefer.length; i++) {
        const m = prefer[i];
        if (!selected.includes(m)) continue;
        const hit = profiles.find(p => p.mode === m);
        if (hit) return hit;
    }
    return profiles[0];
};

window.segmentDisplayMode = function (seg, modes) {
    const p = window.segmentDisplayProfile(seg, modes);
    return (p && p.mode) || 'hike';
};

/** Hütte mit Schlafen — nur mit Checkbox „Hüttenübernachtung“ als Start/Ende */
window.isLodgingHut = function (node) {
    return !!(node && node.offers_lodging);
};

window.getHutOvernightEnabled = function () {
    const el = document.getElementById('filter-hut-overnight');
    return !!(el && el.checked);
};

/**
 * Gültiger Tour-Start/-Ende:
 * — Erreichbarkeit (Wohnung/Bus/Auto) nach Filter
 * — optional Hütten mit Schlafen, wenn „Hüttenübernachtung“ an
 * Highlights allein sind keine Startpunkte.
 */
window.isValidRouteBaseNode = function (node) {
    if (!node) return false;
    const kinds = window.getActiveStartKinds();
    if (window.nodeMatchesStartKinds(node, kinds)) return true;
    if (window.getHutOvernightEnabled() && window.isLodgingHut(node)) return true;
    return false;
};

window.getStartNodeIds = function () {
    const selected = window.getSelectedStartNodeId();
    if (selected) {
        const node = (window.TOUR_NODES || []).find(n => Number(n.id) === Number(selected));
        if (window.isValidRouteBaseNode(node)) return [selected];
    }
    return (window.TOUR_NODES || [])
        .filter(n => window.isValidRouteBaseNode(n))
        .map(n => Number(n.id));
};

/**
 * Tour muss am selben Start enden?
 * Checkbox „Ende darf vom Start abweichen“ steuert die Karten-/Pfadfilterung.
 */
window.getMustEndAtSameStart = function () {
    const el = document.getElementById('filter-end-may-differ');
    if (el) return !el.checked;
    const shape = (window.GUEST_PLAN && window.GUEST_PLAN.tripShape) || 'out_and_back';
    return shape !== 'one_way';
};

/** @deprecated Alias — Touren enden immer an einem Startpunkt */
window.getMustReturnToStart = function () {
    return true;
};

window.buildSegmentAdj = function (segments) {
    const byId = new Map(segments.map(s => [Number(s.id), s]));
    const adj = new Map();
    segments.forEach(seg => {
        const sid = Number(seg.id);
        const a = Number(seg.from_node_id);
        const b = Number(seg.to_node_id);
        const eff = window.segmentEffectiveStats(seg);
        if (!adj.has(a)) adj.set(a, []);
        if (!adj.has(b)) adj.set(b, []);

        if (seg.out_and_back) {
            // Sackgasse: einmal gehen = Hin+Retour, Tip bleibt am Einstieg
            adj.get(a).push({
                id: sid, other: a, km: eff.km, hm: eff.hm,
                out_and_back: true,
            });
            if (a !== b) {
                adj.get(b).push({
                    id: sid, other: b, km: eff.km, hm: eff.hm,
                    out_and_back: true,
                });
            }
            return;
        }

        // Nur Hin-Richtung; Retour ist eigenes Segment
        adj.get(a).push({
            id: sid, other: b, km: eff.km, hm: eff.hm,
            out_and_back: false,
        });
    });
    return { byId, adj };
};

/** Hat das Segment eine Gegenrichtung / Sackgasse? (Pool = sichtbare Segmente) */
window.segmentHasRetour = function (seg, pool) {
    if (!seg) return false;
    if (seg.out_and_back) return true;
    return !!window.findReverseSegment(seg, pool || window.ALL_SEGMENTS || []);
};

window.isWheeledMode = function (mode) {
    return mode === 'bike' || mode === 'ebike';
};

/** Unter aktivem Filter nur Rad/E-Bike → Rückweg mit Rad Pflicht (nicht am Berg lassen). */
window.segmentIsWheeledOnlyUnderFilter = function (seg, modes) {
    const keys = window.segmentMatchingModes(seg, modes);
    if (!keys.length) return false;
    return keys.every(m => window.isWheeledMode(m));
};

/**
 * Rad/E-Bike auf einem Kantenpfad: jede Hin-Fahrt braucht die Gegenrichtung.
 * - modeBySegId: gewählte Sportarten aus dem Routenplaner (auch Multi-Mode → E-Bike)
 * - sonst: nur Segmente, die unter dem Filter rein radfähig sind
 * Retour kann Multi-Mode sein — schließt offene Needs, wenn Profil passt.
 */
window.pathClosesWheeledOnEdgeList = function (edgeIds, byId, pool, modes, modeBySegId) {
    const open = new Map();
    const localPool = pool || [];
    // Retour immer in der vollen Segmentliste suchen (nicht nur gefilterter Pool)
    const allPool = window.ALL_SEGMENTS || localPool;

    for (let i = 0; i < edgeIds.length; i++) {
        const id = Number(edgeIds[i]);
        const seg = (byId && byId.get(id))
            || localPool.find(s => Number(s.id) === id)
            || allPool.find(s => Number(s.id) === id);
        if (!seg || seg.out_and_back) continue;

        const routeMode = modeBySegId ? modeBySegId.get(id) : null;

        // 1) Diese Kante schließt eine offene Retour?
        let closed = false;
        if (routeMode && window.isWheeledMode(routeMode)) {
            const closeKey = id + '|' + routeMode;
            const prev = open.get(closeKey) || 0;
            if (prev > 0) {
                open.set(closeKey, prev - 1);
                if (open.get(closeKey) === 0) open.delete(closeKey);
                closed = true;
            }
        }
        if (!closed) {
            for (const [key, count] of Array.from(open.entries())) {
                if (count <= 0) continue;
                const sep = key.indexOf('|');
                const needId = Number(key.slice(0, sep));
                const needMode = key.slice(sep + 1);
                if (needId !== id) continue;
                const canRide = (window.segmentHasModeProfile && window.segmentHasModeProfile(seg, needMode))
                    || window.segmentMatchingModes(seg, modes).includes(needMode);
                if (!canRide) continue;
                open.set(key, count - 1);
                if (open.get(key) === 0) open.delete(key);
                closed = true;
                break;
            }
        }
        if (closed) continue;

        // 2) Neue Rad-/E-Bike-Hinöffnung
        let mode = routeMode;
        if (!mode || !window.isWheeledMode(mode)) {
            if (!window.segmentIsWheeledOnlyUnderFilter(seg, modes)) continue;
            mode = window.segmentMatchingModes(seg, modes).find(m => window.isWheeledMode(m));
        }
        if (!mode || !window.isWheeledMode(mode)) continue;

        const rev = window.findReverseSegment(seg, allPool);
        if (!rev) return false;
        const needKey = Number(rev.id) + '|' + mode;
        open.set(needKey, (open.get(needKey) || 0) + 1);
    }

    for (const count of open.values()) {
        if (count > 0) return false;
    }
    return true;
};

/** @deprecated Alias — nutzt pathClosesWheeledOnEdgeList ohne Routen-Modes */
window.pathClosesWheeledSegments = function (edgeIds, byId, pool, modes) {
    return window.pathClosesWheeledOnEdgeList(edgeIds, byId, pool, modes, null);
};

/**
 * Offene Rad-/E-Bike-Retouren in der gebauten Route:
 * Map reverseSegmentId → benötigte Sportart (bike|ebike).
 * @param {number|null} excludeStepIndex Step ignorieren (z.B. beim Umschalten)
 */
window.getOpenWheeledReturns = function (excludeStepIndex) {
    const open = new Map();
    const pool = window.ALL_SEGMENTS || [];
    const steps = (window.GUEST_ROUTE && window.GUEST_ROUTE.steps) || [];

    steps.forEach((step, idx) => {
        if (excludeStepIndex != null && idx === excludeStepIndex) return;
        if (!window.isWheeledMode(step.mode)) return;
        const seg = pool.find(s => Number(s.id) === Number(step.segmentId));
        if (!seg || seg.out_and_back) return;
        const rev = window.findReverseSegment(seg, pool);
        if (!rev) return;

        const closeKey = Number(seg.id) + '|' + step.mode;
        const prev = open.get(closeKey) || 0;
        if (prev > 0) {
            open.set(closeKey, prev - 1);
            if (open.get(closeKey) === 0) open.delete(closeKey);
            return;
        }
        const needKey = Number(rev.id) + '|' + step.mode;
        open.set(needKey, (open.get(needKey) || 0) + 1);
    });

    const byRevId = new Map();
    open.forEach((count, key) => {
        if (count <= 0) return;
        const [id, mode] = key.split('|');
        byRevId.set(Number(id), mode);
    });
    return byRevId;
};

/**
 * Filter über Touren (nicht einzelne Segment-km):
 * - Start an Basis-Punkt
 * - Ende am selben Start, außer „Ende darf abweichen“
 * - km/Hm je Sportart gegen Mode-Budgets (nicht Summe aller Modes)
 * - Im Planungsmodus: Filter immer ab Starts (nicht Restbudget am Tip)
 */
window.segmentsOnValidStartPaths = function (segments, startIds, minKm, maxKm, minHm, maxHm, budgets) {
    window._minTourKmBySegId = new Map();
    window._minTourHmBySegId = new Map();

    const sameStartOnly = window.getMustEndAtSameStart();
    const startSet = new Set((startIds || []).map(Number));
    const modes = window.getActiveModes();
    const allSegs = window.ALL_SEGMENTS || [];
    budgets = budgets || {};

    const route = window.GUEST_ROUTE || { startNodeId: null, steps: [] };
    const routeActive = route.startNodeId != null;
    const usingPlanner = !!(window.GUEST_PLAN
        && (window.GUEST_PLAN.startNodeId != null || window.GUEST_PLAN.goalNodeId != null));
    const routeSteps = route.steps || [];
    const modeBySegId = new Map();
    routeSteps.forEach(st => {
        if (st && st.mode) modeBySegId.set(Number(st.segmentId), st.mode);
    });
    const prefixIds = routeSteps.map(st => Number(st.segmentId));

    const pool = (segments || []).slice();
    const poolIds = new Set(pool.map(s => Number(s.id)));
    function ensureInPool(seg) {
        if (!seg) return;
        const id = Number(seg.id);
        if (poolIds.has(id)) return;
        pool.push(seg);
        poolIds.add(id);
    }
    prefixIds.forEach(id => {
        ensureInPool(allSegs.find(s => Number(s.id) === id));
    });
    routeSteps.forEach(st => {
        if (!st || !window.isWheeledMode(st.mode)) return;
        const seg = allSegs.find(s => Number(s.id) === Number(st.segmentId));
        if (!seg || seg.out_and_back) return;
        ensureInPool(window.findReverseSegment(seg, allSegs));
    });

    if (!pool.length) return [];

    const built = window.buildSegmentAdj(pool);
    const adj = built.adj;
    const byId = new Map();
    pool.forEach(s => byId.set(Number(s.id), s));
    const keep = new Set();

    prefixIds.forEach(id => keep.add(id));
    if (typeof window.getOpenWheeledReturns === 'function') {
        window.getOpenWheeledReturns().forEach((_mode, revId) => keep.add(Number(revId)));
    }

    function emptyAcc() {
        const o = {};
        modes.forEach(m => { o[m] = { km: 0, hm: 0 }; });
        return o;
    }

    function cloneAcc(acc) {
        const o = {};
        Object.keys(acc).forEach(m => {
            o[m] = { km: acc[m].km, hm: acc[m].hm };
        });
        return o;
    }

    function accTotals(acc) {
        let km = 0;
        let hm = 0;
        Object.keys(acc).forEach(m => {
            km += acc[m].km;
            hm += acc[m].hm;
        });
        return { km, hm };
    }

    function fitsBudgets(acc) {
        const hasModeBudgets = modes.some(m => budgets[m]);
        if (hasModeBudgets) {
            for (let i = 0; i < modes.length; i++) {
                const m = modes[i];
                const b = budgets[m];
                if (!b) continue;
                const row = acc[m] || { km: 0, hm: 0 };
                if (row.km > b.maxKm + 1e-9) return false;
                if (row.hm > b.maxHm + 1e-9) return false;
            }
            return true;
        }
        const t = accTotals(acc);
        return t.km <= maxKm + 1e-9 && t.hm <= maxHm + 1e-9;
    }

    function pathInRange(acc) {
        if (!fitsBudgets(acc)) return false;
        if (!routeActive || usingPlanner) {
            const t = accTotals(acc);
            if (t.km + 1e-9 < minKm || t.hm + 1e-9 < minHm) return false;
        }
        return true;
    }

    function isValidEnd(node, startId) {
        if (!startSet.has(Number(node))) return false;
        if (sameStartOnly) return Number(node) === Number(startId);
        return true;
    }

    function mark(edgeIds, acc) {
        const t = accTotals(acc);
        edgeIds.forEach(raw => {
            const id = Number(raw);
            keep.add(id);
            const prevKm = window._minTourKmBySegId.get(id);
            if (prevKm == null || t.km < prevKm) {
                window._minTourKmBySegId.set(id, t.km);
                window._minTourHmBySegId.set(id, t.hm);
            }
        });
    }

    function dfs(node, acc, edgeIds, usedSegs, startId) {
        if (edgeIds.length
            && isValidEnd(node, startId)
            && pathInRange(acc)
            && window.pathClosesWheeledOnEdgeList(edgeIds, byId, pool, modes, modeBySegId)) {
            mark(edgeIds, acc);
        }

        const edges = adj.get(Number(node)) || [];
        for (let i = 0; i < edges.length; i++) {
            const e = edges[i];
            const eid = Number(e.id);
            if (usedSegs.has(eid)) continue;

            const seg = byId.get(eid);
            if (!seg) continue;
            let modeKeys = window.segmentMatchingModes(seg, modes);
            if (!modeKeys.length) continue;
            const forced = modeBySegId.get(eid);
            if (forced && modeKeys.includes(forced)) {
                modeKeys = [forced];
            }

            for (let mi = 0; mi < modeKeys.length; mi++) {
                const mode = modeKeys[mi];
                const nextAcc = cloneAcc(acc);
                if (!nextAcc[mode]) nextAcc[mode] = { km: 0, hm: 0 };
                nextAcc[mode].km += e.km;
                nextAcc[mode].hm += e.hm;
                if (!fitsBudgets(nextAcc)) continue;

                usedSegs.add(eid);
                edgeIds.push(eid);
                const prevForced = modeBySegId.get(eid);
                modeBySegId.set(eid, mode);
                dfs(e.other, nextAcc, edgeIds, usedSegs, startId);
                if (prevForced == null) modeBySegId.delete(eid);
                else modeBySegId.set(eid, prevForced);
                edgeIds.pop();
                usedSegs.delete(eid);
            }
        }
    }

    // Planer: immer ab Starts filtern (sonst Restbudget am Tip blendet Vias wie Lichtsee aus)
    if (routeActive && !usingPlanner) {
        const tip = window.getRouteTipNodeId();
        const tot = window.getGuestRouteTotals();
        const routeStart = Number(route.startNodeId);
        startSet.add(routeStart);
        if (tip != null) {
            const startAcc = emptyAcc();
            const byModeRows = typeof window.getGuestRouteTotalsByMode === 'function'
                ? window.getGuestRouteTotalsByMode()
                : [];
            byModeRows.forEach(row => {
                if (!startAcc[row.mode]) startAcc[row.mode] = { km: 0, hm: 0 };
                startAcc[row.mode].km = row.km;
                startAcc[row.mode].hm = row.hm;
            });
            if (!byModeRows.length && (tot.km > 0 || tot.hm > 0)) {
                const fallbackMode = modes[0] || 'hike';
                startAcc[fallbackMode] = { km: tot.km, hm: tot.hm };
            }
            dfs(tip, startAcc, prefixIds.slice(), new Set(prefixIds), routeStart);

            const openRevs = typeof window.getOpenWheeledReturns === 'function'
                ? window.getOpenWheeledReturns()
                : new Map();
            if (openRevs.size) {
                const revIds = new Set();
                openRevs.forEach((_mode, revId) => revIds.add(Number(revId)));
                function dfsDetour(node, acc, edgeIds, usedSegs) {
                    if (edgeIds.length && Number(node) === Number(tip)) {
                        const t = accTotals(acc);
                        if (t.km > 1e-9) mark(edgeIds, acc);
                    }
                    if (!fitsBudgets(acc)) return;
                    const edges = adj.get(Number(node)) || [];
                    for (let i = 0; i < edges.length; i++) {
                        const e = edges[i];
                        const eid = Number(e.id);
                        if (usedSegs.has(eid) || revIds.has(eid)) continue;
                        const seg = byId.get(eid);
                        if (!seg) continue;
                        const modeKeys = window.segmentMatchingModes(seg, modes);
                        for (let mi = 0; mi < modeKeys.length; mi++) {
                            const mode = modeKeys[mi];
                            const nextAcc = cloneAcc(acc);
                            if (!nextAcc[mode]) nextAcc[mode] = { km: 0, hm: 0 };
                            nextAcc[mode].km += e.km;
                            nextAcc[mode].hm += e.hm;
                            if (!fitsBudgets(nextAcc)) continue;
                            usedSegs.add(eid);
                            edgeIds.push(eid);
                            dfsDetour(e.other, nextAcc, edgeIds, usedSegs);
                            edgeIds.pop();
                            usedSegs.delete(eid);
                        }
                    }
                }
                dfsDetour(tip, emptyAcc(), [], new Set(prefixIds));
            }
        }
    } else if (startSet.size) {
        startSet.forEach(start => {
            dfs(start, emptyAcc(), [], new Set(), start);
        });
    }

    return pool.filter(s => keep.has(Number(s.id)));
};

// =========================================================
// PROBE-ROUTENBAU (Segmente antippen)
// =========================================================

window.GUEST_ROUTE = { startNodeId: null, steps: [] };
window._pendingRouteAdd = null;

window.getRouteTipNodeId = function () {
    const r = window.GUEST_ROUTE;
    if (!r || r.startNodeId == null) return null;
    if (!r.steps.length) return Number(r.startNodeId);
    return Number(r.steps[r.steps.length - 1].toNodeId);
};

window.getGuestRouteSegmentIds = function () {
    return new Set((window.GUEST_ROUTE.steps || []).map(s => Number(s.segmentId)));
};

/**
 * Mögliche Routen-Aktion für ein Segment im Panel.
 * @param {object} seg
 * @param {{ canConnect?: boolean, connectIds?: Set<number> }} [opts]
 *   canConnect/connectIds von der Karte — ohne anschließbares Segment kein „Zur Route hinzufügen“.
 * @returns {{ type: 'add'|'return', label: string, targetSeg: object }|null}
 */
window.getSegmentRouteAction = function (seg, opts) {
    if (!seg) return null;
    opts = opts || {};
    const pool = window.ALL_SEGMENTS || [];
    const tip = window.getRouteTipNodeId();
    const r = window.GUEST_ROUTE || { startNodeId: null, steps: [] };
    const last = r.steps.length ? r.steps[r.steps.length - 1] : null;
    const lastSeg = last
        ? pool.find(s => Number(s.id) === Number(last.segmentId))
        : null;
    const revOfLast = lastSeg ? window.findReverseSegment(lastSeg, pool) : null;
    const revOfClicked = window.findReverseSegment(seg, pool);
    const used = window.getGuestRouteSegmentIds();
    const modes = typeof window.getActiveModes === 'function'
        ? window.getActiveModes()
        : [];

    // Rückweg: letztes Segment (oder sein Zwilling) gewählt und Retour am Tip möglich
    if (last && revOfLast && tip != null && !used.has(Number(revOfLast.id))) {
        const isPair = Number(seg.id) === Number(last.segmentId)
            || Number(seg.id) === Number(revOfLast.id)
            || (revOfClicked && Number(revOfClicked.id) === Number(last.segmentId));
        if (isPair && window.buildRouteStepGeometry(revOfLast, tip)) {
            return {
                type: 'return',
                label: 'Rückweg hinzufügen',
                targetSeg: revOfLast,
            };
        }
    }

    // Noch kein Start: nur wenn man vom Startknoten wirklich auf dieses Segment kann
    if (tip == null) {
        const starts = new Set((window.getStartNodeIds() || []).map(Number));
        const a = Number(seg.from_node_id);
        const b = Number(seg.to_node_id);
        const tryStart = (startAt, target) => {
            if (startAt == null || !target) return null;
            if (!window.buildRouteStepGeometry(target, startAt)) return null;
            if (!window.segmentMatchingModes(target, modes).length) return null;
            return {
                type: 'add',
                label: 'Zur Route hinzufügen',
                targetSeg: target,
            };
        };
        if (starts.has(a)) {
            const hit = tryStart(a, seg);
            if (hit) return hit;
        }
        if (starts.has(b)) {
            if (seg.out_and_back || (window.isSeilbahnSegment && window.isSeilbahnSegment(seg))) {
                const hit = tryStart(b, seg);
                if (hit) return hit;
            }
            if (revOfClicked) {
                const hit = tryStart(b, revOfClicked);
                if (hit) return hit;
            }
        }
        return null;
    }

    // Mit aktiver Route: nur anschließbare Segmente — nicht die ausgegrauten.
    // (Kein Umweg über die Retour-Zwilling-ID: sonst erscheint der Button auf grauen Linien.)
    if (!opts.canConnect) return null;
    if (opts.connectIds && !opts.connectIds.has(Number(seg.id))) return null;

    let resolved = typeof window.resolveSegmentForRouteClick === 'function'
        ? window.resolveSegmentForRouteClick(seg)
        : null;
    if (!resolved && window.buildRouteStepGeometry(seg, tip) && !used.has(Number(seg.id))) {
        resolved = seg;
    }
    if (!resolved || !window.buildRouteStepGeometry(resolved, tip)) return null;
    if (used.has(Number(resolved.id))) return null;
    if (!window.segmentMatchingModes(resolved, modes).length) return null;

    if (opts.connectIds && !opts.connectIds.has(Number(resolved.id))) return null;

    if (revOfLast && Number(resolved.id) === Number(revOfLast.id)) {
        return {
            type: 'return',
            label: 'Rückweg hinzufügen',
            targetSeg: resolved,
        };
    }
    return {
        type: 'add',
        label: 'Zur Route hinzufügen',
        targetSeg: resolved,
    };
};

/** Start / Ziel / Via für den Planungsmodus. */
window.getNodeRouteAction = function (node) {
    if (!node) return null;
    const id = Number(node.id);
    const plan = window.GUEST_PLAN || {};
    const isBase = typeof window.isValidRouteBaseNode === 'function' && window.isValidRouteBaseNode(node);
    const isHighlight = !!node.is_highlight;

    return {
        type: 'planner',
        label: isBase ? 'Als Start' : (isHighlight ? 'Als Zwischenziel' : 'Als Ziel'),
        targetNode: node,
        canStart: isBase || !!node.is_start_candidate,
        canGoal: true,
        canVia: isHighlight || !isBase,
        nodeId: id,
        isStart: Number(plan.startNodeId) === id,
        isGoal: Number(plan.goalNodeId) === id,
        isVia: (plan.vias || []).some(v => Number(v) === id),
    };
};

/** Gegenrichtung: Blankasee→Abzw zu Abzw→Blankasee (und umgekehrt). */
window.findReverseSegment = function (seg, segments) {
    if (!seg) return null;
    const a = Number(seg.from_node_id);
    const b = Number(seg.to_node_id);
    const pool = segments || window.ALL_SEGMENTS || [];
    return pool.find(s =>
        Number(s.id) !== Number(seg.id)
        && Number(s.from_node_id) === b
        && Number(s.to_node_id) === a
    ) || null;
};

/**
 * Klick auf überlappende Hin/Retour-Linie: unbenutzte, vom Tip aus anschließbare
 * Variante bevorzugen (Retour), sonst die getroffene.
 */
window.resolveSegmentForRouteClick = function (seg) {
    if (!seg) return null;
    const tip = window.getRouteTipNodeId();
    const used = window.getGuestRouteSegmentIds();
    const pool = window.ALL_SEGMENTS || [];
    const twins = [seg];
    const rev = window.findReverseSegment(seg, pool);
    if (rev) twins.push(rev);

    const usable = twins.filter(s => {
        if (used.has(Number(s.id))) return false;
        if (tip == null) return true;
        return !!window.buildRouteStepGeometry(s, tip);
    });

    if (!usable.length) return null;
    // Am Tip anschließbare Retour vor bereits genutzter Hin-Geometrie
    if (tip != null) {
        const fromTip = usable.find(s => Number(s.from_node_id) === tip)
            || usable.find(s => {
                if (Number(s.to_node_id) !== tip) return false;
                return !!(s.out_and_back || (window.isSeilbahnSegment && window.isSeilbahnSegment(s)));
            });
        if (fromTip) return fromTip;
    }
    return usable[0];
};

window.getConnectableSegmentIds = function (segments) {
    const tip = window.getRouteTipNodeId();
    const ids = new Set();
    if (tip == null) return ids;
    const used = window.getGuestRouteSegmentIds();
    (segments || []).forEach(seg => {
        if (used.has(Number(seg.id))) return;
        if (window.buildRouteStepGeometry(seg, tip)) {
            ids.add(Number(seg.id));
        }
    });
    return ids;
};

window.segmentAscentDescent = function (seg) {
    if (!seg) return { ascent: 0, descent: 0 };
    const ascent = Math.max(0, Math.round(Number(seg.elevation_m) || 0));
    if (seg.out_and_back) {
        // Hin+Retour auf derselben Spur: Aufstieg und Abstieg jeweils einmal
        return { ascent, descent: ascent };
    }
    const rev = typeof window.findReverseSegment === 'function'
        ? window.findReverseSegment(seg, window.ALL_SEGMENTS || [])
        : null;
    const descent = rev ? Math.max(0, Math.round(Number(rev.elevation_m) || 0)) : 0;
    return { ascent, descent };
};

window.formatHmUpDown = function (ascent, descent) {
    const up = Math.max(0, Math.round(Number(ascent) || 0));
    const down = Math.max(0, Math.round(Number(descent) || 0));
    return '↑ ' + up + ' Hm · ↓ ' + down + ' Hm';
};

window.getGuestRouteTotals = function () {
    let km = 0;
    let hmUp = 0;
    let hmDown = 0;
    (window.GUEST_ROUTE.steps || []).forEach(step => {
        const seg = (window.ALL_SEGMENTS || []).find(s => Number(s.id) === Number(step.segmentId));
        if (!seg) return;
        const eff = window.segmentEffectiveStats(seg);
        km += eff.km;
        const ad = window.segmentAscentDescent(seg);
        // out_and_back in effectiveStats already doubles km/hm; ascentDescent already both sides
        if (seg.out_and_back) {
            hmUp += ad.ascent;
            hmDown += ad.descent;
        } else {
            hmUp += ad.ascent;
            hmDown += ad.descent;
        }
    });
    return {
        km,
        hm: hmUp,
        hmUp,
        hmDown,
        count: (window.GUEST_ROUTE.steps || []).length,
    };
};

/** km/Hm je Sportart in der gebauten Route (Reihenfolge: erste Nutzung). */
window.getGuestRouteTotalsByMode = function () {
    const byMode = new Map();
    const order = [];
    (window.GUEST_ROUTE.steps || []).forEach(step => {
        const mode = step.mode || 'hike';
        const seg = (window.ALL_SEGMENTS || []).find(s => Number(s.id) === Number(step.segmentId));
        if (!seg) return;
        const eff = window.segmentEffectiveStats(seg);
        const ad = window.segmentAscentDescent(seg);
        if (!byMode.has(mode)) {
            byMode.set(mode, { mode, km: 0, hm: 0, hmUp: 0, hmDown: 0, count: 0 });
            order.push(mode);
        }
        const row = byMode.get(mode);
        row.km += eff.km;
        row.hmUp += ad.ascent;
        row.hmDown += ad.descent;
        row.hm = row.hmUp;
        row.count += 1;
    });
    return order.map(m => byMode.get(m));
};

window.hideRouteModeChooser = function () {
    window._pendingRouteAdd = null;
    const box = document.getElementById('tours-mode-chooser');
    if (box) box.hidden = true;
};

window.isRouteModeChooserOpen = function () {
    const box = document.getElementById('tours-mode-chooser');
    return !!(box && !box.hidden);
};

window.segmentHasModeProfile = function (seg, mode) {
    if (!seg || !mode) return false;
    return (seg.modes || []).some(m =>
        m && m.mode === mode && m.is_active !== false && m.is_active !== 0 && m.is_active !== '0'
    );
};

window.showRouteModeChooser = function (title, modeKeys, onPick) {
    const box = document.getElementById('tours-mode-chooser');
    const titleEl = document.getElementById('tours-mode-chooser-title');
    const btns = document.getElementById('tours-mode-chooser-btns');
    if (!box || !btns) {
        if (modeKeys.length) onPick(modeKeys[0]);
        return;
    }
    if (titleEl) titleEl.textContent = title || 'Sportart wählen';
    btns.innerHTML = modeKeys.map(mode => {
        const label = window.TOUR_MODE_LABELS[mode] || mode;
        const color = (window.TOUR_MODE_COLORS && window.TOUR_MODE_COLORS[mode]) || '#3d9b6a';
        const icon = typeof window.modeIconHtml === 'function'
            ? window.modeIconHtml(mode, 'tours-mode-pick-icon')
            : '';
        return `<button type="button" class="tours-mode-pick" data-mode="${mode}" title="${label}" aria-label="${label}" style="--mode-color:${color}">${icon}</button>`;
    }).join('');
    btns.querySelectorAll('.tours-mode-pick').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const mode = btn.getAttribute('data-mode');
            window.hideRouteModeChooser();
            onPick(mode);
        });
    });
    box.hidden = false;
};

window.commitGuestRouteStep = function (step) {
    window.GUEST_ROUTE.steps.push(step);
    window.syncGuestRouteUi();
    if (typeof window.renderHikes === 'function') {
        window.renderHikes({ updateMap: true, fitBounds: false });
    }
};

window.getPairedRouteStepIndices = function (stepIndex) {
    const steps = (window.GUEST_ROUTE && window.GUEST_ROUTE.steps) || [];
    const step = steps[stepIndex];
    if (!step) return [];
    const pool = window.ALL_SEGMENTS || [];
    const seg = pool.find(s => Number(s.id) === Number(step.segmentId));
    const indices = [stepIndex];
    if (!seg || seg.out_and_back) return indices;
    const rev = window.findReverseSegment(seg, pool);
    if (!rev) return indices;
    steps.forEach((s, i) => {
        if (i !== stepIndex && Number(s.segmentId) === Number(rev.id)) {
            indices.push(i);
        }
    });
    return indices;
};

window.changeGuestRouteStepMode = function (stepIndex, mode) {
    if (!mode) return;
    const steps = (window.GUEST_ROUTE && window.GUEST_ROUTE.steps) || [];
    const pool = window.ALL_SEGMENTS || [];
    const indices = window.getPairedRouteStepIndices(stepIndex);
    indices.forEach(i => {
        const st = steps[i];
        if (!st) return;
        const seg = pool.find(s => Number(s.id) === Number(st.segmentId));
        if (!seg) return;
        const ok = (window.segmentHasModeProfile && window.segmentHasModeProfile(seg, mode))
            || (window.segmentMatchingModes(seg, [mode]).length > 0);
        if (ok) st.mode = mode;
    });
    if (!window.GUEST_PLAN) window.GUEST_PLAN = {};
    window.GUEST_PLAN.preferredMode = mode;
    window.syncGuestRouteUi();
    if (typeof window.renderHikes === 'function') {
        window.renderHikes({ updateMap: true, fitBounds: false });
    }
};

window.routeStepEndNodeId = function (step, seg) {
    if (!step) return null;
    if (seg && seg.out_and_back) {
        const a = Number(seg.from_node_id);
        const b = Number(seg.to_node_id);
        const tip = Number(step.fromNodeId);
        return tip === a ? b : a;
    }
    return Number(step.toNodeId);
};

window.syncGuestRouteUi = function () {
    const hint = document.getElementById('tours-route-hint');
    const stats = document.getElementById('tours-route-stats');
    const startEl = document.getElementById('tours-route-start');
    const list = document.getElementById('tours-route-steps');
    const r = window.GUEST_ROUTE;
    const nodes = window.TOUR_NODES || [];
    const start = nodes.find(n => Number(n.id) === Number(r.startNodeId));
    const activeModes = window.getActiveModes();

    if (hint) {
        const plan = window.GUEST_PLAN || {};
        const openWheeled = window.getOpenWheeledReturns();
        if (plan.lastHint) {
            hint.textContent = plan.lastHint;
        } else if (!plan.startNodeId && !r.startNodeId) {
            hint.textContent = 'Start und Ziel auf der Karte tippen — Route wird berechnet';
        } else if (!plan.goalNodeId) {
            hint.textContent = 'Jetzt Ziel tippen (oder Highlight als Zwischenziel)';
        } else if (!r.steps.length) {
            hint.textContent = '„Route berechnen“ oder Fahrtform wählen';
        } else if (openWheeled.size) {
            const firstRevId = openWheeled.keys().next().value;
            const needMode = openWheeled.get(firstRevId);
            const modeLabel = window.TOUR_MODE_LABELS[needMode] || needMode;
            hint.textContent = `Rad/E-Bike noch offen (${modeLabel}) — Fahrtform prüfen`;
        } else {
            const tipId = window.getRouteTipNodeId();
            const tip = nodes.find(n => Number(n.id) === tipId);
            hint.textContent = `Route fertig · Ende: ${tip ? tip.name : 'Punkt'}`;
        }
    }

    const tot = window.getGuestRouteTotals();
    if (typeof window.syncTakeawayButton === 'function') window.syncTakeawayButton();
    if (stats) {
        if (!tot.count) {
            stats.innerHTML = '';
        } else {
            const byMode = window.getGuestRouteTotalsByMode();
            const dur = typeof window.getGuestRouteDurationMin === 'function'
                ? window.getGuestRouteDurationMin()
                : 0;
            const durLabel = typeof window.formatDurationMin === 'function'
                ? window.formatDurationMin(dur)
                : (dur + ' min');
            const modeRows = byMode.map(row => {
                const label = window.TOUR_MODE_LABELS[row.mode] || row.mode;
                const color = (window.TOUR_MODE_COLORS && window.TOUR_MODE_COLORS[row.mode]) || '#3d9b6a';
                const icon = typeof window.modeIconHtml === 'function'
                    ? window.modeIconHtml(row.mode, 'tours-route-mode-stat-icon')
                    : '';
                const hmPart = (row.hmUp > 0 || row.hmDown > 0)
                    ? ` · ${window.formatHmUpDown(row.hmUp, row.hmDown)}`
                    : '';
                return `<div class="tours-route-mode-stat" style="--mode-color:${color}" title="${label}">`
                    + (icon || `<span class="tours-route-mode-stat-dot" aria-hidden="true"></span>`)
                    + `<span class="tours-route-mode-stat-vals">${row.km.toFixed(1)} km${hmPart}</span>`
                    + `</div>`;
            }).join('');
            const hmTotal = (tot.hmUp > 0 || tot.hmDown > 0)
                ? window.formatHmUpDown(tot.hmUp, tot.hmDown)
                : (Math.round(tot.hm) + ' Hm');
            stats.innerHTML =
                `<div class="tours-route-stats-total">`
                + `${tot.count} Segment${tot.count === 1 ? '' : 'e'} · ${tot.km.toFixed(1)} km · ${hmTotal}`
                + ` · ≈ ${durLabel}`
                + `</div>`
                + `<div class="tours-route-stats-modes">${modeRows}</div>`;
        }
    }

    if (startEl) {
        if (r.startNodeId) {
            startEl.hidden = false;
            startEl.innerHTML =
                `<span class="tours-route-start-label">Start</span>`
                + `<strong>${start ? start.name : ('#' + r.startNodeId)}</strong>`;
        } else {
            startEl.hidden = true;
            startEl.innerHTML = '';
        }
    }

    if (list) {
        list.innerHTML = (r.steps || []).map((step, i) => {
            const seg = (window.ALL_SEGMENTS || []).find(s => s.id === step.segmentId);
            const endId = window.routeStepEndNodeId(step, seg);
            const endNode = nodes.find(n => Number(n.id) === Number(endId));
            const endName = endNode ? endNode.name : ('Punkt #' + endId);
            const note = (seg && seg.out_and_back && !step.deadEndSpur)
                ? '<span class="tours-route-step-note">Hin+Retour</span>'
                : '';
            const isCable = seg && window.isSeilbahnSegment && window.isSeilbahnSegment(seg);
            const displayMode = isCable ? 'cable' : (step.mode || 'hike');
            const modeLabel = window.TOUR_MODE_LABELS[displayMode] || displayMode || '';
            const modeColor = (window.TOUR_MODE_COLORS && window.TOUR_MODE_COLORS[displayMode]) || '#3d9b6a';
            const modeIcon = typeof window.modeIconHtml === 'function'
                ? window.modeIconHtml(displayMode, 'tours-route-mode-icon')
                : '';
            const altModes = seg ? window.segmentMatchingModes(seg, activeModes) : [];
            // Paar Hin+Retour: gemeinsame Modes anbieten (kein Einzel-Zwang mehr)
            const pairIdx = window.getPairedRouteStepIndices(i);
            let modeKeys = altModes.slice();
            if (pairIdx.length > 1) {
                const other = r.steps[pairIdx.find(x => x !== i)];
                const otherSeg = other
                    ? (window.ALL_SEGMENTS || []).find(s => Number(s.id) === Number(other.segmentId))
                    : null;
                if (otherSeg) {
                    const otherModes = window.segmentMatchingModes(otherSeg, activeModes);
                    modeKeys = modeKeys.filter(m => otherModes.includes(m));
                }
            }
            const canSwitch = modeKeys.length > 1;
            const modeBtn = canSwitch
                ? `<button type="button" class="tours-route-mode-btn" data-step="${i}" title="${modeLabel}" aria-label="${modeLabel}" style="--mode-color:${modeColor}">${modeIcon}</button>`
                : `<span class="tours-route-mode-tag" title="${modeLabel}" style="--mode-color:${modeColor}">${modeIcon}</span>`;
            return `<li class="tours-route-step">`
                + `<div class="tours-route-step-name">`
                + `<span class="tours-route-arrow" aria-hidden="true">→</span>`
                + `<span class="tours-route-end">${endName}</span>`
                + note
                + `</div>`
                + `<div class="tours-route-step-mode">${modeBtn}</div>`
                + `</li>`;
        }).join('');

        list.querySelectorAll('.tours-route-mode-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const idx = parseInt(btn.getAttribute('data-step'), 10);
                const step = r.steps[idx];
                const seg = (window.ALL_SEGMENTS || []).find(s => s.id === step.segmentId);
                if (!seg) return;
                let modeKeys = window.segmentMatchingModes(seg, activeModes);
                const pairIdx = window.getPairedRouteStepIndices(idx);
                if (pairIdx.length > 1) {
                    const other = r.steps[pairIdx.find(x => x !== idx)];
                    const otherSeg = other
                        ? (window.ALL_SEGMENTS || []).find(s => Number(s.id) === Number(other.segmentId))
                        : null;
                    if (otherSeg) {
                        const otherModes = window.segmentMatchingModes(otherSeg, activeModes);
                        modeKeys = modeKeys.filter(m => otherModes.includes(m));
                    }
                }
                if (!modeKeys.length) modeKeys = [step.mode].filter(Boolean);
                window.showRouteModeChooser(
                    'Sportart wählen (Hin+Retour)',
                    modeKeys,
                    (mode) => window.changeGuestRouteStepMode(idx, mode)
                );
            });
        });
    }

    if (typeof window.syncPlannerUi === 'function') {
        window.syncPlannerUi();
    }
};

window.setGuestRouteStart = function (nodeId, redraw) {
    window.hideRouteModeChooser();
    if (typeof window.setPlannerStart === 'function') {
        window.setPlannerStart(nodeId);
        return;
    }
    window.GUEST_ROUTE = { startNodeId: Number(nodeId), steps: [] };
    window.syncGuestRouteUi();
    if (redraw !== false && typeof window.renderHikes === 'function') {
        window.renderHikes({ updateMap: true, fitBounds: false });
    }
};

window.clearGuestRoute = function () {
    window.hideRouteModeChooser();
    if (window.GUEST_PLAN) {
        window.GUEST_PLAN.loadedSavedId = null;
    }
    if (typeof window.setLoadedRouteGlow === 'function') {
        window.setLoadedRouteGlow(false);
    }
    if (typeof window.clearPlanner === 'function') {
        window.clearPlanner();
        return;
    }
    window.GUEST_ROUTE = { startNodeId: null, steps: [] };
    window.syncGuestRouteUi();
    if (typeof window.renderHikes === 'function') {
        window.renderHikes({ updateMap: true, fitBounds: false });
    }
};

window.undoGuestRouteStep = function () {
    window.hideRouteModeChooser();
    const r = window.GUEST_ROUTE;
    if (!r.steps.length) {
        if (r.startNodeId != null) {
            r.startNodeId = null;
        }
    } else {
        r.steps.pop();
    }
    window.syncGuestRouteUi();
    if (typeof window.renderHikes === 'function') {
        window.renderHikes({ updateMap: true, fitBounds: false });
    }
};

window.onGuestRouteNodeClick = function (node) {
    if (!node) return;
    // Planungsmodus: Start an Basis, sonst Ziel (Highlights → Via wenn schon Start+Ziel)
    if (typeof window.setPlannerStart === 'function') {
        const plan = window.GUEST_PLAN || {};
        const isBase = window.isValidRouteBaseNode(node);
        if (isBase && plan.startNodeId == null) {
            window.setPlannerStart(node.id);
            return;
        }
        if (plan.goalNodeId == null) {
            window.setPlannerGoal(node.id);
            return;
        }
        if (node.is_highlight || !isBase) {
            window.addPlannerVia(node.id);
            return;
        }
        // Zweiter Basis-Knoten → neues Ziel
        window.setPlannerGoal(node.id);
        return;
    }
    if (window.isValidRouteBaseNode(node)) {
        window.setGuestRouteStart(node.id, true);
        return;
    }
    if (Number(node.id) === window.getRouteTipNodeId()) return;
    const hint = document.getElementById('tours-route-hint');
    if (hint && !window.GUEST_ROUTE.startNodeId) {
        hint.textContent = window.getHutOvernightEnabled()
            ? 'Start: Wohnung / Bus / Auto oder Hütte mit Schlafen'
            : 'Start: Wohnung / Bus / Auto (Highlights sind keine Startpunkte)';
    }
};

window.buildRouteStepGeometry = function (seg, tip) {
    const a = Number(seg.from_node_id);
    const b = Number(seg.to_node_id);
    const sid = Number(seg.id);
    if (seg.out_and_back) {
        if (tip !== a && tip !== b) return null;
        return {
            segmentId: sid,
            fromNodeId: tip,
            toNodeId: tip,
            reversed: tip === b,
        };
    }
    if (tip === a) {
        return { segmentId: sid, fromNodeId: a, toNodeId: b, reversed: false };
    }
    // Seilbahn: gleiche Kabine bergauf/bergab, bis Retour-Segment existiert
    if (tip === b && window.isSeilbahnSegment && window.isSeilbahnSegment(seg)) {
        return { segmentId: sid, fromNodeId: b, toNodeId: a, reversed: true };
    }
    return null;
};

window.onGuestRouteSegmentClick = function (seg) {
    if (!seg) return;
    // Kein zweiter Klick während Sportart-Auswahl
    if (window.isRouteModeChooserOpen()) return;

    const r = window.GUEST_ROUTE;
    let a = Number(seg.from_node_id);
    let b = Number(seg.to_node_id);

    let tip = window.getRouteTipNodeId();

    if (tip == null) {
        const starts = new Set(window.getStartNodeIds());
        let startAt = null;
        if (starts.has(a)) startAt = a;
        else if (starts.has(b)) startAt = b;
        if (startAt == null) {
            window.syncGuestRouteUi();
            const hint = document.getElementById('tours-route-hint');
            if (hint) {
                hint.textContent = 'Zuerst einen Start tippen (Wohnung / Bus / Auto'
                    + (window.getHutOvernightEnabled() ? ' / Hütte)' : ')');
            }
            return;
        }
        r.startNodeId = startAt;
        r.steps = [];
        tip = startAt;
    }

    // Hin/Retour liegen oft deckungsgleich: 2. Klick aufs Paar = Retour (z. B. Doppelklick)
    tip = window.getRouteTipNodeId();
    const pool = window.ALL_SEGMENTS || [];
    const last = r.steps.length ? r.steps[r.steps.length - 1] : null;
    const lastSeg = last
        ? pool.find(s => Number(s.id) === Number(last.segmentId))
        : null;
    const revOfLast = lastSeg ? window.findReverseSegment(lastSeg, pool) : null;
    const openWheeled = window.getOpenWheeledReturns();

    let resolved = window.resolveSegmentForRouteClick(seg);
    if (revOfLast && tip != null && window.buildRouteStepGeometry(revOfLast, tip)) {
        const clickedRev = window.findReverseSegment(seg, pool);
        const clickedIsPair = Number(seg.id) === Number(last.segmentId)
            || Number(seg.id) === Number(revOfLast.id)
            || (clickedRev && Number(clickedRev.id) === Number(last.segmentId));
        if (clickedIsPair) {
            resolved = revOfLast;
        }
    }

    if (resolved) {
        seg = resolved;
    } else if (r.steps.length) {
        const rev = window.findReverseSegment(seg, pool);
        const isLastPair = Number(last.segmentId) === Number(seg.id)
            || (rev && Number(last.segmentId) === Number(rev.id));
        if (isLastPair) {
            const modeKeys = window.segmentMatchingModes(lastSeg, window.getActiveModes());
            if (modeKeys.length > 1) {
                window.showRouteModeChooser(
                    'Sportart wählen',
                    modeKeys,
                    (mode) => window.changeGuestRouteStepMode(r.steps.length - 1, mode)
                );
            } else {
                window.undoGuestRouteStep();
            }
            return;
        }
        const hint = document.getElementById('tours-route-hint');
        if (hint) hint.textContent = 'Dieses Segment hängt nicht am aktuellen Punkt';
        return;
    } else {
        const hint = document.getElementById('tours-route-hint');
        if (hint) hint.textContent = 'Dieses Segment hängt nicht am aktuellen Punkt';
        return;
    }

    tip = window.getRouteTipNodeId();
    const geom = window.buildRouteStepGeometry(seg, tip);
    if (!geom) {
        const hint = document.getElementById('tours-route-hint');
        if (hint) hint.textContent = 'Dieses Segment hängt nicht am aktuellen Punkt';
        return;
    }

    const modeKeys = window.segmentMatchingModes(seg, window.getActiveModes());
    if (!modeKeys.length) {
        const hint = document.getElementById('tours-route-hint');
        if (hint) hint.textContent = 'Keine passende Sportart für dieses Segment';
        return;
    }

    const finish = (mode) => {
        window.commitGuestRouteStep({ ...geom, mode });
    };

    const isRetourOfLast = !!(last && revOfLast && Number(seg.id) === Number(revOfLast.id));

    // Retour des letzten Schritts → dieselbe Sportart (E-Bike→E-Bike)
    if (isRetourOfLast && last.mode) {
        const same = last.mode;
        if (modeKeys.includes(same) || window.segmentHasModeProfile(seg, same) || window.isWheeledMode(same)) {
            finish(same);
            return;
        }
    }

    const requiredMode = openWheeled.get(Number(seg.id)) || null;
    if (requiredMode) {
        finish(requiredMode);
        return;
    }

    if (modeKeys.length === 1) {
        finish(modeKeys[0]);
        return;
    }

    window._pendingRouteAdd = { seg, geom };
    window.showRouteModeChooser('Sportart wählen', modeKeys, finish);
};

/**
 * Filter:
 * 1) Modus-Profil aktiv
 * 2) Schwierigkeit / Tags
 * 3) Segment in Mode-Budget (Max) als Soft-Gate
 * 4) Pfad Start→Ende mit km/Hm je Sportart; Ende = Start außer Checkbox
 */
window.getFilteredSegments = function () {
    const modes = window.getActiveModes();
    const budgets = typeof window.getModeBudgets === 'function' ? window.getModeBudgets() : {};
    const pool = window.ALL_SEGMENTS || [];

    const hard = pool.filter(seg => {
        const profiles = window.segmentActiveProfiles(seg, modes);
        if (!profiles.length) return false;

        if (activeFilters.diff.length > 0
            && !profiles.some(p => activeFilters.diff.includes(p.difficulty))) {
            return false;
        }
        if (activeFilters.tags.length > 0) {
            const ok = profiles.some(p =>
                (p.tags || []).some(t => activeFilters.tags.includes(t))
            );
            if (!ok) return false;
        }

        const eff = window.segmentEffectiveStats(seg);
        const fitsBudget = profiles.some(p => {
            const b = budgets[p.mode];
            if (!b) return true;
            return eff.km <= b.maxKm + 1e-6 && eff.hm <= b.maxHm + 1e-6;
        });
        return fitsBudget;
    });

    // Legacy-Max nur Fallback; echte Limits laufen über budgets je Mode
    let maxDist = 0;
    let maxAlt = 0;
    modes.forEach(m => {
        const b = budgets[m];
        if (!b) return;
        maxDist = Math.max(maxDist, Number(b.maxKm) || 0);
        maxAlt = Math.max(maxAlt, Number(b.maxHm) || 0);
    });
    if (!Number.isFinite(maxDist) || maxDist <= 0) maxDist = 100;
    if (!Number.isFinite(maxAlt) || maxAlt <= 0) maxAlt = 5000;

    let minDist = 0;
    let minAlt = 0;
    modes.forEach(m => {
        const b = budgets[m];
        if (!b) return;
        minDist = Math.max(minDist, Number(b.minKm) || 0);
        minAlt = Math.max(minAlt, Number(b.minHm) || 0);
    });

    const startIds = window.getStartNodeIds();
    const ranged = typeof window.segmentsOnValidStartPaths === 'function'
        ? window.segmentsOnValidStartPaths(hard, startIds, minDist, maxDist, minAlt, maxAlt, budgets)
        : hard;

    ranged.sort((a, b) => Number(b.is_highlight) - Number(a.is_highlight));
    return ranged;
};

window.getFilteredTours = function () {
    return window.getFilteredSegments();
};

window.renderHikes = function (options) {
    const opts = options || {};
    const updateMap = opts.updateMap !== false;
    const fitBounds = opts.fitBounds === true;

    const container = document.getElementById('hike-results');
    const counterEl = document.getElementById('hike-counter');
    if (!container) return;

    const filtered = window.getFilteredSegments();
    const modes = window.getActiveModes();
    const startId = window.getSelectedStartNodeId();

    const mapEl = document.getElementById('tours-overview-map');
    if (mapEl) mapEl._pendingStartId = startId;

    if (updateMap && typeof window.updateToursOverviewMap === 'function') {
        window.updateToursOverviewMap(filtered, {
            fitBounds,
            startNodeId: startId,
            nodes: window.TOUR_NODES || [],
            modes,
        });
    }

    const drawerCount = document.getElementById('tours-segment-drawer-count');
    if (drawerCount) {
        drawerCount.textContent = filtered.length
            ? String(filtered.length)
            : '';
    }

    if (filtered.length === 0) {
        container.innerHTML = '';
        container.classList.remove('one-column-mode');
        return;
    }

    const currentLang = (typeof APP !== 'undefined' && APP.lang === 'en') ? 'en' : 'de';

    const MODE_LABELS_UI = window.TOUR_MODE_LABELS || { hike: 'Wandern', bike: 'Rad', ebike: 'E-Bike' };

    container.innerHTML = filtered.map(seg => {
        const profile = window.segmentDisplayProfile(seg, modes);
        const displayMode = profile ? profile.mode : 'hike';
        const modeLabel = MODE_LABELS_UI[displayMode] || displayMode;
        const eff = window.segmentEffectiveStats(seg);
        const desc = profile && profile.description
            ? (profile.description[currentLang] || profile.description.de || '')
            : '';

        let imgList = seg.images || [];
        let headerImagesHTML = '';
        if (imgList.length > 0) {
            const imgFitMode = imgList.length <= 1 ? 'contain' : 'cover';
            const imgStyle = `height: 100%; width: auto; max-width: 100%; object-fit: ${imgFitMode}; flex-shrink: 0; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);`;
            headerImagesHTML = imgList.slice(0, 4).map(src => {
                return `<img src="${window.tourImageUrl(src)}" style="${imgStyle}">`;
            }).join('');
        } else {
            headerImagesHTML = `<div style="font-size:30px;">⛰️</div>`;
        }

        let diffText = '—';
        if (profile) {
            const d = Math.max(1, Math.min(5, Number(profile.difficulty) || 1));
            diffText = 'Schwierigkeit ' + d;
        }

        let tagsHTML = '';
        const tags = (profile && profile.tags) || [];
        if (tags.length) {
            tagsHTML = `<div class="hike-tags">${tags.map(tag => {
                const i18nKey = 'tag_' + String(tag).toLowerCase();
                const label = (typeof t === 'function' && t(i18nKey) !== i18nKey) ? t(i18nKey) : capitalize(tag);
                return `<span class="tag-badge">${label}</span>`;
            }).join('')}</div>`;
        }

        const badgeStyle = `
            background: rgba(0,0,0,0.75); color: #fff; padding: 4px 12px; border-radius: 20px;
            font-size: 0.75rem; font-weight: bold; border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(4px); box-shadow: 0 2px 5px rgba(0,0,0,0.2); text-align: right;`;

        const flags = [];
        flags.push(`<div style="${badgeStyle} background: rgba(45,106,79,0.95);">${modeLabel}</div>`);
        if (seg.is_highlight) flags.push(`<div style="${badgeStyle} background: rgba(233,196,106,0.95); color:#222;">Highlight</div>`);
        if (seg.out_and_back) flags.push(`<div style="${badgeStyle} background: rgba(69,123,157,0.95);">Hin+Retour</div>`);

        const hasImages = imgList.length > 0;
        const imgClick = hasImages ? `onclick="openHikeGallery('${seg.id}', 0)"` : '';
        const kmLabel = seg.out_and_back
            ? `${eff.km.toFixed(1)} km <span style="opacity:.7;font-weight:500;">(2× ${eff.oneWayKm.toFixed(1)})</span>`
            : `${eff.oneWayKm.toFixed(1)} km`;
        const durMin = typeof window.estimateSegmentDurationMin === 'function'
            ? window.estimateSegmentDurationMin(seg, displayMode)
            : 0;
        const durLabel = durMin && typeof window.formatDurationMin === 'function'
            ? window.formatDurationMin(durMin)
            : '';
        const minTourKm = window._minTourKmBySegId && window._minTourKmBySegId.get(seg.id);
        const tourHint = (minTourKm != null)
            ? `<span style="opacity:.8;font-weight:500;">Tour ab ${Number(minTourKm).toFixed(1)} km</span>`
            : '';

        return `
        <div class="card hike-card">
            <div class="image-container" ${imgClick} style="
                height: 200px; position: relative; cursor: ${hasImages ? 'zoom-in' : 'default'}; overflow: hidden;
                display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 10px; padding: 10px;
                background: var(--card); border-bottom: 1px solid var(--border);">
                ${headerImagesHTML}
                <div style="position: absolute; top: 15px; right: 15px; z-index: 10; display: flex; flex-direction: column; gap: 6px; align-items: flex-end; pointer-events: none;">
                    <div style="${badgeStyle}">${diffText}</div>
                    ${flags.join('')}
                </div>
            </div>
            <div style="padding: var(--spacing); gap: 10px; flex-grow: 1; display: flex; flex-direction: column; width: 100%; min-width: 0;">
                <h3 style="font-size: 1.15rem; margin:0;">${seg.name || 'Segment'}</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 15px; color: var(--accent-soft); font-weight: bold; font-size: 0.9rem;">
                    <span>📏 Segment ${kmLabel}</span>
                    <span>⛰️ ${eff.hm} m</span>
                    ${durLabel ? `<span>⏱ ≈ ${durLabel}</span>` : ''}
                    ${tourHint}
                </div>
                ${tagsHTML}
                <p style="font-size: 0.9rem; color: var(--muted); line-height: 1.5; flex-grow: 1; margin:0;">${desc}</p>
            </div>
        </div>`;
    }).join('');

    if (typeof checkHikeLayout === 'function') checkHikeLayout();
};

// =========================================================
// TRIP-DETAIL (Varianten + Leaflet-Karte + GPX / Maps)
// =========================================================

window._activeTripId = null;
window._activeVariantId = null;

window.openTripDetail = function (tripId) {
    const all = window.ALL_TOURS || window.HIKES || [];
    const trip = all.find(h => String(h.id) === String(tripId));
    if (!trip) return;

    window._activeTripId = trip.id;
    const variants = trip.variants || [];
    const def = variants.find(v => v.is_default) || variants[0] || null;
    window._activeVariantId = def ? def.id : null;

    const currentLang = (typeof APP !== 'undefined' && APP.lang === 'en') ? 'en' : 'de';
    const title = (trip.title && trip.title[currentLang]) ? trip.title[currentLang] : (trip.title?.de || 'Tour');
    const desc = (trip.description && trip.description[currentLang]) ? trip.description[currentLang] : (trip.description?.de || '');

    const overlay = document.getElementById('trip-detail-overlay');
    const titleEl = document.getElementById('trip-detail-title');
    const descEl = document.getElementById('trip-detail-desc');
    const chips = document.getElementById('trip-detail-variants');

    if (titleEl) titleEl.textContent = title;
    if (descEl) descEl.textContent = desc;

    if (chips) {
        if (variants.length > 1) {
            chips.innerHTML = variants.map(v => `
                <button type="button" class="trip-variant-chip ${v.id === window._activeVariantId ? 'active' : ''}"
                        onclick="selectTripVariant(${trip.id}, ${v.id})">${v.name}</button>
            `).join('');
            chips.style.display = 'flex';
        } else if (variants.length === 1) {
            chips.innerHTML = `<span class="trip-variant-chip active">${variants[0].name}</span>`;
            chips.style.display = 'flex';
        } else {
            chips.innerHTML = '';
            chips.style.display = 'none';
        }
    }

    if (overlay) overlay.style.display = 'flex';
    document.body.classList.add('trip-detail-open');
    // Erst Vollbild zeigen, dann Karte (sonst Größe 0)
    requestAnimationFrame(() => {
        window.renderTripDetailVariant(trip, def);
        setTimeout(() => {
            const mapEl = document.getElementById('trip-detail-map');
            if (mapEl && mapEl._toursMap) {
                mapEl._toursMap.invalidateSize(true);
            }
        }, 80);
        setTimeout(() => {
            const mapEl = document.getElementById('trip-detail-map');
            if (mapEl && mapEl._toursMap) {
                mapEl._toursMap.invalidateSize(true);
            }
        }, 250);
    });
};

window.selectTripVariant = function (tripId, variantId) {
    const all = window.ALL_TOURS || [];
    const trip = all.find(h => String(h.id) === String(tripId));
    if (!trip) return;
    const variant = (trip.variants || []).find(v => v.id === variantId);
    window._activeVariantId = variantId;

    document.querySelectorAll('.trip-variant-chip').forEach(btn => {
        btn.classList.toggle('active', String(btn.getAttribute('onclick')).includes(`, ${variantId})`));
    });

    window.renderTripDetailVariant(trip, variant || null);
};

window.renderTripDetailVariant = function (trip, variant) {
    const statsEl = document.getElementById('trip-detail-stats');
    const mapEl = document.getElementById('trip-detail-map');
    const mapStage = document.querySelector('.trip-detail-map-stage');
    const segInfo = document.getElementById('trip-detail-seg-info');
    const fallback = document.getElementById('trip-detail-map-fallback');
    const actions = document.getElementById('trip-detail-actions');

    const dist = variant ? variant.dist : trip.dist;
    const alt = variant ? variant.alt : trip.alt;
    const duration = variant && variant.duration_min
        ? ` · ⏱ ${variant.duration_min} min`
        : '';

    const currentLang = (typeof APP !== 'undefined' && APP.lang === 'en') ? 'en' : 'de';
    const title = (trip.title && trip.title[currentLang])
        ? trip.title[currentLang]
        : (trip.title?.de || 'Tour');

    if (statsEl) {
        statsEl.innerHTML = `<span>📏 ${dist} km</span><span>⛰️ ${alt} m</span>${duration}`;
    }

    const hasMap = variant && variant.has_map && variant.segments && variant.segments.length;
    if (segInfo) segInfo.hidden = true;
    if (mapStage) mapStage.style.display = hasMap ? 'flex' : 'none';
    if (mapEl) {
        mapEl.style.display = hasMap ? 'block' : 'none';
        if (hasMap && typeof window.renderToursMap === 'function') {
            window.renderToursMap(mapEl, {
                segments: variant.segments,
                title,
                tripKm: dist,
                tripHm: alt,
            });
        } else if (mapEl._toursMap) {
            mapEl._toursMap.remove();
            mapEl._toursMap = null;
        }
    }
    if (fallback) fallback.style.display = hasMap ? 'none' : 'block';

    let html = '';
    if (trip.images && trip.images.length) {
        html += `<button type="button" class="add-btn" onclick="openHikeGallery('${trip.id}', 0)">Bilder</button>`;
    }
    if (variant && variant.gpx_url) {
        html += `<a class="add-btn" href="${variant.gpx_url}" download>GPX laden</a>`;
    }
    if (variant && variant.maps_url) {
        html += `<a class="add-btn" href="${variant.maps_url}" target="_blank" rel="noopener">In Google Maps</a>`;
    }
    if (trip.komoot_url) {
        html += `<a class="add-btn" href="${trip.komoot_url}" target="_blank" rel="noopener">Komoot</a>`;
    }
    if (trip.video) {
        html += `<button type="button" class="add-btn" onclick="playHikeVideo('${trip.video}')">Video</button>`;
    }
    if (actions) actions.innerHTML = html;
};

window.closeTripDetail = function () {
    const overlay = document.getElementById('trip-detail-overlay');
    if (overlay) overlay.style.display = 'none';
    document.body.classList.remove('trip-detail-open');
    const mapEl = document.getElementById('trip-detail-map');
    if (mapEl && mapEl._toursMap) {
        mapEl._toursMap.remove();
        mapEl._toursMap = null;
    }
    const segInfo = document.getElementById('trip-detail-seg-info');
    if (segInfo) segInfo.hidden = true;
};

// 4. INITIALISIERUNG — Segment-Graph (Modi + Startpunkt)
// Tours-HTML kommt oft erst per Livewire-defer; das JS-Bundle erst beim Tab-Öffnen.
// Deshalb nicht nur DOMContentLoaded — bootToursGuestApp() ist wiederholbar bis es greift.
window.bootToursGuestApp = function () {
    if (window._toursWanted === false || window._toursBooting) return false;
    const container = document.getElementById('tours-app-container');
    if (!container) return false;
    if (window._toursGuestBooted) {
        clearTimeout(window._toursMapTimer);
        window._toursMapTimer = setTimeout(function () {
            if (window._toursWanted === false) return;
            if (typeof window.invalidateToursOverviewMap === 'function') {
                window.invalidateToursOverviewMap();
            }
        }, 40);
        return true;
    }
    if (window._toursPrepared) {
        clearTimeout(window._toursMapTimer);
        window._toursMapTimer = setTimeout(window._toursFinishBoot, 0);
        return false;
    }
    window._toursBooting = true;

    let graph = { nodes: [], segments: [] };
    try {
        const raw = container.getAttribute('data-graph');
        if (raw) graph = JSON.parse(raw);
    } catch (e) {
        console.warn('Tour-Graph konnte nicht gelesen werden', e);
    }

    window.TOUR_NODES = graph.nodes || [];
    window.ALL_SEGMENTS = graph.segments || [];
    window.HIKES = window.ALL_SEGMENTS;

    const endMayDiffer = document.getElementById('filter-end-may-differ');
    const hutOvernight = document.getElementById('filter-hut-overnight');

    window.TOUR_PROFILE_MODES = graph.profile_modes || {
        hike: 'Wandern',
        bike: 'Rad',
        ebike: 'E-Bike',
    };
    Object.assign(window.TOUR_MODE_LABELS, window.TOUR_PROFILE_MODES, {
        cable: 'Seilbahn',
    });
    if (graph.start_kinds && typeof graph.start_kinds === 'object') {
        window.TOUR_START_KIND_LABELS = graph.start_kinds;
    }

    window.applyTourGraphFilters = function (fitBounds) {
        try {
            localStorage.setItem('savedTourModes', JSON.stringify(activeFilters.modes || []));
            localStorage.setItem('savedTourStartKinds', JSON.stringify(activeFilters.startKinds || []));
            if (endMayDiffer) {
                localStorage.setItem('savedTourEndMayDiffer', endMayDiffer.checked ? '1' : '0');
            }
            if (hutOvernight) {
                localStorage.setItem('savedTourHutOvernight', hutOvernight.checked ? '1' : '0');
            }
        } catch (e) {}

        const routeStart = window.GUEST_ROUTE && window.GUEST_ROUTE.startNodeId;
        if (routeStart != null) {
            const n = (window.TOUR_NODES || []).find(x => Number(x.id) === Number(routeStart));
            if (!window.isValidRouteBaseNode(n)) {
                window.clearGuestRoute();
            }
        }

        if (typeof renderHikes === 'function') {
            renderHikes({ updateMap: true, fitBounds: !!fitBounds });
        }
    };

    function normalizeSavedFilter(saved, allKeys) {
        if (!Array.isArray(saved)) return null;
        // Früher „alles an“ = kein Filter → jetzt leere Auswahl
        if (allKeys.length && saved.length >= allKeys.length
            && allKeys.every(k => saved.includes(k))) {
            return [];
        }
        return saved.slice();
    }

    try {
        const allModes = Object.keys(graph.profile_modes || { hike: 1, bike: 1, ebike: 1 });
        const allKinds = Object.keys(graph.start_kinds || {
            ferienwohnung: 1, bus: 1, auto: 1,
        });
        const savedModes = normalizeSavedFilter(
            JSON.parse(localStorage.getItem('savedTourModes') || 'null'),
            allModes
        );
        if (savedModes) activeFilters.modes = savedModes;
        const savedKinds = normalizeSavedFilter(
            JSON.parse(localStorage.getItem('savedTourStartKinds') || 'null'),
            allKinds
        );
        if (savedKinds) activeFilters.startKinds = savedKinds;

        const savedDiffer = localStorage.getItem('savedTourEndMayDiffer');
        if (endMayDiffer && (savedDiffer === '0' || savedDiffer === '1')) {
            endMayDiffer.checked = savedDiffer === '1';
        }
        const savedHut = localStorage.getItem('savedTourHutOvernight');
        if (hutOvernight && (savedHut === '0' || savedHut === '1')) {
            hutOvernight.checked = savedHut === '1';
        }
    } catch (e) {}

    if (endMayDiffer) {
        endMayDiffer.addEventListener('change', () => window.applyTourGraphFilters(false));
    }
    if (hutOvernight) {
        hutOvernight.addEventListener('change', () => window.applyTourGraphFilters(false));
    }

    const undoBtn = document.getElementById('tours-route-undo');
    const clearBtn = document.getElementById('tours-route-clear');
    const modeClose = document.getElementById('tours-mode-chooser-close');
    if (undoBtn) undoBtn.addEventListener('click', () => window.undoGuestRouteStep());
    if (clearBtn) clearBtn.addEventListener('click', () => window.clearGuestRoute());
    if (modeClose) modeClose.addEventListener('click', () => window.hideRouteModeChooser());
    if (typeof window.syncGuestRouteUi === 'function') window.syncGuestRouteUi();

    document.querySelectorAll('.dd-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (typeof toggleMultiSelect === 'function') {
                toggleMultiSelect(this.getAttribute('data-target'));
            }
        });
    });

    if (typeof initMultiFilters === 'function') initMultiFilters();
    ['modes', 'startKinds'].forEach(syncFilterItemDom);
    if (typeof setupHikeSliders === 'function') {
        window.hikesFiltersInitialized = false;
        setupHikeSliders();
    }
    if (typeof window.initPlannerUi === 'function') window.initPlannerUi();
    window._toursPrepared = true;
    window._toursBooting = false;
    window._toursFinishBoot = function () {
        if (window._toursWanted === false || window._toursGuestBooted) return;
        window._toursBooting = true;
        window.applyTourGraphFilters(true);
        window._toursGuestBooted = true;
        window._toursBooting = false;
        if (window._toursObs) {
            try { window._toursObs.disconnect(); } catch (e) {}
            window._toursObs = null;
        }
        console.info('[Haus Meli] Tours-UI gebootet', {
            nodes: (window.TOUR_NODES || []).length,
            segments: (window.ALL_SEGMENTS || []).length,
        });
    };
    clearTimeout(window._toursMapTimer);
    window._toursMapTimer = setTimeout(window._toursFinishBoot, 0);
    return false;
};

// Nicht synchron booten: ein Wisch weg soll das Laden noch abbrechen können.
window.scheduleToursBoot = function () {
    clearTimeout(window._toursBootSoon);
    window._toursBootSoon = setTimeout(function () {
        if (window._toursWanted === false) return;
        window.bootToursGuestApp && window.bootToursGuestApp();
    }, 0);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        window.scheduleToursBoot();
    });
} else {
    window.scheduleToursBoot();
}

(function watchToursContainer() {
    if (!window.MutationObserver || !document.body) {
        setTimeout(watchToursContainer, 50);
        return;
    }
    const obs = new MutationObserver(function () {
        if (window._toursWanted === false || window._toursGuestBooted) return;
        window.scheduleToursBoot();
    });
    window._toursObs = obs;
    obs.observe(document.body, { childList: true, subtree: true });
    setTimeout(function () { try { obs.disconnect(); } catch (e) {} }, 60000);
})();

