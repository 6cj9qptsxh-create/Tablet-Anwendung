/**
 * Klassischer Planungsmodus: Start/Ziel/Via, schnellste Route, Budgets pro Sportart.
 * Abhängigkeit: tours.js (Graph-Helfer, GUEST_ROUTE, Mode-Labels).
 */
(function () {
    'use strict';

    const MODE_DURATION_PARAMS = {
        hike: { speed: 4, hmPer100: 10 },
        bike: { speed: 12, hmPer100: 6 },
        ebike: { speed: 15, hmPer100: 3 },
        ski: { speed: 8, hmPer100: 5 },
        sled: { speed: 6, hmPer100: 4 },
    };
    const CABLE_DEFAULT_MIN = 15;

    window.GUEST_PLAN = {
        startNodeId: null,
        goalNodeId: null,
        vias: [],
        tripShape: 'out_and_back',
        /** Rundweg: Hin- und Rückkorridor tauschen */
        loopReversed: false,
        lastHint: '',
        /** Manuell gewählte Sportart — bleibt bei Neuberechnung; null = automatisch schnellste */
        preferredMode: null,
    };

    window.estimateSegmentDurationMin = function (seg, mode) {
        if (!seg) return 1;
        const m = mode || 'hike';
        const isCable = (window.isSeilbahnSegment && window.isSeilbahnSegment(seg)) || m === 'cable';
        if (isCable) {
            const storedCable = window.getSegmentModeDurationStored(seg, m);
            return storedCable > 0 ? storedCable : CABLE_DEFAULT_MIN;
        }
        const stored = window.getSegmentModeDurationStored(seg, m);
        if (stored > 0) return stored;

        const km = Number(seg.distance_km) || 0;
        const hm = Number(seg.elevation_m) || 0;
        const params = MODE_DURATION_PARAMS[m] || MODE_DURATION_PARAMS.hike;
        const fromDist = (km / Math.max(0.5, params.speed)) * 60;
        const fromHm = (hm / 100) * params.hmPer100;
        let minutes = Math.max(1, Math.round(fromDist + fromHm));
        if (seg.out_and_back) {
            // Hin+Retour: Distanz doppelt, Hm-Zuschlag nur hinauf (wie km*2 / gleiche Hm)
            const backDist = (km / Math.max(0.5, params.speed)) * 60;
            minutes = Math.max(1, Math.round(fromDist + fromHm + backDist));
        }
        return minutes;
    };

    window.getSegmentModeDurationStored = function (seg, mode) {
        const profiles = seg && Array.isArray(seg.modes) ? seg.modes : [];
        const p = profiles.find(x => x && x.mode === mode && x.is_active !== false && x.is_active !== 0 && x.is_active !== '0');
        if (p && p.duration_min != null && Number(p.duration_min) > 0) {
            return Number(p.duration_min);
        }
        if ((!mode || mode === seg.mode) && seg.duration_min != null && Number(seg.duration_min) > 0) {
            return Number(seg.duration_min);
        }
        return 0;
    };

    window.formatDurationMin = function (minutes) {
        const m = Math.max(0, Math.round(Number(minutes) || 0));
        if (m < 60) return m + ' min';
        const h = Math.floor(m / 60);
        const rest = m % 60;
        return rest ? (h + ' h ' + rest + ' min') : (h + ' h');
    };

    window.getGuestRouteDurationMin = function () {
        let total = 0;
        (window.GUEST_ROUTE.steps || []).forEach(step => {
            const seg = (window.ALL_SEGMENTS || []).find(s => Number(s.id) === Number(step.segmentId));
            if (!seg) return;
            total += window.estimateSegmentDurationMin(seg, step.mode || 'hike');
        });
        return total;
    };

    /** Max-Budgets pro Sportart aus den Mode-Doppelslidern. */
    window.getModeBudgets = function () {
        const modes = (typeof window.getActiveModes === 'function')
            ? window.getActiveModes()
            : ['hike', 'bike', 'ebike'];
        const budgets = {};
        modes.forEach(mode => {
            let minKm = parseFloat(document.getElementById('budget-' + mode + '-km-min')?.value);
            let maxKm = parseFloat(document.getElementById('budget-' + mode + '-km-max')?.value);
            let minHm = parseFloat(document.getElementById('budget-' + mode + '-hm-min')?.value);
            let maxHm = parseFloat(document.getElementById('budget-' + mode + '-hm-max')?.value);
            if (!Number.isFinite(minKm)) minKm = 0;
            if (!Number.isFinite(maxKm)) maxKm = 9999;
            if (!Number.isFinite(minHm)) minHm = 0;
            if (!Number.isFinite(maxHm)) maxHm = 99999;
            if (minKm > maxKm) [minKm, maxKm] = [maxKm, minKm];
            if (minHm > maxHm) [minHm, maxHm] = [maxHm, minHm];
            budgets[mode] = { minKm, maxKm, minHm, maxHm };
        });
        return budgets;
    };

    window.routeFitsModeBudgets = function (steps, budgets) {
        const byMode = {};
        (steps || []).forEach(step => {
            const mode = step.mode || 'hike';
            const seg = (window.ALL_SEGMENTS || []).find(s => Number(s.id) === Number(step.segmentId));
            if (!seg) return;
            const eff = window.segmentEffectiveStats(seg);
            if (!byMode[mode]) byMode[mode] = { km: 0, hm: 0 };
            byMode[mode].km += eff.km;
            byMode[mode].hm += eff.hm;
        });
        for (const mode of Object.keys(byMode)) {
            const b = budgets[mode];
            if (!b) continue;
            if (byMode[mode].km > b.maxKm + 1e-6) return false;
            if (byMode[mode].hm > b.maxHm + 1e-6) return false;
            // Min nur prüfen wenn Route diesen Mode nutzt und Min > 0
            if (b.minKm > 0 && byMode[mode].km + 1e-6 < b.minKm) return false;
            if (b.minHm > 0 && byMode[mode].hm + 1e-6 < b.minHm) return false;
        }
        return true;
    };

    /**
     * Schnellste Kante(n) von from→to unter erlaubten Modes.
     * Gibt Steps zurück oder null.
     */
    window.dijkstraFastestPath = function (fromId, toId, opts) {
        opts = opts || {};
        const pool = opts.pool || window.ALL_SEGMENTS || [];
        const modes = opts.modes || (window.getActiveModes ? window.getActiveModes() : []);
        const forbidden = new Set((opts.forbiddenSegIds || []).map(Number));
        const start = Number(fromId);
        const goal = Number(toId);
        if (!Number.isFinite(start) || !Number.isFinite(goal)) return null;
        if (start === goal) return { steps: [], minutes: 0 };

        const { adj, byId } = window.buildPlannerAdj(pool, modes, forbidden);
        const dist = new Map();
        const prev = new Map(); // node -> { from, edge }
        const pq = []; // { node, cost }

        function push(node, cost) {
            pq.push({ node, cost });
            pq.sort((a, b) => a.cost - b.cost);
        }

        dist.set(start, 0);
        push(start, 0);

        while (pq.length) {
            const cur = pq.shift();
            if (cur.cost !== dist.get(cur.node)) continue;
            if (cur.node === goal) break;
            const edges = adj.get(cur.node) || [];
            for (let i = 0; i < edges.length; i++) {
                const e = edges[i];
                const nextCost = cur.cost + e.minutes;
                const known = dist.has(e.other) ? dist.get(e.other) : Infinity;
                if (nextCost + 1e-9 < known) {
                    dist.set(e.other, nextCost);
                    prev.set(e.other, { from: cur.node, edge: e });
                    push(e.other, nextCost);
                }
            }
        }

        if (!dist.has(goal)) return null;

        const chain = [];
        let node = goal;
        while (node !== start) {
            const p = prev.get(node);
            if (!p) return null;
            chain.push(p.edge);
            node = p.from;
        }
        chain.reverse();

        const steps = chain.map(e => ({
            segmentId: e.id,
            fromNodeId: e.fromNode,
            toNodeId: e.toNode,
            reversed: !!e.reversed,
            mode: e.mode,
        }));

        return { steps, minutes: dist.get(goal) };
    };

    /** Adjazenz mit Mode-Wahl (schnellste erlaubte Sportart) und Dauer. */
    window.buildPlannerAdj = function (segments, modes, forbidden) {
        const byId = new Map();
        const adj = new Map();
        const modeList = modes && modes.length ? modes : ['hike', 'bike', 'ebike'];
        const forbid = forbidden || new Set();

        (segments || []).forEach(seg => {
            const sid = Number(seg.id);
            if (forbid.has(sid)) return;
            byId.set(sid, seg);
            const a = Number(seg.from_node_id);
            const b = Number(seg.to_node_id);
            if (!adj.has(a)) adj.set(a, []);
            if (!adj.has(b)) adj.set(b, []);

            const modeKeys = (typeof window.segmentMatchingModes === 'function')
                ? window.segmentMatchingModes(seg, modeList)
                : modeList.filter(m => (seg.modes || []).some(p => p.mode === m));
            if (!modeKeys.length) return;

            let bestMode = modeKeys[0];
            let bestMin = Infinity;
            const preferred = window.GUEST_PLAN && window.GUEST_PLAN.preferredMode;
            if (preferred && modeKeys.includes(preferred)) {
                bestMode = preferred;
                bestMin = window.estimateSegmentDurationMin(seg, preferred);
            } else {
                modeKeys.forEach(m => {
                    const d = window.estimateSegmentDurationMin(seg, m);
                    if (d < bestMin) {
                        bestMin = d;
                        bestMode = m;
                    }
                });
            }

            const eff = window.segmentEffectiveStats(seg);

            if (seg.out_and_back) {
                adj.get(a).push({
                    id: sid,
                    other: a,
                    fromNode: a,
                    toNode: a,
                    minutes: bestMin,
                    mode: bestMode,
                    km: eff.km,
                    hm: eff.hm,
                    reversed: false,
                    out_and_back: true,
                });
                if (a !== b) {
                    adj.get(b).push({
                        id: sid,
                        other: b,
                        fromNode: b,
                        toNode: b,
                        minutes: bestMin,
                        mode: bestMode,
                        km: eff.km,
                        hm: eff.hm,
                        reversed: false,
                        out_and_back: true,
                    });
                }
                return;
            }

            adj.get(a).push({
                id: sid,
                other: b,
                fromNode: a,
                toNode: b,
                minutes: bestMin,
                mode: bestMode,
                km: eff.km,
                hm: eff.hm,
                reversed: false,
                out_and_back: false,
            });
        });

        return { byId, adj };
    };

    window.reverseRouteSteps = function (steps) {
        const pool = window.ALL_SEGMENTS || [];
        const out = [];
        for (let i = steps.length - 1; i >= 0; i--) {
            const step = steps[i];
            if (step.deadEndSpur || step.deadEndApproach) continue;
            const seg = pool.find(s => Number(s.id) === Number(step.segmentId));
            if (!seg) continue;
            if (seg.out_and_back) {
                continue;
            }
            const rev = window.findReverseSegment(seg, pool);
            if (!rev) return null;
            const mode = step.mode;
            // wheeled / manuelle Präferenz beibehalten; sonst pickBestModeForSeg
            let useMode = mode;
            if (!(window.segmentHasModeProfile && window.segmentHasModeProfile(rev, useMode))) {
                const modes = window.getActiveModes ? window.getActiveModes() : [];
                useMode = window.pickBestModeForSeg(rev, modes);
                if (!useMode) return null;
            }
            if (window.isWheeledMode && window.isWheeledMode(mode)) {
                useMode = mode;
                if (!(window.segmentHasModeProfile && window.segmentHasModeProfile(rev, useMode))) {
                    return null;
                }
            }
            out.push({
                segmentId: Number(rev.id),
                fromNodeId: Number(step.toNodeId),
                toNodeId: Number(step.fromNodeId),
                // Retour-Segment ist schon B→A gespeichert — nicht nochmal umdrehen
                // (sonst geht der Höhenprofil-Rückweg wieder bergauf)
                reversed: false,
                mode: useMode,
            });
        }
        return out;
    };

    /**
     * Sackgasse: DB-Flag is_dead_end, Stich (1 Nachbar) oder out_and_back-Tip.
     * Detour — kein Hard-Waypoint im Korridor, damit Rundweg/Retour intakt bleiben.
     */
    window.isDeadEndNode = function (nodeOrId) {
        const node = (typeof nodeOrId === 'object' && nodeOrId)
            ? nodeOrId
            : (window.TOUR_NODES || []).find(n => Number(n.id) === Number(nodeOrId));
        return !!(node && node.is_dead_end);
    };

    window.pickBestModeForSeg = function (seg, modes) {
        const modeList = modes || (window.getActiveModes ? window.getActiveModes() : []);
        const keys = window.segmentMatchingModes(seg, modeList);
        if (!keys.length) return null;
        const preferred = window.GUEST_PLAN && window.GUEST_PLAN.preferredMode;
        if (preferred && keys.includes(preferred)) return preferred;
        let bestMode = keys[0];
        let best = Infinity;
        keys.forEach(m => {
            const d = window.estimateSegmentDurationMin(seg, m);
            if (d < best) {
                best = d;
                bestMode = m;
            }
        });
        return bestMode;
    };

    window.corridorNodeIds = function (steps, startId) {
        const ids = [Number(startId)];
        (steps || []).forEach(s => {
            if (s.deadEndSpur || s.deadEndApproach) return;
            ids.push(Number(s.toNodeId));
        });
        return ids;
    };

    /**
     * @returns {null|{ tipId, entryId, spurSeg, spurBack, deadEnd: true }}
     */
    window.resolveDeadEndDetour = function (nodeId) {
        const nid = Number(nodeId);
        const pool = window.ALL_SEGMENTS || [];
        const node = (window.TOUR_NODES || []).find(n => Number(n.id) === nid);
        if (!node) return null;

        const oab = pool.find(s =>
            s.out_and_back
            && (Number(s.from_node_id) === nid || Number(s.to_node_id) === nid)
        );

        if (oab) {
            const a = Number(oab.from_node_id);
            const b = Number(oab.to_node_id);
            const entry = nid === a ? b : a;
            if (entry === nid) return null;

            // Grad im Gesamtgraph (inkl. oab): Tip hat typisch nur diesen Anschluss
            const degree = new Set();
            pool.forEach(s => {
                const x = Number(s.from_node_id);
                const y = Number(s.to_node_id);
                if (x === nid) degree.add(y);
                if (y === nid) degree.add(x);
            });
            const isTip = window.isDeadEndNode(node) || !!node.is_highlight || degree.size <= 1;
            if (isTip) {
                return {
                    tipId: nid,
                    entryId: entry,
                    spurSeg: oab,
                    spurBack: null,
                    deadEnd: true,
                };
            }
        }

        const touching = pool.filter(s =>
            !s.out_and_back
            && (Number(s.from_node_id) === nid || Number(s.to_node_id) === nid)
        );
        if (!touching.length) return null;

        const neighbors = new Set();
        touching.forEach(s => {
            const a = Number(s.from_node_id);
            const b = Number(s.to_node_id);
            neighbors.add(a === nid ? b : a);
        });

        // Stich (1 Nachbar) oder explizites Flag — nie als Hard-Via erzwingen
        const isLeaf = neighbors.size === 1;
        if (!window.isDeadEndNode(node) && !isLeaf) return null;

        const entryId = neighbors.values().next().value;
        const toTip = pool.find(s =>
            Number(s.from_node_id) === entryId && Number(s.to_node_id) === nid
        );
        const fromTip = pool.find(s =>
            Number(s.from_node_id) === nid && Number(s.to_node_id) === entryId
        );
        if (!toTip && !fromTip) return null;
        return {
            tipId: nid,
            entryId: Number(entryId),
            spurSeg: toTip || fromTip,
            spurBack: toTip && fromTip ? fromTip : null,
            deadEnd: true,
        };
    };

    window.appendDeadEndDetourSteps = function (steps, detour) {
        if (!detour) return steps;
        const modes = window.getActiveModes ? window.getActiveModes() : [];
        const entry = Number(detour.entryId);
        if (detour.spurSeg && detour.spurSeg.out_and_back) {
            const mode = window.pickBestModeForSeg(detour.spurSeg, modes);
            if (!mode) return steps;
            steps.push({
                segmentId: Number(detour.spurSeg.id),
                fromNodeId: entry,
                toNodeId: entry,
                reversed: false,
                mode,
                deadEndSpur: true,
            });
            return steps;
        }
        if (detour.spurSeg) {
            const mode = window.pickBestModeForSeg(detour.spurSeg, modes);
            if (mode) {
                steps.push({
                    segmentId: Number(detour.spurSeg.id),
                    fromNodeId: Number(detour.spurSeg.from_node_id),
                    toNodeId: Number(detour.spurSeg.to_node_id),
                    reversed: Number(detour.spurSeg.from_node_id) !== entry,
                    mode,
                    deadEndSpur: true,
                });
            }
        }
        if (detour.spurBack) {
            const mode = window.pickBestModeForSeg(detour.spurBack, modes);
            if (mode) {
                steps.push({
                    segmentId: Number(detour.spurBack.id),
                    fromNodeId: Number(detour.spurBack.from_node_id),
                    toNodeId: Number(detour.spurBack.to_node_id),
                    reversed: false,
                    mode,
                    deadEndSpur: true,
                });
            }
        }
        return steps;
    };

    /**
     * Sackgasse am ersten Treffer des Einstiegs auf dem Hinweg einfügen.
     * Fehlt der Einstieg: am nächstgelegenen Korridorpunkt abzweigen — nie vom Ziel/Gipfel aus.
     */
    /**
     * Abstecher genau einmal: beim ersten Erreichen der Abzweigung.
     * Zweites / weiteres Passieren derselben Abzweigung → kein erneuter Besuch.
     */
    window.insertDetoursIntoRoute = function (routeSteps, startId, detours, pool, modes) {
        if (!detours || !detours.length) return routeSteps.slice();
        let out = routeSteps.slice();

        detours.forEach(detour => {
            const tip = Number(detour.tipId);
            let entry = Number(detour.entryId);

            if (!Number.isFinite(entry)) {
                const candidates = [{ idx: -1, node: Number(startId) }];
                out.forEach((s, i) => {
                    if (s.deadEndSpur || s.deadEndApproach) return;
                    candidates.push({ idx: i, node: Number(s.toNodeId) });
                });
                let best = null;
                candidates.forEach(c => {
                    if (c.node === tip) {
                        if (!best || 0 < best.cost) best = { idx: c.idx, cost: 0, entry: c.node };
                        return;
                    }
                    const leg = window.dijkstraFastestPath(c.node, tip, {
                        pool,
                        modes,
                        forbiddenSegIds: [],
                    });
                    if (!leg) return;
                    if (!best || leg.minutes < best.cost) {
                        best = { idx: c.idx, cost: leg.minutes, entry: c.node };
                    }
                });
                if (!best) return;
                entry = Number(best.entry);
                detour = Object.assign({}, detour, { entryId: entry });
            }

            // Nur der erste Treffer der Abzweigung
            let at = -2;
            if (Number(startId) === entry) {
                at = -1;
            } else {
                for (let i = 0; i < out.length; i++) {
                    if (out[i].deadEndSpur || out[i].deadEndApproach) continue;
                    if (Number(out[i].toNodeId) === entry) {
                        at = i;
                        break;
                    }
                }
            }

            if (at === -2) {
                // Abzweigung liegt nicht auf der Route → kürzesten Anlauf vom Korridor
                const candidates = [{ idx: -1, node: Number(startId) }];
                out.forEach((s, i) => {
                    if (s.deadEndSpur || s.deadEndApproach) return;
                    candidates.push({ idx: i, node: Number(s.toNodeId) });
                });
                let best = null;
                candidates.forEach(c => {
                    if (c.node === entry) {
                        if (!best || 0 < best.cost) best = { idx: c.idx, cost: 0, leg: { steps: [] } };
                        return;
                    }
                    const leg = window.dijkstraFastestPath(c.node, entry, {
                        pool,
                        modes,
                        forbiddenSegIds: [],
                    });
                    if (!leg) return;
                    if (!best || leg.minutes < best.cost) {
                        best = { idx: c.idx, cost: leg.minutes, leg };
                    }
                });
                if (!best) return;

                const before = best.idx < 0 ? [] : out.slice(0, best.idx + 1);
                const after = best.idx < 0 ? out.slice() : out.slice(best.idx + 1);
                const mid = [];
                (best.leg.steps || []).forEach(s => {
                    mid.push(Object.assign({}, s, { deadEndApproach: true }));
                });
                window.appendVisitOrDeadEndSteps(mid, detour, pool, modes);
                const back = window.reverseRouteSteps(best.leg.steps || []);
                if (back) {
                    back.forEach(s => mid.push(Object.assign({}, s, { deadEndApproach: true })));
                }
                out = before.concat(mid, after);
                return;
            }

            const mid = [];
            window.appendVisitOrDeadEndSteps(mid, detour, pool, modes);
            if (at < 0) {
                out = mid.concat(out);
            } else {
                out = out.slice(0, at + 1).concat(mid, out.slice(at + 1));
            }
        });

        return out;
    };

    window.appendVisitOrDeadEndSteps = function (steps, detour, pool, modes) {
        if (detour.deadEnd || detour.spurSeg) {
            return window.appendDeadEndDetourSteps(steps, detour);
        }
        const entry = Number(detour.entryId);
        const tip = Number(detour.tipId);
        if (!Number.isFinite(entry) || !Number.isFinite(tip) || entry === tip) return steps;
        const toTip = window.dijkstraFastestPath(entry, tip, {
            pool,
            modes,
            forbiddenSegIds: [],
        });
        if (!toTip) return steps;
        toTip.steps.forEach(s => {
            steps.push(Object.assign({}, s, { deadEndApproach: true }));
        });
        const back = window.reverseRouteSteps(toTip.steps);
        if (back) {
            back.forEach(s => steps.push(Object.assign({}, s, { deadEndApproach: true })));
        }
        return steps;
    };

    window.routeStepsDurationMin = function (steps, pool) {
        const segs = pool || window.ALL_SEGMENTS || [];
        let total = 0;
        (steps || []).forEach(step => {
            const seg = segs.find(s => Number(s.id) === Number(step.segmentId));
            if (!seg) return;
            total += window.estimateSegmentDurationMin(seg, step.mode || 'hike');
        });
        return total;
    };

    /** @deprecated */
    window.insertDeadEndDetoursOnOutbound = function (outbound, startId, detours, pool, modes) {
        return window.insertDetoursIntoRoute(outbound, startId, detours, pool, modes);
    };

    /**
     * Zwischenziel → Abstecher (Abzweigung + Tip).
     * Kein Hard-Via mehr: die schnellste Hauptroute entscheidet, Besuch nur 1× an der Abzweigung.
     */
    window.resolveViaDetour = function (vid) {
        const id = Number(vid);
        const dead = window.resolveDeadEndDetour(id);
        if (dead && dead.deadEnd) return dead;
        return {
            tipId: id,
            entryId: id,
            softVisit: true,
            deadEnd: false,
        };
    };

    /**
     * Kandidaten für den Hinweg: direkt Start→Ziel, oder über Abzweigung(en),
     * wenn das die Gesamttour (inkl. einmaligem Zwischenziel) schneller macht.
     * Rundweg: Kandidaten ohne echten Alternativ-Rückweg werden verworfen.
     */
    window.pickFastestOutboundForVias = function (startId, routeGoal, viaDetours, pool, modes, tripShape) {
        const direct = window.dijkstraFastestPath(startId, routeGoal, {
            pool,
            modes,
            forbiddenSegIds: [],
        });
        if (!direct) return null;

        const candidates = [direct.steps.slice()];
        const entries = [];
        (viaDetours || []).forEach(d => {
            const e = Number(d.entryId);
            if (!Number.isFinite(e)) return;
            if (e === Number(startId) || e === Number(routeGoal)) return;
            if (entries.some(x => x === e)) return;
            entries.push(e);
        });

        // Bei Rundweg: Hinweg möglichst direkt lassen — Umbiegen über Via
        // blockiert oft den Alternativ-Rückweg. Via kommt als 1×-Abstecher drauf.
        if (tripShape !== 'loop') {
            entries.forEach(e => {
                const a = window.dijkstraFastestPath(startId, e, {
                    pool,
                    modes,
                    forbiddenSegIds: [],
                });
                const b = window.dijkstraFastestPath(e, routeGoal, {
                    pool,
                    modes,
                    forbiddenSegIds: [],
                });
                if (a && b) candidates.push(a.steps.concat(b.steps));
            });

            if (entries.length >= 2) {
                let chain = [];
                let ok = true;
                let from = Number(startId);
                entries.concat([Number(routeGoal)]).forEach(to => {
                    if (!ok) return;
                    if (from === to) return;
                    const leg = window.dijkstraFastestPath(from, to, {
                        pool,
                        modes,
                        forbiddenSegIds: [],
                    });
                    if (!leg) {
                        ok = false;
                        return;
                    }
                    chain = chain.concat(leg.steps);
                    from = to;
                });
                if (ok && chain.length) candidates.push(chain);
            }
        }

        const tipOf = (steps) => {
            for (let i = steps.length - 1; i >= 0; i--) {
                if (steps[i].deadEndSpur || steps[i].deadEndApproach) continue;
                return Number(steps[i].toNodeId);
            }
            return Number(routeGoal);
        };

        const buildLoopReturn = (outbound) => {
            const corridor = outbound.filter(s => !s.deadEndSpur && !s.deadEndApproach);
            const tip = tipOf(outbound);
            if (tip === Number(startId)) return { steps: [], ok: true, note: '' };
            const forbid = corridor.map(s => Number(s.segmentId));
            let returnLeg = window.dijkstraFastestPath(tip, startId, {
                pool,
                modes,
                forbiddenSegIds: forbid,
            });
            if (returnLeg && returnLeg.steps.length) {
                return { steps: returnLeg.steps, ok: true, note: '' };
            }
            returnLeg = window.dijkstraFastestPath(tip, startId, {
                pool,
                modes,
                forbiddenSegIds: [],
            });
            if (!returnLeg || !returnLeg.steps.length) {
                return { steps: null, ok: false, note: '' };
            }
            const rev = window.reverseRouteSteps(corridor);
            const same = rev && rev.length === returnLeg.steps.length
                && rev.every((s, i) => Number(s.segmentId) === Number(returnLeg.steps[i].segmentId));
            if (same) {
                return { steps: null, ok: false, note: '' };
            }
            return {
                steps: returnLeg.steps,
                ok: true,
                note: 'Rundweg mit teilweiser Streckenüberschneidung.',
            };
        };

        const score = (outbound) => {
            const corridor = outbound.filter(s => !s.deadEndSpur && !s.deadEndApproach);
            let full = outbound.slice();
            if (tripShape === 'out_and_back') {
                const back = window.reverseRouteSteps(corridor);
                if (!back) return Infinity;
                full = outbound.concat(back);
            } else if (tripShape === 'loop') {
                const ret = buildLoopReturn(outbound);
                if (!ret.ok || !ret.steps) return Infinity;
                full = outbound.concat(ret.steps);
            }
            full = window.insertDetoursIntoRoute(
                full, startId, viaDetours, pool, modes
            );
            return window.routeStepsDurationMin(full, pool);
        };

        let best = null;
        let bestMin = Infinity;
        candidates.forEach(cand => {
            const m = score(cand);
            if (m < bestMin) {
                bestMin = m;
                best = cand;
            }
        });
        return best || candidates[0];
    };

    /**
     * Schnellste Tour Start→Ziel (+ Form); Zwischenziele 1× beim ersten Abzweigungs-Treffer.
     */
    window.findFastestRoute = function (options) {
        options = options || {};
        const plan = window.GUEST_PLAN || {};
        const startId = options.startNodeId != null ? Number(options.startNodeId) : Number(plan.startNodeId);
        const goalId = options.goalNodeId != null ? Number(options.goalNodeId) : Number(plan.goalNodeId);
        const vias = (options.vias != null ? options.vias : (plan.vias || [])).map(Number);
        const tripShape = options.tripShape || plan.tripShape || 'out_and_back';
        const modes = options.modes || (window.getActiveModes ? window.getActiveModes() : []);
        const budgets = options.budgets || window.getModeBudgets();
        const pool = window.ALL_SEGMENTS || [];

        if (!Number.isFinite(startId)) {
            return { ok: false, message: 'Bitte Startpunkt wählen.' };
        }
        if (!Number.isFinite(goalId)) {
            return { ok: false, message: 'Bitte Zielpunkt wählen.' };
        }

        const keepMap = () => {
            if (typeof window.syncGuestRouteUi === 'function') window.syncGuestRouteUi();
            if (typeof window.renderHikes === 'function') {
                window.renderHikes({ updateMap: true, fitBounds: false });
            }
        };

        const fail = (message) => {
            window.GUEST_ROUTE = { startNodeId: startId, steps: [] };
            window.GUEST_PLAN.lastHint = message;
            keepMap();
            return { ok: false, message };
        };

        let routeGoal = goalId;
        const goalDetour = window.resolveDeadEndDetour(goalId);
        if (goalDetour && goalDetour.deadEnd) {
            routeGoal = goalDetour.entryId;
        }

        const viaDetours = [];
        vias.forEach(vid => {
            if (Number(vid) === Number(routeGoal) || Number(vid) === Number(startId)) return;
            if (goalDetour && Number(vid) === Number(goalDetour.tipId)) return;
            const d = window.resolveViaDetour(vid);
            if (d) viaDetours.push(d);
        });

        let outbound = window.pickFastestOutboundForVias(
            startId, routeGoal, viaDetours, pool, modes, tripShape
        );
        if (!outbound) {
            return fail('Keine Verbindung zwischen den Punkten gefunden.');
        }

        // Ziel-Sackgasse: einmal am Ende des Hinwegs (vor Rückweg/Rundweg)
        if (goalDetour && goalDetour.deadEnd) {
            outbound = window.insertDetoursIntoRoute(
                outbound, startId, [goalDetour], pool, modes
            );
        }

        const corridorSteps = outbound.filter(s => !s.deadEndSpur && !s.deadEndApproach);
        let allSteps = outbound.slice();
        let shapeNote = '';
        const tipAfterOutbound = (() => {
            for (let i = outbound.length - 1; i >= 0; i--) {
                if (outbound[i].deadEndSpur || outbound[i].deadEndApproach) continue;
                return Number(outbound[i].toNodeId);
            }
            return routeGoal;
        })();

        const forbidCorridor = new Set(corridorSteps.map(s => Number(s.segmentId)));

        if (tripShape === 'out_and_back') {
            const back = window.reverseRouteSteps(corridorSteps);
            if (!back) {
                return fail('Hin+Zurück nicht möglich (fehlende Retour-Segmente).');
            }
            allSteps = outbound.concat(back);
        } else if (tripShape === 'loop') {
            if (tipAfterOutbound !== startId) {
                let returnLeg = window.dijkstraFastestPath(tipAfterOutbound, startId, {
                    pool,
                    modes,
                    forbiddenSegIds: Array.from(forbidCorridor),
                });
                if (!returnLeg) {
                    returnLeg = window.dijkstraFastestPath(tipAfterOutbound, startId, {
                        pool,
                        modes,
                        forbiddenSegIds: [],
                    });
                    if (returnLeg) {
                        const rev = window.reverseRouteSteps(corridorSteps);
                        const same = rev && rev.length === returnLeg.steps.length
                            && rev.every((s, i) => Number(s.segmentId) === Number(returnLeg.steps[i].segmentId));
                        if (same) returnLeg = null;
                        else shapeNote = 'Rundweg mit teilweiser Streckenüberschneidung.';
                    }
                }
                if (returnLeg && returnLeg.steps.length) {
                    const loopReversed = !!(plan.loopReversed);
                    if (loopReversed) {
                        // Richtung umkehren: alter Rückweg wird Hinweg
                        const newOut = window.reverseRouteSteps(returnLeg.steps);
                        const newBack = window.reverseRouteSteps(corridorSteps);
                        if (!newOut || !newBack) {
                            return fail('Richtung umkehren nicht möglich (fehlende Retour-Segmente).');
                        }
                        // Ziel-Sackgasse am neuen Hinweg-Ende erneut einfügen
                        let flipped = newOut.slice();
                        if (goalDetour && goalDetour.deadEnd) {
                            flipped = window.insertDetoursIntoRoute(
                                flipped, startId, [goalDetour], pool, modes
                            );
                        }
                        allSteps = flipped.concat(newBack);
                        shapeNote = shapeNote || 'Rundweg (Richtung umgekehrt).';
                    } else {
                        allSteps = outbound.concat(returnLeg.steps);
                    }
                } else {
                    const back = window.reverseRouteSteps(corridorSteps);
                    if (!back) {
                        return fail('Rundweg und Hin+Zurück nicht möglich.');
                    }
                    allSteps = outbound.concat(back);
                    shapeNote = 'Kein alternativer Rückweg — Hin+Zurück verwendet.';
                }
            }
        }

        // Zwischenziel: nur beim 1. Erreichen der Abzweigung (auch auf dem Rundweg)
        allSteps = window.insertDetoursIntoRoute(
            allSteps, startId, viaDetours, pool, modes
        );

        const modeBySegId = new Map();
        allSteps.forEach(s => modeBySegId.set(Number(s.segmentId), s.mode));
        const edgeIds = allSteps.map(s => Number(s.segmentId));
        const byId = new Map(pool.map(s => [Number(s.id), s]));
        if (typeof window.pathClosesWheeledOnEdgeList === 'function') {
            if (!window.pathClosesWheeledOnEdgeList(edgeIds, byId, pool, modes, modeBySegId)) {
                return fail('Rad/E-Bike muss zurückgeholt werden — Route unvollständig.');
            }
        }

        if (!window.routeFitsModeBudgets(allSteps, budgets)) {
            return fail('Route sprengt die km/Hm-Budgets einer Sportart.');
        }

        window.GUEST_ROUTE = { startNodeId: startId, steps: allSteps };
        window.GUEST_PLAN.lastHint = shapeNote || '';
        keepMap();
        return {
            ok: true,
            message: shapeNote || '',
            steps: allSteps,
        };
    };

    window.resolveViaWaypoint = function (nodeId) {
        const d = window.resolveDeadEndDetour(nodeId);
        if (d) {
            return {
                waypointId: d.entryId,
                spurSeg: d.spurSeg,
                tipNodeId: d.tipId,
                deadEnd: true,
            };
        }
        return { waypointId: Number(nodeId), spurSeg: null, tipNodeId: null, deadEnd: false };
    };

    window.setPlannerStart = function (nodeId) {
        window.GUEST_PLAN.startNodeId = Number(nodeId);
        window.GUEST_PLAN.preferredMode = null;
        window.GUEST_ROUTE = { startNodeId: Number(nodeId), steps: [] };
        window.syncPlannerUi();
        window.maybeAutoComputeRoute();
    };

    window.setPlannerGoal = function (nodeId) {
        window.GUEST_PLAN.goalNodeId = Number(nodeId);
        window.syncPlannerUi();
        window.maybeAutoComputeRoute();
    };

    window.addPlannerVia = function (nodeId) {
        const id = Number(nodeId);
        if (!window.GUEST_PLAN.vias) window.GUEST_PLAN.vias = [];
        if (window.GUEST_PLAN.vias.some(v => Number(v) === id)) return;
        window.GUEST_PLAN.vias.push(id);
        window.syncPlannerUi();
        window.maybeAutoComputeRoute();
    };

    window.removePlannerVia = function (nodeId) {
        const id = Number(nodeId);
        window.GUEST_PLAN.vias = (window.GUEST_PLAN.vias || []).filter(v => Number(v) !== id);
        window.syncPlannerUi();
        window.maybeAutoComputeRoute();
    };

    window.setPlannerTripShape = function (shape) {
        window.GUEST_PLAN.tripShape = shape || 'out_and_back';
        if (window.GUEST_PLAN.tripShape !== 'loop') {
            window.GUEST_PLAN.loopReversed = false;
        }
        window.syncPlannerUi();
        window.maybeAutoComputeRoute();
    };

    window.togglePlannerLoopDirection = function () {
        if ((window.GUEST_PLAN.tripShape || '') !== 'loop') return;
        window.GUEST_PLAN.loopReversed = !window.GUEST_PLAN.loopReversed;
        window.syncPlannerUi();
        window.maybeAutoComputeRoute();
    };

    window.maybeAutoComputeRoute = function () {
        const p = window.GUEST_PLAN;
        if (p.startNodeId == null || p.goalNodeId == null) {
            window.syncPlannerUi();
            return;
        }
        const result = window.findFastestRoute();
        const status = document.getElementById('tours-plan-status');
        if (status) status.textContent = result.message || '';
    };

    window.computePlannerRoute = function () {
        const result = window.findFastestRoute();
        const status = document.getElementById('tours-plan-status');
        if (status) status.textContent = result.message || '';
        return result;
    };

    window.clearPlanner = function () {
        window.GUEST_PLAN = {
            startNodeId: null,
            goalNodeId: null,
            vias: [],
            tripShape: window.GUEST_PLAN.tripShape || 'out_and_back',
            loopReversed: false,
            lastHint: '',
            preferredMode: null,
            loadedSavedId: null,
        };
        window.GUEST_ROUTE = { startNodeId: null, steps: [] };
        if (typeof window.setLoadedRouteGlow === 'function') {
            window.setLoadedRouteGlow(false);
        }
        window.syncPlannerUi();
        if (typeof window.syncGuestRouteUi === 'function') window.syncGuestRouteUi();
        if (typeof window.syncSavedRoutesUi === 'function') window.syncSavedRoutesUi();
        if (typeof window.renderHikes === 'function') {
            window.renderHikes({ updateMap: true, fitBounds: false });
        }
    };

    window.syncPlannerUi = function () {
        const p = window.GUEST_PLAN || {};
        const nodes = window.TOUR_NODES || [];
        const startEl = document.getElementById('tours-plan-start');
        const goalEl = document.getElementById('tours-plan-goal');
        const viasEl = document.getElementById('tours-plan-vias');
        const start = nodes.find(n => Number(n.id) === Number(p.startNodeId));
        const goal = nodes.find(n => Number(n.id) === Number(p.goalNodeId));
        if (startEl) startEl.textContent = start ? start.name : '— tippen —';
        if (goalEl) goalEl.textContent = goal ? goal.name : '— tippen —';
        if (viasEl) {
            const vias = p.vias || [];
            if (!vias.length) {
                viasEl.innerHTML = '<span class="tours-plan-via-empty">Keine Zwischenziele</span>';
            } else {
                viasEl.innerHTML = vias.map(vid => {
                    const n = nodes.find(x => Number(x.id) === Number(vid));
                    const name = n ? n.name : ('#' + vid);
                    return `<span class="tours-plan-via-chip" data-via="${vid}">${name}`
                        + `<button type="button" class="tours-plan-via-remove" data-via="${vid}" aria-label="Entfernen">×</button></span>`;
                }).join('');
                viasEl.querySelectorAll('.tours-plan-via-remove').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        window.removePlannerVia(btn.getAttribute('data-via'));
                    });
                });
            }
        }
        document.querySelectorAll('input[name="tours-trip-shape"]').forEach(radio => {
            radio.checked = radio.value === (p.tripShape || 'out_and_back');
        });
        if (typeof window.syncSavedRoutesUi === 'function') window.syncSavedRoutesUi();
    };

    window.TOUR_MODE_ICON_URLS = {
        hike: '/icons/mode-hike.svg',
        bike: '/icons/mode-bike.svg',
        ebike: '/icons/mode-ebike.svg',
    };

    window.modeIconHtml = function (mode, cls) {
        const url = (window.TOUR_MODE_ICON_URLS && window.TOUR_MODE_ICON_URLS[mode]) || '';
        const label = (window.TOUR_MODE_LABELS && window.TOUR_MODE_LABELS[mode]) || mode;
        if (!url) return `<span class="${cls || ''}">${label}</span>`;
        return `<img src="${url}" alt="${label}" title="${label}" class="tours-mode-icon ${cls || ''}" width="28" height="28" decoding="async">`;
    };

    /** Maxima je Mode: längste gültige Tour nur mit Segmenten dieser Sportart. */
    window.maxModeTotalsFromPool = function (segments) {
        const out = {};
        const allModes = window.getActiveModes
            ? window.getActiveModes()
            : ['hike', 'bike', 'ebike'];

        allModes.forEach(mode => {
            const pool = (segments || []).filter(seg =>
                window.segmentMatchingModes(seg, [mode]).length > 0
            );
            if (!pool.length) {
                out[mode] = { km: 1, hm: 50 };
                return;
            }
            let segMaxKm = 0;
            let segMaxHm = 0;
            pool.forEach(s => {
                const e = window.segmentEffectiveStats(s);
                segMaxKm = Math.max(segMaxKm, e.km);
                segMaxHm = Math.max(segMaxHm, e.hm);
            });
            const tour = typeof window.maxTourTotalsFromStarts === 'function'
                ? window.maxTourTotalsFromStarts(pool)
                : { km: segMaxKm, hm: segMaxHm };
            // Kein künstlicher ×12-Faktor — realistische Tour-Obergrenze
            out[mode] = {
                km: Math.max(segMaxKm, Number(tour.km) || 0, 0.5),
                hm: Math.max(segMaxHm, Number(tour.hm) || 0, 10),
            };
            // Auf sinnvolle Slider-Schritte runden
            out[mode].km = Math.ceil(out[mode].km * 2) / 2;
            out[mode].hm = Math.ceil(out[mode].hm / 10) * 10;
        });
        return out;
    };

    window.renderModeBudgetSliders = function () {
        const host = document.getElementById('tours-mode-budgets');
        if (!host) return;
        const modes = window.getActiveModes ? window.getActiveModes() : [];
        const maxima = window.maxModeTotalsFromPool(window.ALL_SEGMENTS || []);
        const prev = {};
        host.querySelectorAll('[data-budget-mode]').forEach(block => {
            const mode = block.getAttribute('data-budget-mode');
            prev[mode] = {
                kmMin: parseFloat(block.querySelector('.budget-km-min')?.value),
                kmMax: parseFloat(block.querySelector('.budget-km-max')?.value),
                hmMin: parseFloat(block.querySelector('.budget-hm-min')?.value),
                hmMax: parseFloat(block.querySelector('.budget-hm-max')?.value),
            };
        });

        host.innerHTML = modes.map(mode => {
            const label = (window.TOUR_MODE_LABELS && window.TOUR_MODE_LABELS[mode]) || mode;
            const color = (window.TOUR_MODE_COLORS && window.TOUR_MODE_COLORS[mode]) || '#3d9b6a';
            const maxKm = (maxima[mode] && maxima[mode].km) || 40;
            const maxHm = (maxima[mode] && maxima[mode].hm) || 2000;
            const kmMin = Number.isFinite(prev[mode]?.kmMin) ? Math.min(prev[mode].kmMin, maxKm) : 0;
            const kmMax = Number.isFinite(prev[mode]?.kmMax) ? Math.min(prev[mode].kmMax, maxKm) : maxKm;
            const hmMin = Number.isFinite(prev[mode]?.hmMin) ? Math.min(prev[mode].hmMin, maxHm) : 0;
            const hmMax = Number.isFinite(prev[mode]?.hmMax) ? Math.min(prev[mode].hmMax, maxHm) : maxHm;
            const icon = window.modeIconHtml(mode, 'tours-mode-budget-icon');
            return `<div class="tours-mode-budget" data-budget-mode="${mode}" style="--mode-color:${color}">`
                + `<div class="tours-mode-budget-head">${icon}<span class="tours-mode-budget-title-sr">${label}</span></div>`
                + `<div class="tours-mode-budget-sliders">`
                + `<div class="range-group tours-budget-range">`
                + `<div class="filter-label">km <span id="budget-${mode}-km-min-label">${kmMin}</span>–<span id="budget-${mode}-km-max-label">${kmMax}</span></div>`
                + `<div class="slider-row">`
                + `<div class="slider-wrapper" id="budget-${mode}-km-wrap">`
                + `<input type="range" id="budget-${mode}-km-min" class="dual-range budget-km-min" data-type="min" min="0" max="${maxKm}" value="${kmMin}" step="0.5">`
                + `<input type="range" id="budget-${mode}-km-max" class="dual-range budget-km-max" data-type="max" min="0" max="${maxKm}" value="${kmMax}" step="0.5">`
                + `</div></div></div>`
                + `<div class="range-group tours-budget-range">`
                + `<div class="filter-label">Hm <span id="budget-${mode}-hm-min-label">${Math.round(hmMin)}</span>–<span id="budget-${mode}-hm-max-label">${Math.round(hmMax)}</span></div>`
                + `<div class="slider-row">`
                + `<div class="slider-wrapper" id="budget-${mode}-hm-wrap">`
                + `<input type="range" id="budget-${mode}-hm-min" class="dual-range budget-hm-min" data-type="min" min="0" max="${maxHm}" value="${hmMin}" step="10">`
                + `<input type="range" id="budget-${mode}-hm-max" class="dual-range budget-hm-max" data-type="max" min="0" max="${maxHm}" value="${hmMax}" step="10">`
                + `</div></div></div>`
                + `</div></div>`;
        }).join('');

        let timer = null;
        const schedule = () => {
            if (timer) clearTimeout(timer);
            timer = setTimeout(() => {
                if (typeof window.renderHikes === 'function') {
                    window.renderHikes({ updateMap: true, fitBounds: false });
                }
                window.maybeAutoComputeRoute();
            }, 100);
        };

        host.querySelectorAll('[data-budget-mode]').forEach(block => {
            const mode = block.getAttribute('data-budget-mode');
            [['km', 0.5], ['hm', 10]].forEach(([kind]) => {
                const iMin = block.querySelector(`.budget-${kind}-min`);
                const iMax = block.querySelector(`.budget-${kind}-max`);
                const wrap = document.getElementById(`budget-${mode}-${kind}-wrap`);
                const lMin = document.getElementById(`budget-${mode}-${kind}-min-label`);
                const lMax = document.getElementById(`budget-${mode}-${kind}-max-label`);
                if (!iMin || !iMax) return;
                const maxVal = parseFloat(iMax.max);
                const minVal = parseFloat(iMin.min);

                function update() {
                    let vMin = parseFloat(iMin.value);
                    let vMax = parseFloat(iMax.value);
                    if (vMin > vMax) { vMin = vMax; iMin.value = vMin; }
                    if (vMax < vMin) { vMax = vMin; iMax.value = vMax; }
                    if (lMin) lMin.textContent = kind === 'hm' ? Math.round(vMin) : vMin;
                    if (lMax) lMax.textContent = kind === 'hm' ? Math.round(vMax) : vMax;
                    const range = maxVal - minVal;
                    const perMin = range === 0 ? 0 : ((vMin - minVal) / range) * 100;
                    const perMax = range === 0 ? 100 : ((vMax - minVal) / range) * 100;
                    if (wrap) {
                        wrap.style.setProperty('--a', perMin + '%');
                        wrap.style.setProperty('--b', perMax + '%');
                    }
                    schedule();
                }
                iMin.addEventListener('input', update);
                iMax.addEventListener('input', update);
                update();
            });
        });
    };

    window.initPlannerUi = function () {
        window.renderModeBudgetSliders();
        window.syncPlannerUi();

        document.querySelectorAll('input[name="tours-trip-shape"]').forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.checked) window.setPlannerTripShape(radio.value);
            });
        });
        const computeBtn = document.getElementById('tours-plan-compute');
        if (computeBtn) {
            computeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                window.computePlannerRoute();
            });
        }
        const takeawayBtn = document.getElementById('tours-plan-takeaway');
        if (takeawayBtn) {
            takeawayBtn.addEventListener('click', (e) => {
                e.preventDefault();
                window.openPlannedRouteTakeaway();
            });
        }
        const takeawayClose = document.getElementById('tours-takeaway-close');
        if (takeawayClose) {
            takeawayClose.addEventListener('click', (e) => {
                e.preventDefault();
                window.closePlannedRouteTakeaway();
            });
        }
        const lanApply = document.getElementById('tours-takeaway-lan-apply');
        if (lanApply) {
            lanApply.addEventListener('click', (e) => {
                e.preventDefault();
                const input = document.getElementById('tours-takeaway-lan-input');
                const raw = (input && input.value || '').trim().replace(/\/+$/, '');
                if (!/^https?:\/\//i.test(raw)) {
                    const status = document.getElementById('tours-takeaway-status');
                    if (status) status.textContent = 'Bitte mit http:// beginnen, z. B. http://192.168.1.4:8000';
                    return;
                }
                try { localStorage.setItem('hausMeliShareOrigin', raw); } catch (err) { /* ignore */ }
                window.openPlannedRouteTakeaway();
            });
        }
        const overlay = document.getElementById('tours-takeaway-overlay');
        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) window.closePlannedRouteTakeaway();
            });
        }
        const loopRev = document.getElementById('tours-plan-loop-reverse');
        if (loopRev) {
            loopRev.addEventListener('click', (e) => {
                e.preventDefault();
                window.togglePlannerLoopDirection();
            });
        }
        const saveBtn = document.getElementById('tours-plan-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                window.saveCurrentGuestRoute();
            });
        }
        window.syncSavedRoutesUi();
        window.pullSavedGuestRoutes();
        if (!window._savedRoutesVisibilityBound) {
            window._savedRoutesVisibilityBound = true;
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') window.pullSavedGuestRoutes();
            });
        }
    };

    window.syncTakeawayButton = function () {
        const saveBtn = document.getElementById('tours-plan-save');
        const steps = (window.GUEST_ROUTE && window.GUEST_ROUTE.steps) || [];
        const has = steps.length > 0;
        if (saveBtn) saveBtn.hidden = !has;
    };

    window.closePlannedRouteTakeaway = function () {
        const overlay = document.getElementById('tours-takeaway-overlay');
        if (overlay) overlay.hidden = true;
    };

    window.getCsrfHeaders = function () {
        const csrf = (window.APP && window.APP.csrfToken)
            || (document.querySelector('meta[name="csrf-token"]') || {}).content
            || '';
        let xsrf = '';
        try {
            const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
            if (m) xsrf = decodeURIComponent(m[1]);
        } catch (e) { /* ignore */ }
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (csrf) headers['X-CSRF-TOKEN'] = csrf;
        if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
        return headers;
    };

    window.nodeNameById = function (nodeId) {
        const id = Number(nodeId);
        if (!Number.isFinite(id)) return '';
        const n = (window.TOUR_NODES || []).find(x => Number(x.id) === id);
        return n && n.name ? String(n.name).trim() : ('Punkt #' + id);
    };

    /**
     * Speichern-Vorschlag: Start → Via… → Ziel → Start(falls zurück) / Sportarten
     */
    window.buildGuestRouteSaveNameSuggestion = function () {
        const plan = window.GUEST_PLAN || {};
        const r = window.GUEST_ROUTE || {};
        const startId = plan.startNodeId != null ? plan.startNodeId : r.startNodeId;
        const goalId = plan.goalNodeId;
        const vias = Array.isArray(plan.vias) ? plan.vias : [];
        const shape = plan.tripShape || 'out_and_back';

        const parts = [];
        const startName = window.nodeNameById(startId);
        if (startName) parts.push(startName);

        vias.forEach(vid => {
            if (Number(vid) === Number(startId) || Number(vid) === Number(goalId)) return;
            const name = window.nodeNameById(vid);
            if (!name) return;
            if (parts[parts.length - 1] === name) return;
            parts.push(name);
        });

        const goalName = window.nodeNameById(goalId);
        if (goalName && parts[parts.length - 1] !== goalName) {
            parts.push(goalName);
        }

        const returnsToStart = shape === 'out_and_back' || shape === 'loop'
            || (r.steps && r.steps.length && startId != null && typeof window.getRouteTipNodeId === 'function'
                && Number(window.getRouteTipNodeId()) === Number(startId));
        if (returnsToStart && startName && parts[parts.length - 1] !== startName) {
            parts.push(startName);
        }

        const modeOrder = ['hike', 'ebike', 'bike', 'sled', 'ski', 'cable'];
        const modeSet = new Set();
        (r.steps || []).forEach(step => {
            if (step && step.mode) modeSet.add(step.mode);
        });
        const modeLabels = modeOrder
            .filter(m => modeSet.has(m))
            .concat(Array.from(modeSet).filter(m => !modeOrder.includes(m)))
            .map(m => (window.TOUR_MODE_LABELS && window.TOUR_MODE_LABELS[m]) || m);

        let title = parts.length ? parts.join(' → ') : 'Gespeicherte Tour';
        if (modeLabels.length) {
            title += ' / ' + modeLabels.join('+');
        }
        return title;
    };

    window.buildPlannedRouteSharePayload = function () {
        const r = window.GUEST_ROUTE || {};
        const steps = (r.steps || []).map(s => ({
            segmentId: Number(s.segmentId),
            reversed: !!s.reversed,
            mode: s.mode || null,
        }));
        const tot = typeof window.getGuestRouteTotals === 'function'
            ? window.getGuestRouteTotals()
            : { km: 0, hm: 0, count: 0 };
        const dur = typeof window.getGuestRouteDurationMin === 'function'
            ? window.getGuestRouteDurationMin()
            : 0;
        const nodes = window.TOUR_NODES || [];
        const plan = window.GUEST_PLAN || {};
        const title = window.buildGuestRouteSaveNameSuggestion();

        const stages = [];
        const seen = new Set();
        const pushStage = (nodeId) => {
            const id = Number(nodeId);
            if (!Number.isFinite(id) || seen.has(id)) return;
            seen.add(id);
            const n = nodes.find(x => Number(x.id) === id);
            stages.push(n ? n.name : ('Punkt #' + id));
        };
        if (r.startNodeId) pushStage(r.startNodeId);
        (r.steps || []).forEach(step => {
            const seg = (window.ALL_SEGMENTS || []).find(s => Number(s.id) === Number(step.segmentId));
            const endId = typeof window.routeStepEndNodeId === 'function'
                ? window.routeStepEndNodeId(step, seg)
                : step.toNodeId;
            pushStage(endId);
        });

        return {
            startNodeId: r.startNodeId != null ? Number(r.startNodeId) : null,
            steps: steps.map(s => ({
                segmentId: s.segmentId,
                reversed: s.reversed,
            })),
            meta: {
                title,
                km: Number((tot.km || 0).toFixed ? (tot.km || 0).toFixed(2) : tot.km) || 0,
                hm: Math.round(tot.hmUp || tot.hm || 0),
                hmUp: Math.round(tot.hmUp || tot.hm || 0),
                hmDown: Math.round(tot.hmDown || 0),
                duration_min: Math.round(dur || 0),
                stages,
            },
        };
    };

    window.isPhoneUnreachableHost = function (hostname) {
        const h = String(hostname || '').toLowerCase();
        if (!h) return true;
        if (h === 'localhost' || h === '127.0.0.1' || h === '::1') return true;
        // Herd / Valet / mDNS — Handy kennt die Namen nicht
        if (h.endsWith('.test') || h.endsWith('.local') || h.endsWith('.localhost')) return true;
        return false;
    };

    window.getSharePublicOrigin = function () {
        const fromEnv = (window.APP && window.APP.sharePublicOrigin) || '';
        if (fromEnv) return String(fromEnv).replace(/\/+$/, '');
        try {
            const stored = localStorage.getItem('hausMeliShareOrigin');
            if (stored && /^https?:\/\//i.test(stored)) return stored.replace(/\/+$/, '');
        } catch (e) { /* ignore */ }
        if (!window.isPhoneUnreachableHost(window.location.hostname)) {
            return window.location.origin;
        }
        return '';
    };

    window.openPlannedRouteTakeaway = async function () {
        const overlay = document.getElementById('tours-takeaway-overlay');
        const status = document.getElementById('tours-takeaway-status');
        const qrImg = document.getElementById('tours-takeaway-qr');
        const linkEl = document.getElementById('tours-takeaway-link');
        const lanBox = document.getElementById('tours-takeaway-lan');
        const lanInput = document.getElementById('tours-takeaway-lan-input');
        const endpoint = (window.APP && window.APP.plannedShareUrl)
            || (window.location.origin + '/tours/planned/share');
        const shareEndpoint = endpoint.startsWith('http')
            ? endpoint
            : (window.location.origin + (endpoint.startsWith('/') ? endpoint : '/' + endpoint));

        if (!overlay) return;
        const payload = window.buildPlannedRouteSharePayload();
        if (!payload.steps.length) {
            if (status) status.textContent = 'Keine Route zum Mitnehmen.';
            return;
        }

        overlay.hidden = false;
        const needsLan = window.isPhoneUnreachableHost(window.location.hostname)
            && !((window.APP && window.APP.sharePublicOrigin) || '');
        if (lanBox) lanBox.hidden = !needsLan;
        if (needsLan && lanInput) {
            try {
                lanInput.value = localStorage.getItem('hausMeliShareOrigin') || lanInput.value || '';
            } catch (e) { /* ignore */ }
        }

        const publicOrigin = window.getSharePublicOrigin();
        if (needsLan && !publicOrigin) {
            if (status) {
                status.textContent = 'QR braucht eine WLAN-Adresse des PCs — bitte oben eintragen (serve.bat + ipconfig).';
            }
            if (qrImg) {
                qrImg.removeAttribute('src');
                qrImg.alt = 'LAN-Adresse fehlt';
            }
            if (linkEl) {
                linkEl.hidden = true;
                linkEl.href = '#';
                linkEl.textContent = '';
            }
            return;
        }

        if (status) status.textContent = 'Link wird erzeugt…';
        if (qrImg) {
            qrImg.removeAttribute('src');
            qrImg.alt = 'QR-Code wird geladen';
        }
        if (linkEl) {
            linkEl.hidden = true;
            linkEl.href = '#';
            linkEl.textContent = '';
        }

        try {
            const res = await fetch(shareEndpoint, {
                method: 'POST',
                headers: Object.assign({
                    'Content-Type': 'application/json',
                }, window.getCsrfHeaders()),
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            let data = {};
            const raw = await res.text();
            try { data = raw ? JSON.parse(raw) : {}; } catch (e) { data = {}; }
            if (!res.ok || !(data.token || data.share_url)) {
                const detail = (data.errors && Object.values(data.errors).flat().join(' '))
                    || data.message
                    || ('HTTP ' + res.status);
                throw new Error(detail);
            }
            // QR/Link: LAN-Origin falls Herd/.test/localhost — Handy kann die nicht auflösen
            const base = publicOrigin || window.location.origin;
            const shareUrl = data.token
                ? (base + '/tours/share/' + data.token)
                : String(data.share_url || '').replace(window.location.origin, base);
            if (qrImg) {
                qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&ecc=M&data='
                    + encodeURIComponent(shareUrl);
                qrImg.alt = 'QR-Code zur Route';
            }
            if (linkEl) {
                linkEl.hidden = false;
                linkEl.href = shareUrl;
                linkEl.textContent = shareUrl;
            }
            if (status) {
                status.textContent = needsLan || window.isPhoneUnreachableHost(window.location.hostname)
                    ? ('Handy-Link über ' + base + ' · gleiches WLAN · Link ca. '
                        + (data.expires_hours || 48) + ' Std.')
                    : ('Link ca. ' + (data.expires_hours || 48) + ' Stunden gültig · gleiches WLAN wie das Tablet');
            }
        } catch (err) {
            if (status) {
                status.textContent = 'Link fehlgeschlagen: '
                    + ((err && err.message) || 'unbekannter Fehler');
            }
            console.warn('planned route share', err);
        }
    };

    const SAVED_ROUTES_KEY = 'hausMeliSavedRoutes';

    window.listSavedGuestRoutes = function () {
        try {
            const raw = localStorage.getItem(SAVED_ROUTES_KEY);
            const list = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(list)) return [];
            // Frühere Auto-Speicherungen ausblenden und aufräumen
            const manual = list.filter(x => x && !x.auto);
            if (manual.length !== list.length) {
                window.persistSavedGuestRoutes(manual);
            }
            return manual;
        } catch (e) {
            return [];
        }
    };

    window.persistSavedGuestRoutes = function (list) {
        try {
            localStorage.setItem(SAVED_ROUTES_KEY, JSON.stringify(list || []));
        } catch (e) {
            console.warn('save routes', e);
        }
    };

    // Gespeicherte Touren liegen zusätzlich auf dem Server, damit das Handy sie
    // anzeigen kann. Der Server ist maßgeblich; lokale Touren, die dort fehlen
    // und schon einmal übertragen waren, wurden anderswo gelöscht.
    window.mergeSavedRoutes = function (local, server) {
        const byId = new Map();
        const upload = [];
        (server || []).forEach(item => {
            if (item && item.id != null) byId.set(String(item.id), Object.assign({}, item, { synced: true }));
        });
        (local || []).forEach(item => {
            if (!item || item.id == null) return;
            const id = String(item.id);
            if (byId.has(id) || item.synced) return;
            byId.set(id, item);
            upload.push(item);
        });
        const merged = Array.from(byId.values())
            .sort((a, b) => String(b.savedAt || '').localeCompare(String(a.savedAt || '')))
            .slice(0, 40);
        return { merged, upload };
    };

    window.pushSavedGuestRoutes = function (items) {
        if (!items || !items.length) return Promise.resolve();
        return fetch('/tours/saved', {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, window.getCsrfHeaders()),
            credentials: 'same-origin',
            body: JSON.stringify({ routes: items }),
        }).then(res => {
            if (!res.ok) return;
            const ids = new Set(items.map(item => String(item.id)));
            const list = window.listSavedGuestRoutes().map(item => (
                ids.has(String(item.id)) ? Object.assign({}, item, { synced: true }) : item
            ));
            window.persistSavedGuestRoutes(list);
        }).catch(err => console.warn('push saved routes', err));
    };

    window.pullSavedGuestRoutes = function () {
        if (window._savedRoutesPulling) return Promise.resolve();
        window._savedRoutesPulling = true;
        return fetch('/tours/saved', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(res => (res.ok ? res.json() : null))
            .then(data => {
                if (!data || !Array.isArray(data.routes)) return;
                const result = window.mergeSavedRoutes(window.listSavedGuestRoutes(), data.routes);
                window.persistSavedGuestRoutes(result.merged);
                if (result.upload.length) window.pushSavedGuestRoutes(result.upload);
                window.syncSavedRoutesUi();
            })
            .catch(err => console.warn('pull saved routes', err))
            .finally(() => { window._savedRoutesPulling = false; });
    };

    window.deleteSavedGuestRouteRemote = function (id) {
        return fetch('/tours/saved/' + encodeURIComponent(id), {
            method: 'DELETE',
            headers: window.getCsrfHeaders(),
            credentials: 'same-origin',
        }).catch(err => console.warn('delete saved route', err));
    };

    window.escapeHtmlText = function (s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    };

    window.localizedText = function (obj) {
        if (!obj) return '';
        if (typeof obj === 'string') return obj;
        const lang = (typeof APP !== 'undefined' && APP.lang === 'en') ? 'en' : 'de';
        return String(obj[lang] || obj.de || obj.en || '').trim();
    };

    window.buildSavedTourItineraryHtml = function (item) {
        const nodes = window.TOUR_NODES || [];
        const pool = window.ALL_SEGMENTS || [];
        const steps = (item && item.route && item.route.steps) || [];
        const startId = item && item.route && item.route.startNodeId != null
            ? Number(item.route.startNodeId)
            : (item && item.plan ? Number(item.plan.startNodeId) : null);
        const esc = window.escapeHtmlText;
        const imgUrl = (src) => (typeof window.tourImageUrl === 'function' ? window.tourImageUrl(src) : src);

        const renderPhotos = (images, key) => {
            const list = (images || []).filter(Boolean);
            if (!list.length) return '';
            return '<div class="tours-saved-photos" data-photos="' + esc(key) + '">'
                + list.map((src, i) => {
                    const url = esc(imgUrl(src));
                    return `<button type="button" class="tours-saved-photo" data-photo-i="${i}" data-photo-src="${url}">`
                        + `<img src="${url}" alt="" loading="lazy"></button>`;
                }).join('')
                + '</div>';
        };

        const renderNode = (nodeId) => {
            const n = nodes.find(x => Number(x.id) === Number(nodeId));
            if (!n) {
                return `<section class="tours-saved-block tours-saved-block--node">`
                    + `<h4 class="tours-saved-block-title">Punkt #${esc(nodeId)}</h4></section>`;
            }
            const desc = window.localizedText(n.description) || String(n.notes || '').trim();
            const photos = renderPhotos(n.images, 'node-' + n.id);
            return `<section class="tours-saved-block tours-saved-block--node">`
                + `<div class="tours-saved-block-head">`
                + `<div class="tours-saved-block-text">`
                + `<h4 class="tours-saved-block-title">${esc(n.name)}</h4>`
                + (desc ? `<p class="tours-saved-block-desc">${esc(desc)}</p>` : '')
                + `</div>`
                + photos
                + `</div>`
                + `</section>`;
        };

        const renderSeg = (step) => {
            const seg = pool.find(s => Number(s.id) === Number(step.segmentId));
            if (!seg) {
                return `<section class="tours-saved-block tours-saved-block--seg">`
                    + `<h4 class="tours-saved-block-title">Segment #${esc(step.segmentId)}</h4></section>`;
            }
            const modes = step.mode ? [step.mode] : (typeof window.getActiveModes === 'function' ? window.getActiveModes() : ['hike']);
            const profile = typeof window.segmentDisplayProfile === 'function'
                ? window.segmentDisplayProfile(seg, modes)
                : null;
            const desc = window.localizedText(profile && profile.description);
            const km = Number(seg.distance_km);
            const ad = typeof window.segmentAscentDescent === 'function'
                ? window.segmentAscentDescent(seg)
                : { ascent: Number(seg.elevation_m) || 0, descent: 0 };
            const metaBits = [];
            if (Number.isFinite(km) && km > 0) metaBits.push(km.toFixed(1) + ' km');
            if (ad.ascent > 0 || ad.descent > 0) {
                metaBits.push(window.formatHmUpDown(ad.ascent, ad.descent));
            }
            if (step.mode) {
                const labels = window.TOUR_MODE_LABELS || { hike: 'Wandern', bike: 'Rad', ebike: 'E-Bike' };
                metaBits.push(labels[step.mode] || step.mode);
            }
            const photos = renderPhotos(seg.images, 'seg-' + seg.id);
            return `<section class="tours-saved-block tours-saved-block--seg">`
                + `<div class="tours-saved-block-head">`
                + `<div class="tours-saved-block-text">`
                + `<h4 class="tours-saved-block-title">${esc(seg.name || ('Segment #' + seg.id))}</h4>`
                + (metaBits.length ? `<div class="tours-saved-block-meta">${esc(metaBits.join(' · '))}</div>` : '')
                + (desc ? `<p class="tours-saved-block-desc">${esc(desc)}</p>` : '')
                + `</div>`
                + photos
                + `</div>`
                + `</section>`;
        };

        let html = '';
        if (startId != null && Number.isFinite(startId)) html += renderNode(startId);
        steps.forEach(step => {
            html += renderSeg(step);
            const seg = pool.find(s => Number(s.id) === Number(step.segmentId));
            const endId = typeof window.routeStepEndNodeId === 'function'
                ? window.routeStepEndNodeId(step, seg)
                : step.toNodeId;
            if (endId != null) html += renderNode(endId);
        });

        const elevKey = window.routeElevCacheKey(steps);
        if (steps.length) {
            html = `<div class="tours-saved-elev" data-elev-key="${window.escapeHtmlText(elevKey)}">`
                + `<div class="tours-saved-elev-head"><span>Höhenprofil</span><span>…</span></div>`
                + `<div class="tours-saved-elev-loading">Höhenprofil wird geladen… (ein paar Sekunden)</div>`
                + `</div>` + (html || '');
        }
        return html || '';
    };

    window._elevProfileCache = window._elevProfileCache || new Map();
    window._elevPointCache = window._elevPointCache || new Map();

    window.routeElevCacheKey = function (steps) {
        return (steps || []).map(s =>
            String(s.segmentId) + ':' + (s.reversed ? '1' : '0') + ':' + (s.mode || '')
        ).join('|');
    };

    window.haversineM = function (lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const toRad = (d) => d * Math.PI / 180;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(a)));
    };

    /** Trace along route geojson, sample about every `everyM` meters. */
    window.sampleRouteElevationPoints = function (steps, everyM) {
        const pool = window.ALL_SEGMENTS || [];
        const stepM = Math.max(50, Number(everyM) || 100);
        const out = [];
        let distM = 0;
        let lastSampleAt = -stepM;

        const pushSample = (lat, lng, ele, d, mode) => {
            if (d - lastSampleAt < stepM * 0.85 && out.length) return;
            out.push({
                lat,
                lng,
                dKm: d / 1000,
                ele: Number.isFinite(ele) ? ele : null,
                mode: mode || 'hike',
            });
            lastSampleAt = d;
        };

        (steps || []).forEach(step => {
            const seg = pool.find(s => Number(s.id) === Number(step.segmentId));
            if (!seg) return;
            const mode = (window.isSeilbahnSegment && window.isSeilbahnSegment(seg))
                ? 'cable'
                : (step.mode || 'hike');
            let coords = (seg.geojson && seg.geojson.coordinates) || [];
            if (!Array.isArray(coords) || coords.length < 2) return;

            // Richtung aus from/to ableiten (zuverlässiger als step.reversed allein —
            // Retour-Zwillinge sind schon B→A gespeichert und dürfen nicht nochmal gespiegelt werden)
            const segFrom = Number(seg.from_node_id);
            const segTo = Number(seg.to_node_id);
            const stepFrom = step.fromNodeId != null ? Number(step.fromNodeId) : null;
            const stepTo = step.toNodeId != null ? Number(step.toNodeId) : null;
            let reverse = !!step.reversed;
            if (Number.isFinite(stepFrom) && Number.isFinite(stepTo)) {
                if (stepFrom === segFrom && stepTo === segTo) reverse = false;
                else if (stepFrom === segTo && stepTo === segFrom) reverse = true;
            }
            if (reverse) coords = coords.slice().reverse();

            for (let i = 0; i < coords.length; i++) {
                const c = coords[i];
                const lng = Number(c[0]);
                const lat = Number(c[1]);
                const ele = c.length > 2 && Number.isFinite(Number(c[2])) ? Number(c[2]) : null;
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;

                if (i === 0) {
                    if (!out.length) pushSample(lat, lng, ele, distM, mode);
                    continue;
                }
                const prev = coords[i - 1];
                const pLng = Number(prev[0]);
                const pLat = Number(prev[1]);
                const pEle = prev.length > 2 && Number.isFinite(Number(prev[2])) ? Number(prev[2]) : null;
                const segLen = window.haversineM(pLat, pLng, lat, lng);
                if (segLen < 0.5) continue;

                let remain = stepM - (distM - lastSampleAt);
                while (remain < segLen) {
                    const t = remain / segLen;
                    const sLat = pLat + (lat - pLat) * t;
                    const sLng = pLng + (lng - pLng) * t;
                    let sEle = null;
                    if (pEle != null && ele != null) sEle = pEle + (ele - pEle) * t;
                    pushSample(sLat, sLng, sEle, distM + remain, mode);
                    remain += stepM;
                }
                distM += segLen;
            }
            const last = coords[coords.length - 1];
            if (last) {
                const lng = Number(last[0]);
                const lat = Number(last[1]);
                const ele = last.length > 2 && Number.isFinite(Number(last[2])) ? Number(last[2]) : null;
                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    out.push({ lat, lng, dKm: distM / 1000, ele, mode });
                    lastSampleAt = distM;
                }
            }
        });

        return out;
    };

    window.fillElevationGaps = function (pts) {
        if (!pts || !pts.length) return pts;
        const known = [];
        for (let i = 0; i < pts.length; i++) {
            if (Number.isFinite(pts[i].ele)) known.push(i);
        }
        if (!known.length) return pts;

        for (let i = 0; i < known[0]; i++) {
            pts[i].ele = pts[known[0]].ele;
        }
        const lastK = known[known.length - 1];
        for (let i = lastK + 1; i < pts.length; i++) {
            pts[i].ele = pts[lastK].ele;
        }
        for (let k = 0; k < known.length - 1; k++) {
            const a = known[k];
            const b = known[k + 1];
            const ea = pts[a].ele;
            const eb = pts[b].ele;
            const da = pts[a].dKm;
            const db = pts[b].dKm;
            for (let i = a + 1; i < b; i++) {
                if (Number.isFinite(pts[i].ele)) continue;
                const t = (db > da) ? (pts[i].dKm - da) / (db - da) : 0;
                pts[i].ele = ea + (eb - ea) * t;
            }
        }
        return pts;
    };

    /**
     * Echte Geländehöhen über Server-Proxy (OpenTopoData + Cache).
     * Kein Direktaufruf Open-Meteo im Browser (dort oft 429).
     */
    window.fetchRouteElevations = async function (points) {
        const needIdx = [];
        points.forEach((p, idx) => {
            if (Number.isFinite(p.ele)) return;
            const key = p.lat.toFixed(5) + ',' + p.lng.toFixed(5);
            if (window._elevPointCache.has(key)) {
                p.ele = window._elevPointCache.get(key);
                return;
            }
            needIdx.push(idx);
        });
        if (!needIdx.length) {
            return { points: window.fillElevationGaps(points), ok: true };
        }

        const applyElevs = (idxs, elevs) => {
            let applied = 0;
            idxs.forEach((pi, j) => {
                if (elevs[j] == null || elevs[j] === '') return;
                const e = Number(elevs[j]);
                if (!Number.isFinite(e)) return;
                points[pi].ele = e;
                window._elevPointCache.set(
                    points[pi].lat.toFixed(5) + ',' + points[pi].lng.toFixed(5),
                    e
                );
                applied++;
            });
            return applied;
        };

        const csrf = (window.APP && window.APP.csrfToken)
            || (document.querySelector('meta[name="csrf-token"]') || {}).content
            || '';

        // In Chunks von max. 100 (OpenTopoData-Limit), nacheinander
        const CHUNK = 100;
        let appliedTotal = 0;
        for (let i = 0; i < needIdx.length; i += CHUNK) {
            const slice = needIdx.slice(i, i + CHUNK);
            const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
            const timer = ctrl ? setTimeout(() => ctrl.abort(), 45000) : null;
            try {
                const res = await fetch('/tours/elevation', {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: ctrl ? ctrl.signal : undefined,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        latitude: slice.map(pi => points[pi].lat),
                        longitude: slice.map(pi => points[pi].lng),
                    }),
                });
                if (!res.ok) throw new Error('elevation http ' + res.status);
                const data = await res.json();
                appliedTotal += applyElevs(slice, Array.isArray(data.elevation) ? data.elevation : []);
            } catch (err) {
                console.warn('Höhen-Chunk', i / CHUNK + 1, err);
            } finally {
                if (timer) clearTimeout(timer);
            }
        }

        window.fillElevationGaps(points);
        const okCount = points.filter(p => Number.isFinite(p.ele)).length;
        // Nach Interpolation reichen schon ein paar echte Punkte für die ganze Linie
        const ok = appliedTotal >= 2 || okCount >= Math.max(2, Math.floor(points.length * 0.5));
        return { points, ok };
    };

    window.smoothElevationSpikes = function (pts) {
        if (!pts || pts.length < 3) return pts;
        const out = pts.map(p => Object.assign({}, p));
        // Unrealistische Sprünge (DEM-/Lückenfehler) glätten
        for (let pass = 0; pass < 3; pass++) {
            for (let i = 1; i < out.length - 1; i++) {
                const prev = out[i - 1];
                const cur = out[i];
                const next = out[i + 1];
                if (![prev.ele, cur.ele, next.ele].every(Number.isFinite)) continue;
                const dPrevM = Math.max(1, (cur.dKm - prev.dKm) * 1000);
                const dNextM = Math.max(1, (next.dKm - cur.dKm) * 1000);
                const jumpPrev = Math.abs(cur.ele - prev.ele);
                const jumpNext = Math.abs(next.ele - cur.ele);
                // >35 % Steigung über kurzem Stück oder >250 m Sprung → Mittelwert Nachbarn
                if ((jumpPrev > 250 && dPrevM < 200)
                    || (jumpNext > 250 && dNextM < 200)
                    || (jumpPrev / dPrevM > 0.45 && jumpNext / dNextM > 0.45)) {
                    out[i] = Object.assign({}, cur, { ele: (prev.ele + next.ele) / 2 });
                }
            }
        }
        // Leichte Glättung
        for (let pass = 0; pass < 2; pass++) {
            for (let i = 1; i < out.length - 1; i++) {
                const a = out[i - 1].ele;
                const b = out[i].ele;
                const c = out[i + 1].ele;
                if (![a, b, c].every(Number.isFinite)) continue;
                out[i] = Object.assign({}, out[i], { ele: (a + 2 * b + c) / 4 });
            }
        }
        return out;
    };

    window.renderElevationProfileSvg = function (pts, opts) {
        opts = opts || {};
        const VEX = 2;
        if (!pts || pts.length < 2) return '';
        pts = window.smoothElevationSpikes(pts);
        const distKm = pts[pts.length - 1].dKm;
        if (!(distKm > 0)) return '';

        let totalUp = 0;
        let totalDown = 0;
        for (let i = 1; i < pts.length; i++) {
            const a = pts[i - 1].ele;
            const b = pts[i].ele;
            if (!Number.isFinite(a) || !Number.isFinite(b)) continue;
            const d = b - a;
            if (d > 1.5) totalUp += d;
            else if (d < -1.5) totalDown += -d;
        }
        totalUp = Math.round(totalUp);
        totalDown = Math.round(totalDown);
        if (opts.fallbackUp != null && totalUp === 0 && totalDown === 0) {
            totalUp = Math.round(opts.fallbackUp) || 0;
            totalDown = Math.round(opts.fallbackDown) || 0;
        }

        const eles = pts.map(p => p.ele).filter(Number.isFinite);
        if (eles.length < 2) return '';
        const minE = Math.min(...eles);
        const maxE = Math.max(...eles);
        if (maxE < 20 && Math.abs(maxE - minE) < 8) return '';
        const elevSpanM = Math.max(40, maxE - minE);
        const distM = distKm * 1000;

        // Maßstäblich: gleiche Meter auf X/Y × Überhöhung — volle Breite, Höhe folgt
        const plotW = 800;
        const padL = 52;
        const padR = 14;
        const padT = 12;
        const padB = 32;
        let plotH = plotW * ((elevSpanM * VEX) / distM);
        // Nur weiche Grenzen für Lesbarkeit, Proportion bleibt der Maßstab
        plotH = Math.max(72, Math.min(plotH, plotW * 1.1));
        const svgW = plotW + padL + padR;
        const svgH = plotH + padT + padB;

        const xOf = (dKm) => padL + (dKm / distKm) * plotW;
        const yOf = (e) => padT + plotH - ((e - minE) / elevSpanM) * plotH;
        const modeColor = (mode) =>
            (window.TOUR_MODE_COLORS && window.TOUR_MODE_COLORS[mode]) || '#3d9b6a';
        const modeLabel = (mode) =>
            (window.TOUR_MODE_LABELS && window.TOUR_MODE_LABELS[mode]) || mode;

        const valid = pts.filter(p => Number.isFinite(p.ele));
        if (valid.length < 2) return '';

        // Linien + Flächen nach Sportart
        const runs = [];
        valid.forEach((p) => {
            const mode = p.mode || 'hike';
            const last = runs[runs.length - 1];
            if (last && last.mode === mode) last.pts.push(p);
            else runs.push({ mode, pts: [p] });
        });
        for (let i = 0; i < runs.length - 1; i++) {
            const bridge = runs[i + 1].pts[0];
            if (bridge) runs[i].pts.push(bridge);
        }

        let areas = '';
        let lines = '';
        const usedModes = new Set();
        runs.forEach(run => {
            if (run.pts.length < 2) return;
            usedModes.add(run.mode);
            const color = modeColor(run.mode);
            const path = run.pts.map((p, i) =>
                (i === 0 ? 'M' : 'L') + xOf(p.dKm).toFixed(1) + ' ' + yOf(p.ele).toFixed(1)
            ).join(' ');
            const d0 = run.pts[0].dKm;
            const d1 = run.pts[run.pts.length - 1].dKm;
            const area = path
                + ` L${xOf(d1).toFixed(1)} ${(padT + plotH).toFixed(1)}`
                + ` L${xOf(d0).toFixed(1)} ${(padT + plotH).toFixed(1)} Z`;
            areas += `<path d="${area}" fill="${color}" fill-opacity="0.28"/>`;
            lines += `<path d="${path}" fill="none" stroke="${color}" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>`;
        });
        if (!lines) return '';

        const yTicks = 4;
        const xTicks = Math.min(8, Math.max(3, Math.round(distKm)));
        let grid = '';
        for (let i = 0; i <= yTicks; i++) {
            const e = minE + (elevSpanM * i) / yTicks;
            const y = yOf(e);
            grid += `<line class="tours-saved-elev-grid" x1="${padL}" y1="${y.toFixed(1)}" x2="${(padL + plotW).toFixed(1)}" y2="${y.toFixed(1)}"/>`;
            grid += `<text class="tours-saved-elev-label" x="${padL - 6}" y="${(y + 3).toFixed(1)}" text-anchor="end">${Math.round(e)} m</text>`;
        }
        for (let i = 0; i <= xTicks; i++) {
            const d = (distKm * i) / xTicks;
            const x = xOf(d);
            grid += `<line class="tours-saved-elev-grid" x1="${x.toFixed(1)}" y1="${padT}" x2="${x.toFixed(1)}" y2="${(padT + plotH).toFixed(1)}"/>`;
            const label = d >= 10 ? d.toFixed(0) : d.toFixed(1);
            grid += `<text class="tours-saved-elev-label" x="${x.toFixed(1)}" y="${(svgH - 8).toFixed(1)}" text-anchor="middle">${label} km</text>`;
        }

        let legend = '';
        if (usedModes.size > 1) {
            legend = `<div class="tours-saved-elev-modes">`
                + Array.from(usedModes).map(m =>
                    `<span><i style="background:${modeColor(m)}"></i>${window.escapeHtmlText(modeLabel(m))}</span>`
                ).join('')
                + `</div>`;
        }

        const label = window.formatHmUpDown(totalUp, totalDown) + ' · ' + distKm.toFixed(1) + ' km';
        return `<div class="tours-saved-elev-head"><span>Höhenprofil</span><span>${window.escapeHtmlText(label)}</span></div>`
            + `<svg class="tours-saved-elev-svg" viewBox="0 0 ${svgW} ${svgH}" width="100%" height="auto" preserveAspectRatio="xMidYMid meet" style="aspect-ratio:${svgW} / ${svgH}" aria-hidden="true">`
            + grid
            + areas
            + lines
            + `</svg>`
            + legend
            + `<div class="tours-saved-elev-note">Maßstäblich (Höhe ×${VEX})</div>`;
    };

    window.buildRouteElevationProfileSvgAsync = async function (steps) {
        const key = window.routeElevCacheKey(steps);
        if (window._elevProfileCache.has(key)) {
            return window._elevProfileCache.get(key);
        }

        const pool = window.ALL_SEGMENTS || [];
        let fallbackUp = 0;
        let fallbackDown = 0;
        (steps || []).forEach(step => {
            const seg = pool.find(s => Number(s.id) === Number(step.segmentId));
            if (!seg) return;
            const ad = typeof window.segmentAscentDescent === 'function'
                ? window.segmentAscentDescent(seg)
                : { ascent: Math.max(0, Number(seg.elevation_m) || 0), descent: 0 };
            fallbackUp += Math.max(0, ad.ascent);
            fallbackDown += Math.max(0, ad.descent);
        });

        // Fester Abstand 100 m über die ganze Tour
        let pts = window.sampleRouteElevationPoints(steps, 100);
        if (pts.length < 2) {
            return '<div class="tours-saved-elev-loading">Kein Höhenprofil verfügbar.</div>';
        }

        const result = await window.fetchRouteElevations(pts);
        pts = result.points;
        if (!result.ok) {
            return '<div class="tours-saved-elev-loading">Höhen konnten nicht geladen werden (Netzwerk).</div>';
        }

        for (let pass = 0; pass < 2; pass++) {
            for (let i = 1; i < pts.length - 1; i++) {
                const a = pts[i - 1].ele;
                const b = pts[i].ele;
                const c = pts[i + 1].ele;
                if (![a, b, c].every(Number.isFinite)) continue;
                pts[i] = Object.assign({}, pts[i], { ele: (a + b + c) / 3 });
            }
        }

        const html = window.renderElevationProfileSvg(pts, {
            fallbackUp,
            fallbackDown,
        });
        if (!html) {
            return '<div class="tours-saved-elev-loading">Höhen konnten nicht geladen werden.</div>';
        }
        window._elevProfileCache.set(key, html);
        return html;
    };

    window.hydrateSavedElevationProfiles = function (root) {
        const scope = root || document;
        const hosts = scope.querySelectorAll('.tours-saved-elev[data-elev-key]');
        hosts.forEach((el) => {
            const key = el.getAttribute('data-elev-key');
            if (!key) return;
            if (window._elevProfileCache.has(key)) {
                el.innerHTML = window._elevProfileCache.get(key);
                el.removeAttribute('data-elev-key');
                return;
            }
            const card = el.closest('[data-saved-id]');
            const id = card && card.getAttribute('data-saved-id');
            const item = id ? window.listSavedGuestRoutes().find(r => String(r.id) === String(id)) : null;
            const steps = (item && item.route && item.route.steps) || [];
            if (!steps.length) {
                el.innerHTML = '<div class="tours-saved-elev-loading">Kein Höhenprofil verfügbar.</div>';
                el.removeAttribute('data-elev-key');
                return;
            }
            el.removeAttribute('data-elev-key');
            window.buildRouteElevationProfileSvgAsync(steps).then((html) => {
                if (el.isConnected) {
                    el.innerHTML = html || '<div class="tours-saved-elev-loading">Kein Höhenprofil verfügbar.</div>';
                }
            }).catch((err) => {
                console.warn(err);
                if (el.isConnected) {
                    el.innerHTML = '<div class="tours-saved-elev-loading">Höhenprofil konnte nicht geladen werden.</div>';
                }
            });
        });
    };

    window.syncSavedRoutesUi = function () {
        const host = document.getElementById('tours-saved-list');
        if (!host) return;
        const list = window.listSavedGuestRoutes();
        if (!list.length) {
            host.hidden = false;
            host.classList.add('is-empty');
            host.innerHTML = '<div class="tours-saved-head">Gespeicherte Touren</div>'
                + '<p class="tours-saved-empty">Noch keine Touren gespeichert. Plane eine Tour am Tablet oder PC, sie erscheint danach hier.</p>';
            return;
        }
        host.classList.remove('is-empty');
        host.hidden = false;
        const fmtDur = (min) => {
            const m = Math.round(Number(min) || 0);
            if (m <= 0) return '—';
            if (typeof window.formatDurationMin === 'function') return window.formatDurationMin(m);
            const h = Math.floor(m / 60);
            const r = m % 60;
            return h > 0 ? (h + ' h ' + r + ' min') : (r + ' min');
        };
        const esc = window.escapeHtmlText;
        const trashSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            + '<polyline points="3 6 5 6 21 6"></polyline>'
            + '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>'
            + '</svg>';
        const activeId = window.GUEST_PLAN && window.GUEST_PLAN.loadedSavedId
            ? String(window.GUEST_PLAN.loadedSavedId)
            : '';
        const ordered = list.slice().sort((a, b) => {
            const aActive = String(a.id) === activeId ? 0 : 1;
            const bActive = String(b.id) === activeId ? 0 : 1;
            return aActive - bActive;
        });

        host.innerHTML = '<div class="tours-saved-head">Gespeicherte Touren</div>'
            + '<div class="tours-saved-cards">'
            + ordered.map(item => {
                const meta = item.meta || {};
                const km = meta.km != null ? Number(meta.km).toFixed(1) + ' km' : '—';
                const hmUp = meta.hmUp != null ? meta.hmUp : meta.hm;
                const hmDown = meta.hmDown != null ? meta.hmDown : 0;
                const hm = (hmUp != null || hmDown)
                    ? window.formatHmUpDown(hmUp, hmDown)
                    : '—';
                const dur = fmtDur(meta.duration_min);
                const name = esc(item.name || 'Tour');
                const stages = Array.isArray(meta.stages) ? meta.stages.filter(Boolean) : [];
                const preview = stages.length
                    ? esc(stages.slice(0, 6).join(' → ') + (stages.length > 6 ? ' …' : ''))
                    : '';
                const shape = (item.plan && item.plan.tripShape) || '';
                const shapeLabel = shape === 'loop' ? 'Rundweg'
                    : (shape === 'one_way' || shape === 'point_to_point' ? 'Einfach'
                        : (shape === 'out_and_back' ? 'Hin+Zurück' : ''));
                const isActive = String(item.id) === activeId;
                return `<article class="tours-saved-card${isActive ? ' is-active is-open' : ''}" data-saved-id="${esc(item.id)}">`
                    + `<div class="tours-saved-card-bar">`
                    + `<button type="button" class="tours-saved-card-toggle" data-toggle="${esc(item.id)}" aria-expanded="${isActive ? 'true' : 'false'}" title="Details">${isActive ? '▾' : '▸'}</button>`
                    + `<div class="tours-saved-card-summary">`
                    + `<strong class="tours-saved-card-title">${name}</strong>`
                    + `<span class="tours-saved-card-stats">${esc(km)} · ${esc(hm)} · ${esc(dur)}`
                    + (shapeLabel ? ` · ${esc(shapeLabel)}` : '')
                    + `</span>`
                    + (preview ? `<span class="tours-saved-card-preview">${preview}</span>` : '')
                    + `</div>`
                    + `<button type="button" class="tours-saved-card-load" data-load="${esc(item.id)}">Laden</button>`
                    + `<button type="button" class="tours-saved-card-takeaway" data-takeaway="${esc(item.id)}">Mitnehmen</button>`
                    + `<button type="button" class="tours-saved-card-del" data-delete="${esc(item.id)}" title="Löschen" aria-label="Löschen">${trashSvg}</button>`
                    + `</div>`
                    + `<div class="tours-saved-card-detail"${isActive ? '' : ' hidden'} data-detail-for="${esc(item.id)}">`
                    + window.buildSavedTourItineraryHtml(item)
                    + `</div>`
                    + `</article>`;
            }).join('')
            + '</div>';

        host.querySelectorAll('[data-toggle]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-toggle');
                const card = host.querySelector(`[data-saved-id="${CSS.escape(id)}"]`);
                const detail = card && card.querySelector('.tours-saved-card-detail');
                if (!detail) return;
                const open = detail.hidden;
                detail.hidden = !open;
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                btn.textContent = open ? '▾' : '▸';
                card.classList.toggle('is-open', open);
            });
        });
        host.querySelectorAll('[data-load]').forEach(btn => {
            btn.addEventListener('click', () => window.loadSavedGuestRoute(btn.getAttribute('data-load')));
        });
        host.querySelectorAll('[data-takeaway]').forEach(btn => {
            btn.addEventListener('click', () => window.takeawaySavedGuestRoute(btn.getAttribute('data-takeaway')));
        });
        host.querySelectorAll('[data-delete]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                window.deleteSavedGuestRoute(btn.getAttribute('data-delete'));
            });
        });
        host.querySelectorAll('.tours-saved-photos').forEach(wrap => {
            const urls = Array.from(wrap.querySelectorAll('img')).map(img => img.getAttribute('src')).filter(Boolean);
            wrap.querySelectorAll('.tours-saved-photo').forEach(btn => {
                btn.addEventListener('click', () => {
                    const i = Number(btn.getAttribute('data-photo-i')) || 0;
                    if (typeof window.openImageLightbox === 'function') {
                        window.openImageLightbox(urls, i);
                    }
                });
            });
        });
        if (typeof window.hydrateSavedElevationProfiles === 'function') {
            window.hydrateSavedElevationProfiles(host);
        }
    };

    window.savedRouteSignature = function (plan, route) {
        const p = plan || {};
        const r = route || {};
        const steps = (r.steps || []).map(s => Number(s.segmentId) + (s.reversed ? 'r' : '')).join(',');
        return [
            p.startNodeId || r.startNodeId || '',
            p.goalNodeId || '',
            (p.vias || []).join('-'),
            p.tripShape || '',
            p.loopReversed ? '1' : '0',
            steps,
        ].join('|');
    };

    window.setLoadedRouteGlow = function (on) {
        const stage = document.querySelector('.tours-map-stage');
        const bar = document.getElementById('tours-route-bar');
        if (stage) stage.classList.toggle('tours-route-loaded', !!on);
        if (bar) bar.classList.toggle('tours-route-loaded', !!on);
    };

    window.saveCurrentGuestRoute = function () {
        const r = window.GUEST_ROUTE || {};
        const plan = window.GUEST_PLAN || {};
        if (!(r.steps && r.steps.length)) return;
        const shareMeta = window.buildPlannedRouteSharePayload().meta || {};
        const defaultName = (typeof window.buildGuestRouteSaveNameSuggestion === 'function'
            ? window.buildGuestRouteSaveNameSuggestion()
            : null) || shareMeta.title || 'Gespeicherte Tour';
        const name = window.prompt('Name für die Tour:', defaultName);
        if (name == null) return;
        const trimmed = String(name).trim() || defaultName;
        const sig = window.savedRouteSignature(plan, r);
        const list = window.listSavedGuestRoutes();
        const item = {
            id: 'r' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7),
            name: trimmed,
            auto: false,
            signature: sig,
            savedAt: new Date().toISOString(),
            plan: {
                startNodeId: plan.startNodeId,
                goalNodeId: plan.goalNodeId,
                vias: (plan.vias || []).slice(),
                tripShape: plan.tripShape || 'out_and_back',
                loopReversed: !!plan.loopReversed,
                preferredMode: plan.preferredMode || null,
            },
            route: {
                startNodeId: r.startNodeId,
                steps: (r.steps || []).map(s => Object.assign({}, s)),
            },
            meta: shareMeta,
        };
        list.unshift(item);
        window.persistSavedGuestRoutes(list.slice(0, 40));
        window.pushSavedGuestRoutes([item]);
        if (!window.GUEST_PLAN) window.GUEST_PLAN = {};
        window.GUEST_PLAN.loadedSavedId = item.id;
        window.setLoadedRouteGlow(true);
        window.syncSavedRoutesUi();
        const status = document.getElementById('tours-plan-status');
        if (status) status.textContent = 'Tour gespeichert: ' + trimmed;
    };

    window.loadSavedGuestRoute = function (id) {
        const item = window.listSavedGuestRoutes().find(x => x.id === id);
        if (!item) return;
        const plan = item.plan || {};
        window.GUEST_PLAN = Object.assign({}, window.GUEST_PLAN, {
            startNodeId: plan.startNodeId != null ? Number(plan.startNodeId) : null,
            goalNodeId: plan.goalNodeId != null ? Number(plan.goalNodeId) : null,
            vias: (plan.vias || []).map(Number),
            tripShape: plan.tripShape || 'out_and_back',
            loopReversed: !!plan.loopReversed,
            preferredMode: plan.preferredMode || null,
            lastHint: '',
            loadedSavedId: item.id,
        });
        window.GUEST_ROUTE = {
            startNodeId: item.route && item.route.startNodeId != null
                ? Number(item.route.startNodeId)
                : window.GUEST_PLAN.startNodeId,
            steps: ((item.route && item.route.steps) || []).map(s => Object.assign({}, s)),
        };
        window.setLoadedRouteGlow(true);
        window.syncPlannerUi();
        if (typeof window.syncGuestRouteUi === 'function') window.syncGuestRouteUi();
        if (typeof window.renderHikes === 'function') {
            window.renderHikes({ updateMap: true, fitBounds: false });
        }
        window.syncSavedRoutesUi();
        const status = document.getElementById('tours-plan-status');
        if (status) status.textContent = 'Geladen: ' + (item.name || 'Tour');
        if (window.matchMedia && window.matchMedia('(max-width: 767px)').matches) {
            const stage = document.querySelector('.tours-map-stage');
            if (stage) stage.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    window.takeawaySavedGuestRoute = function (id) {
        window.loadSavedGuestRoute(id);
        window.openPlannedRouteTakeaway();
    };

    window.deleteSavedGuestRoute = function (id) {
        if (!id) return;
        if (!window.confirm('Gespeicherte Tour löschen?')) return;
        const list = window.listSavedGuestRoutes().filter(x => x.id !== id);
        window.persistSavedGuestRoutes(list);
        window.deleteSavedGuestRouteRemote(id);
        if (window.GUEST_PLAN && window.GUEST_PLAN.loadedSavedId === id) {
            window.GUEST_PLAN.loadedSavedId = null;
            window.setLoadedRouteGlow(false);
        }
        window.syncSavedRoutesUi();
    };
})();
