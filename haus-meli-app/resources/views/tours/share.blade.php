<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#23272c">
    <title>{{ $meta['title'] ?? 'Haus Meli Tour' }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <style>
        :root {
            --bg: #2e343a;
            --surface: #343a40;
            --card: #222229;
            --text: #f8f9fa;
            --muted: #adb5bd;
            --accent: #6c25b3;
            --accent-contrast: #ffffff;
            --accent-soft: color-mix(in srgb, var(--accent), white 30%);
            --radius: 10px;
            --font: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            --line: rgba(255, 255, 255, 0.1);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100dvh;
            font-family: var(--font);
            background:
                radial-gradient(ellipse 80% 50% at 20% 0%, color-mix(in srgb, var(--accent), transparent 82%), transparent),
                var(--bg);
            color: var(--text);
            padding: 24px 18px 40px;
        }
        .wrap { max-width: 420px; margin: 0 auto; }
        .brand {
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }
        h1 {
            font-size: 1.55rem;
            font-weight: 700;
            margin: 0 0 18px;
            line-height: 1.25;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        .stat {
            background: var(--card);
            border-radius: var(--radius);
            padding: 12px 10px;
            text-align: center;
        }
        .stat strong {
            display: block;
            font-size: 1.15rem;
            font-weight: 700;
        }
        .stat span {
            font-size: 0.72rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        #share-map {
            height: 280px;
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 12px;
            background: #1a1e22;
            border: 1px solid var(--line);
        }
        .actions { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 48px;
            padding: 12px 16px;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 650;
            text-decoration: none;
            cursor: pointer;
            color: #fff;
            font-family: inherit;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-soft));
            color: var(--accent-contrast);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 4px 12px color-mix(in srgb, var(--accent), transparent 60%);
        }
        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: var(--text);
        }
        .btn:disabled { opacity: 0.55; cursor: default; }
        .tip {
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.45;
            margin: 0 0 22px;
        }
        .stages h2 {
            margin: 0 0 10px;
            font-size: 1rem;
        }
        .stages ol {
            margin: 0;
            padding: 0;
            list-style: none;
            border-top: 1px solid var(--line);
        }
        .stages li {
            padding: 10px 0;
            border-bottom: 1px solid var(--line);
            font-size: 0.95rem;
            display: flex;
            gap: 10px;
        }
        .stages .n {
            color: var(--muted);
            font-variant-numeric: tabular-nums;
            min-width: 1.5rem;
        }
        .foot {
            margin-top: 28px;
            font-size: 0.78rem;
            color: var(--muted);
            text-align: center;
        }
        .status {
            min-height: 1.2em;
            margin: 0 0 12px;
            font-size: 0.88rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
@php
    $title = $meta['title'] ?? 'Haus Meli Tour';
    $km = isset($meta['km']) ? round((float) $meta['km'], 1) : null;
    $hm = isset($meta['hm']) ? (int) $meta['hm'] : null;
    $dur = isset($meta['duration_min']) ? (int) $meta['duration_min'] : null;
    $stages = is_array($meta['stages'] ?? null) ? $meta['stages'] : [];
    $track = is_array($track ?? null) ? $track : [];
    $durLabel = $dur !== null
        ? (floor($dur / 60) > 0 ? floor($dur / 60).' h '.($dur % 60).' min' : $dur.' min')
        : '—';
    $gpxFilename = 'haus-meli-'.(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $title) ?: 'tour').'.gpx';
