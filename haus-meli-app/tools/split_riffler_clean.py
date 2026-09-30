#!/usr/bin/env python3
"""
Sauberer Split: Haus Meli → Riffler → Diasbahn Bergstation (+ Retour-Segmente).

Knoten:
  Haus Meli, Durrich Alpe, Abzweigung Lichtsee (auto auf Track),
  Lichtsee, Blankasee, Edmund-Graf-Hütte, Hoher Riffler, Diasbahn Berg/Tal

Kette Hin:
  Haus Meli → Durrich → Abzw Lichtsee → Lichtsee → Blankasee → Edmund → Riffler
  Riffler → Edmund → Diasbahn Bergstation

Retour = geometrische Umkehrung jedes Hin-Segments (eigene Hm).
Kein Abzweigung Niederelbe.
"""
from __future__ import annotations

import json
import math
import re
import xml.etree.ElementTree as ET
from pathlib import Path


def hav(lat1, lon1, lat2, lon2):
    r = 6371000.0
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dl = math.radians(lon2 - lon1)
    a = math.sin(dphi / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * r * math.asin(min(1.0, math.sqrt(a)))


def parse_gpx(path: Path):
    root = ET.parse(path).getroot()
    pts = []
    for el in root.iter():
        if el.tag.split("}")[-1] not in ("trkpt", "rtept"):
            continue
        ele = None
        for c in el:
            if c.tag.split("}")[-1] == "ele" and c.text:
                ele = float(c.text)
        pts.append({"lat": float(el.attrib["lat"]), "lng": float(el.attrib["lon"]), "ele": ele})
    return pts


def nearest(pts, lat, lng, start=0, end=None):
    end = len(pts) - 1 if end is None else end
    best_i, best_d = start, float("inf")
    for i in range(start, min(end, len(pts) - 1) + 1):
        d = hav(lat, lng, pts[i]["lat"], pts[i]["lng"])
        if d < best_d:
            best_d, best_i = d, i
    return best_i, best_d


def find_abzw_after_durrich(pts, durr_idx, licht_idx):
    durr = pts[durr_idx]
    for i in range(durr_idx + 1, licht_idx):
        d = hav(durr["lat"], durr["lng"], pts[i]["lat"], pts[i]["lng"])
        if 40 <= d <= 120:
            return i
    return durr_idx + max(1, (licht_idx - durr_idx) // 6)


def stats(points):
    coords = []
    dist = 0.0
    elev = 0.0
    prev = None
    for p in points:
        coords.append([p["lng"], p["lat"]])
        if prev is not None:
            dist += hav(prev["lat"], prev["lng"], p["lat"], p["lng"])
            if prev["ele"] is not None and p["ele"] is not None:
                delta = p["ele"] - prev["ele"]
                if delta > 0.5:
                    elev += delta
        prev = p
    return {
        "distance_km": round(dist / 1000, 2),
        "elevation_m": int(round(elev)),
        "point_count": len(coords),
        "geojson": {"type": "LineString", "coordinates": coords},
    }


def write_gpx(path: Path, name: str, points):
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<gpx version="1.1" creator="Haus Meli Split" xmlns="http://www.topografix.com/GPX/1/1">',
        f"  <metadata><name>{name}</name></metadata>",
        f"  <trk><name>{name}</name><trkseg>",
    ]
    for p in points:
        ele = f"<ele>{p['ele']:.1f}</ele>" if p.get("ele") is not None else ""
        lines.append(f'      <trkpt lat="{p["lat"]:.7f}" lon="{p["lng"]:.7f}">{ele}</trkpt>')
    lines += ["  </trkseg></trk>", "</gpx>"]
    path.write_text("\n".join(lines), encoding="utf-8")


def slug(a, b):
    return re.sub(r"[^a-zA-Z0-9]+", "-", f"{a}-{b}").strip("-").lower()


def main():
    out = Path(r"C:\Tablet Anwendung\haus-meli-app\storage\tour_splits\hoher-riffler-clean")
    out.mkdir(parents=True, exist_ok=True)

    hin_path = Path(
        r"C:\Users\luggi\Downloads\2026-07-19_3122005120_Haus Meli - Hoher Riffler - Diasbahn (2).gpx"
    )
    # Retour-Datei optional: wir erzeugen Gegenrichtung geometrisch aus Hin
    _ = Path(
        r"C:\Users\luggi\Downloads\2026-07-19_3122005120_Haus Meli - Hoher Riffler - Diasbahn retour.gpx"
    )

    targets = {
        "Haus Meli": (47.069961, 10.398723),
        "Durrich Alpe": (47.085255, 10.393960),
        "Abzweigung Lichtsee": (47.089566, 10.389693),
        "Lichtsee": (47.089489, 10.388942),
        "Blankasee": (47.099581, 10.376400),
        "Edmund-Graf-Hütte": (47.105967, 10.355834),
        "Hoher Riffler": (47.115954, 10.370957),
        "Diasbahn Bergstation": (47.067115, 10.359023),
    }

    hin = parse_gpx(hin_path)

    i_hm, d_hm = nearest(hin, *targets["Haus Meli"])
    i_du, d_du = nearest(hin, *targets["Durrich Alpe"], start=i_hm)
    i_ab, d_ab = nearest(hin, *targets["Abzweigung Lichtsee"], start=i_du)
    i_li, d_li = nearest(hin, *targets["Lichtsee"], start=i_ab)

    # Spur: Abzw → Lichtsee → wieder Abzw (Track kehrt zurück), danach weiter zum Blankasee
    i_ab_back = i_li
    best_d = float("inf")
    for i in range(i_li, min(i_li + 40, len(hin))):
        d = hav(
            hin[i_ab]["lat"], hin[i_ab]["lng"],
            hin[i]["lat"], hin[i]["lng"],
        )
        if d < best_d:
            best_d = d
            i_ab_back = i
        # nach dem Minimum wieder weg vom Abzw → fertig
        if i > i_li + 2 and d > best_d + 15 and best_d < 25:
            break

    i_bl, d_bl = nearest(hin, *targets["Blankasee"], start=i_ab_back)
    i_ed_up, d_ed_up = nearest(hin, *targets["Edmund-Graf-Hütte"], start=i_bl)
    i_ri, d_ri = nearest(hin, *targets["Hoher Riffler"], start=i_ed_up)
    i_ed_dn, d_ed_dn = nearest(hin, *targets["Edmund-Graf-Hütte"], start=i_ri)
    i_db, d_db = nearest(hin, *targets["Diasbahn Bergstation"], start=i_ed_dn)

    # Durrich → Abzw | Abzw → Lichtsee | Lichtsee → Abzw | Abzw → Blankasee | …
    cuts = [
        ("Haus Meli", i_hm, "Durrich Alpe", i_du),
        ("Durrich Alpe", i_du, "Abzweigung Lichtsee", i_ab),
        ("Abzweigung Lichtsee", i_ab, "Lichtsee", i_li),
        ("Lichtsee", i_li, "Abzweigung Lichtsee", i_ab_back),
        ("Abzweigung Lichtsee", i_ab_back, "Blankasee", i_bl),
        ("Blankasee", i_bl, "Edmund-Graf-Hütte", i_ed_up),
        ("Edmund-Graf-Hütte", i_ed_up, "Hoher Riffler", i_ri),
        ("Hoher Riffler", i_ri, "Edmund-Graf-Hütte", i_ed_dn),
        ("Edmund-Graf-Hütte", i_ed_dn, "Diasbahn Bergstation", i_db),
    ]

    nodes = [
        {"name": "Haus Meli", "lat": targets["Haus Meli"][0], "lng": targets["Haus Meli"][1], "flags": ["start"]},
        {"name": "Durrich Alpe", "lat": targets["Durrich Alpe"][0], "lng": targets["Durrich Alpe"][1], "flags": ["highlight"]},
        {
            "name": "Abzweigung Lichtsee",
            "lat": targets["Abzweigung Lichtsee"][0],
            "lng": targets["Abzweigung Lichtsee"][1],
            "flags": ["junction"],
        },
        {"name": "Lichtsee", "lat": targets["Lichtsee"][0], "lng": targets["Lichtsee"][1], "flags": ["highlight"]},
        {"name": "Blankasee", "lat": targets["Blankasee"][0], "lng": targets["Blankasee"][1], "flags": ["highlight"]},
        {"name": "Edmund-Graf-Hütte", "lat": targets["Edmund-Graf-Hütte"][0], "lng": targets["Edmund-Graf-Hütte"][1], "flags": ["highlight"]},
        {"name": "Hoher Riffler", "lat": targets["Hoher Riffler"][0], "lng": targets["Hoher Riffler"][1], "flags": ["highlight"]},
        {"name": "Diasbahn Bergstation", "lat": targets["Diasbahn Bergstation"][0], "lng": targets["Diasbahn Bergstation"][1], "flags": ["seilbahn"]},
        {"name": "Diasbahn Talstation", "lat": 47.060256, "lng": 10.374533, "flags": ["seilbahn"]},
    ]

    highlight = {"Durrich Alpe", "Lichtsee", "Blankasee", "Edmund-Graf-Hütte", "Hoher Riffler"}
    forward = []

    for a_name, a_i, b_name, b_i in cuts:
        if b_i <= a_i:
            raise SystemExit(f"Index-Fehler: {a_name}@{a_i} -> {b_name}@{b_i}")
        chunk = hin[a_i : b_i + 1]
        st = stats(chunk)
        name = f"{a_name} → {b_name}"
        gpx = f"hin_{slug(a_name, b_name)}.gpx"
        write_gpx(out / gpx, name, chunk)
        forward.append({
            "name": name,
            "from": a_name,
            "to": b_name,
            "gpx_file": gpx,
            "bidirectional": False,
            "out_and_back": False,
            "is_highlight": a_name in highlight or b_name in highlight,
            "direction": "hin",
            "chunk": chunk,
            **st,
        })

    # Gegenrichtung nur wenn noch nicht vorhanden
    # (Lichtsee↔Abzw und Riffler↔Edmund liegen schon als Track-Stücke vor)
    existing = {s["name"] for s in forward}
    segments = [{k: v for k, v in s.items() if k != "chunk"} for s in forward]

    for s in forward:
        name_b = f"{s['to']} → {s['from']}"
        if name_b in existing:
            continue
        back = list(reversed(s["chunk"]))
        st_b = stats(back)
        gpx_b = f"retour_{slug(s['to'], s['from'])}.gpx"
        write_gpx(out / gpx_b, name_b, back)
        segments.append({
            "name": name_b,
            "from": s["to"],
            "to": s["from"],
            "gpx_file": gpx_b,
            "bidirectional": False,
            "out_and_back": False,
            "is_highlight": s["is_highlight"],
            "direction": "retour",
            **st_b,
        })
        existing.add(name_b)

    # Seilbahn
    berg = targets["Diasbahn Bergstation"]
    tal = (47.060256, 10.374533)
    seil_name = "Diasbahn Bergstation → Diasbahn Talstation"
    seil_gpx = "seilbahn_diasbahn-berg-tal.gpx"
    write_gpx(
        out / seil_gpx,
        seil_name,
        [{"lat": berg[0], "lng": berg[1], "ele": None}, {"lat": tal[0], "lng": tal[1], "ele": None}],
    )
    segments.append({
        "name": seil_name,
        "from": "Diasbahn Bergstation",
        "to": "Diasbahn Talstation",
        "distance_km": 0.0,
        "elevation_m": 0,
        "point_count": 2,
        "bidirectional": True,
        "out_and_back": False,
        "is_highlight": False,
        "transport": "cable_car",
        "gpx_file": seil_gpx,
        "geojson": {"type": "LineString", "coordinates": [[berg[1], berg[0]], [tal[1], tal[0]]]},
        "direction": "seilbahn",
    })

    # Dedupe by name
    uniq = {s["name"]: s for s in segments}
    segments = list(uniq.values())

    note = (
        "Segmente an Abzw: Durrich→Abzw, Abzw↔Lichtsee (Spur), Abzw→Blankasee. "
        "Kein Lichtsee→Blankasee. Abstieg Riffler→Edmund→Diasbahn."
    )

    report = {
        "note": note,
        "snap": {
            "Haus Meli": {"idx": i_hm, "m": round(d_hm, 1)},
            "Durrich Alpe": {"idx": i_du, "m": round(d_du, 1)},
            "Abzweigung Lichtsee": {"idx": i_ab, "m": round(d_ab, 1)},
            "Lichtsee": {"idx": i_li, "m": round(d_li, 1)},
            "Abzweigung nach Spur": {"idx": i_ab_back, "m": round(best_d, 1)},
            "Blankasee": {"idx": i_bl, "m": round(d_bl, 1)},
            "Edmund aufwaerts": {"idx": i_ed_up, "m": round(d_ed_up, 1)},
            "Hoher Riffler": {"idx": i_ri, "m": round(d_ri, 1)},
            "Edmund abstieg": {"idx": i_ed_dn, "m": round(d_ed_dn, 1)},
            "Diasbahn Berg": {"idx": i_db, "m": round(d_db, 1)},
        },
        "nodes": nodes,
        "segments": [{k: v for k, v in s.items() if k != "geojson"} for s in segments],
    }

    (out / "segments.json").write_text(
        json.dumps({"nodes": nodes, "segments": segments}, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    (out / "report.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")

    lines = ["# Hoher Riffler CLEAN", "", note, "", "## Snap", ""]
    for k, v in report["snap"].items():
        lines.append(f"- {k}: {v}")
    lines += ["", f"## Segmente ({len(segments)})", ""]
    for s in report["segments"]:
        lines.append(f"- {s['name']}: {s['distance_km']} km / {s['elevation_m']} Hm [{s.get('direction','')}]")
    text = "\n".join(lines)
    (out / "README.md").write_text(text, encoding="utf-8")
    print(text.encode("cp1252", errors="replace").decode("cp1252"))
    print("OUT", out)


if __name__ == "__main__":
    main()