@endphp
<div class="wrap">
    <div class="brand">Haus Meli</div>
    <h1>{{ $title }}</h1>

    <div class="stats">
        <div class="stat">
            <strong>{{ $km !== null ? $km.' km' : '—' }}</strong>
            <span>Strecke</span>
        </div>
        <div class="stat">
            <strong>{{ $hm !== null ? $hm.' Hm' : '—' }}</strong>
            <span>Aufstieg</span>
        </div>
        <div class="stat">
            <strong>{{ $durLabel }}</strong>
            <span>Dauer</span>
        </div>
    </div>

    @if (count($track) >= 2)
        <div id="share-map" role="img" aria-label="Routenkarte"></div>
    @endif

    <p class="status" id="status" aria-live="polite"></p>

    <div class="actions">
        @if (count($track) >= 2)
            <button type="button" class="btn btn-primary" id="follow-gps">Im Browser folgen</button>
            <a class="btn btn-secondary" id="gpx-download" href="{{ $gpxUrl }}">GPX speichern (für App)</a>
        @else
            <a class="btn btn-primary" id="gpx-download" href="{{ $gpxUrl }}">GPX speichern</a>
        @endif
        <button type="button" class="btn btn-secondary" id="share-file">Teilen…</button>
    </div>

    <p class="tip">
        <strong>Karte:</strong> Die Route siehst du oben immer — zum Mitlaufen reicht der Blick auf die Spur.
        <strong>GPS im Browser</strong> funktioniert nur mit <em>HTTPS</em> (unter http://192.168… blockieren Chrome/Safari den Standort).
        Ohne HTTPS: GPX speichern und in Komoot/Outdooractive/… öffnen.
    </p>

    @if (count($stages))
        <div class="stages">
            <h2>Etappen</h2>
            <ol>
                @foreach ($stages as $i => $name)
                    <li><span class="n">{{ $i + 1 }}.</span><span>{{ $name }}</span></li>
                @endforeach
            </ol>
        </div>
    @endif

    <p class="foot">Link gültig ca. {{ $expiresHours }} Stunden</p>
</div>
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(function () {
    var gpxUrl = @json($gpxUrl);
    var title = @json($title);
    var filename = @json($gpxFilename);
    var track = @json($track);
    var status = document.getElementById('status');
    var btnShare = document.getElementById('share-file');
    var btnFollow = document.getElementById('follow-gps');
    var watchId = null;
    var meMarker = null;
    var map = null;

    function setStatus(msg) {
        if (status) status.textContent = msg || '';
    }

    if (Array.isArray(track) && track.length >= 2 && typeof L !== 'undefined') {
        map = L.map('share-map', { zoomControl: true, attributionControl: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        var line = L.polyline(track, { color: '#6c25b3', weight: 5, opacity: 0.95 }).addTo(map);
        map.fitBounds(line.getBounds(), { padding: [28, 28] });
        setTimeout(function () { map.invalidateSize(true); }, 80);
    }

    if (btnFollow && map) {
        btnFollow.addEventListener('click', function () {
            if (!window.isSecureContext) {
                setStatus('Standort geht nur über HTTPS (oder localhost). Unter http://192.168… blockiert der Browser GPS. Bitte GPX in einer App öffnen.');
                return;
            }
            if (!navigator.geolocation) {
                setStatus('Standort ist in diesem Browser nicht verfügbar.');
                return;
            }
            if (watchId != null) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
                btnFollow.textContent = 'Im Browser folgen';
                setStatus('Standort-Verfolgung aus.');
                return;
            }
            setStatus('Standort wird ermittelt…');
            watchId = navigator.geolocation.watchPosition(function (pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                if (!meMarker) {
                    meMarker = L.circleMarker([lat, lng], {
                        radius: 8,
                        color: '#fff',
                        weight: 2,
                        fillColor: '#2563eb',
                        fillOpacity: 1
                    }).addTo(map);
                } else {
                    meMarker.setLatLng([lat, lng]);
                }
                map.panTo([lat, lng], { animate: true });
                setStatus('Du wirst auf der Karte angezeigt — Route im Blick behalten.');
                btnFollow.textContent = 'Verfolgung stoppen';
            }, function (err) {
                var msg = (err && err.message) || '';
                if (/permission|secure|origin|denied/i.test(msg) || (err && err.code === 1)) {
                    setStatus('Kein Standort-Zugriff. Unter HTTP (z. B. 192.168…) blockiert der Browser GPS — GPX in einer App nutzen, oder Seite per HTTPS öffnen.');
                } else {
                    setStatus(msg || 'Standort fehlgeschlagen.');
                }
                watchId = null;
                btnFollow.textContent = 'Im Browser folgen';
            }, {
                enableHighAccuracy: true,
                maximumAge: 5000,
                timeout: 15000
            });
        });
    }

    if (btnShare) {
        btnShare.addEventListener('click', async function () {
            btnShare.disabled = true;
            setStatus('Datei wird vorbereitet…');
            try {
                var res = await fetch(gpxUrl, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('GPX konnte nicht geladen werden.');
                var blob = await res.blob();
                var file = new File([blob], filename, { type: 'application/gpx+xml' });

                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    setStatus('');
                    await navigator.share({
                        files: [file],
                        title: title,
                        text: 'Haus Meli Tour (GPX)',
                    });
                } else if (navigator.share) {
                    setStatus('');
                    await navigator.share({
                        title: title,
                        text: 'Haus Meli Tour (GPX)',
                        url: new URL(gpxUrl, window.location.href).href,
                    });
                } else {
                    setStatus('Teilen nicht verfügbar — bitte „GPX speichern“ nutzen.');
                    window.location.href = gpxUrl;
                }
            } catch (err) {
                if (err && err.name === 'AbortError') {
                    setStatus('');
                } else {
                    setStatus((err && err.message) || 'Teilen fehlgeschlagen — bitte GPX speichern.');
                }
            } finally {
                btnShare.disabled = false;
            }
        });
    }
})();
</script>
</body>
</html>
