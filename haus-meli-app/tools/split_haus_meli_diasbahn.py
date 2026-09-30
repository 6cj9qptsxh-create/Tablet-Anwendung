#!/usr/bin/env python3
"""Split Haus Meli <-> Diasbahn Talstation GPX + Seilbahn 0km segment."""
from __future__ import annotations

import json
import math
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
        pts.append({
            "lat": float(el.attrib["lat"]),
            "lng": float(el.attrib["lon"]),
            "ele": ele,
        })
    return pts


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
                d = p["ele"] - prev["ele"]
                if d > 0.5:
                    elev += d
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
        lines.append(
            f'      <trkpt lat="{p["lat"]:.7f}" lon="{p["lng"]:.7f}">{ele}</trkpt>'
        )
    lines += ["  </trkseg></trk>", "</gpx>"]
    path.write_text("\n".join(lines), encoding="utf-8")


def main():
    out = Path(r"C:\Tablet Anwendung\haus-meli-app\storage\tour_splits\haus-meli-diasbahn")
    out.mkdir(parents=True, exist_ok=True)

    src = Path(r"C:\Users\luggi\Downloads\2026-07-19_3122009650_Haus Meli - Diasbahn.gpx")
    pts = parse_gpx(src)

    hm = (47.069961, 10.398723)
    tal = (47.060256, 10.374533)
    berg = (47.067115, 10.359023)

    tal_i = min(
        range(len(pts)),
        key=lambda i: hav(tal[0], tal[1], pts[i]["lat"], pts[i]["lng"]),
    )
    snap_m = hav(tal[0], tal[1], pts[tal_i]["lat"], pts[tal_i]["lng"])

    hin = pts[: tal_i + 1]
    retour = pts[tal_i:]
    s_hin = stats(hin)
    s_ret = stats(retour)

    write_gpx(out / "hin_haus-meli-diasbahn-talstation.gpx", "Haus Meli -> Diasbahn Talstation", hin)
    write_gpx(out / "retour_diasbahn-talstation-haus-meli.gpx", "Diasbahn Talstation -> Haus Meli", retour)

    seil_pts = [
        {"lat": berg[0], "lng": berg[1], "ele": None},
        {"lat": tal[0], "lng": tal[1], "ele": None},
    ]
    write_gpx(out / "seilbahn_diasbahn-berg-tal.gpx", "Diasbahn Bergstation <-> Talstation", seil_pts)

    nodes = [
        {"name": "Haus Meli", "lat": hm[0], "lng": hm[1], "flags": ["start"]},
        {"name": "Diasbahn Talstation", "lat": tal[0], "lng": tal[1], "flags": ["seilbahn"]},
        {"name": "Diasbahn Bergstation", "lat": berg[0], "lng": berg[1], "flags": ["seilbahn"]},
    ]

    segments = [
        {
            "name": "Haus Meli -> Diasbahn Talstation",
            "from": "Haus Meli",
            "to": "Diasbahn Talstation",
            "out_and_back": True,
            "bidirectional": True,
            "note": "GPX ist Hin+Retour; Geometrie nur Hin. out_and_back empfohlen.",
            "gpx_file": "hin_haus-meli-diasbahn-talstation.gpx",
            **{k: s_hin[k] for k in ("distance_km", "elevation_m", "point_count", "geojson")},
        },
        {
            "name": "Diasbahn Talstation -> Haus Meli",
            "from": "Diasbahn Talstation",
            "to": "Haus Meli",
            "out_and_back": False,
            "bidirectional": True,
            "note": "Optional Gegenrichtung; sonst bidirectional auf Hin-Segment.",
            "gpx_file": "retour_diasbahn-talstation-haus-meli.gpx",
            **{k: s_ret[k] for k in ("distance_km", "elevation_m", "point_count", "geojson")},
        },
        {
            "name": "Diasbahn Bergstation -> Diasbahn Talstation",
            "from": "Diasbahn Bergstation",
            "to": "Diasbahn Talstation",
            "out_and_back": False,
            "bidirectional": True,
            "distance_km": 0.0,
            "elevation_m": 0,
            "point_count": 2,
            "gpx_file": "seilbahn_diasbahn-berg-tal.gpx",
            "geojson": {
                "type": "LineString",
                "coordinates": [[berg[1], berg[0]], [tal[1], tal[0]]],
            },
            "note": "Seilbahn: immer 0 km / 0 Hm",
            "transport": "cable_car",
        },
    ]

    (out / "segments.json").write_text(
        json.dumps({"nodes": nodes, "segments": segments}, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    summary = {
        "nodes": nodes,
        "segments": [{k: v for k, v in s.items() if k != "geojson"} for s in segments],
        "source_gpx": src.name,
        "tal_snap_m": round(snap_m, 2),
        "analysis": (
            "Track ist reiner Hin+Retour Haus Meli <-> Diasbahn Talstation "
            "(Bergstation nicht auf dem Track)."
        ),
    }
    (out / "report.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding="utf-8")

    md = [
        "# Haus Meli - Diasbahn",
        "",
        "Track: Hin+Retour zu **Diasbahn Talstation** (kein Berg).",
        f"Talstation-Snap: {snap_m:.1f} m",
        "",
        "## Segmente",
    ]
    for s in summary["segments"]:
        md.append(
            f"- **{s['name']}**: {s['distance_km']} km, {s['elevation_m']} Hm -> `{s['gpx_file']}`"
        )
        if s.get("note"):
            md.append(f"  - {s['note']}")
    md += [
        "",
        "## Empfehlung Graph",
        "- 1 Segment Haus Meli -> Talstation mit out_and_back=true",
        "- 1 Seilbahn-Segment Berg <-> Tal mit 0 km / 0 Hm, bidirectional",
        "",
        f"Output: `{out}`",
    ]
    text = "\n".join(md)
    (out / "README.md").write_text(text, encoding="utf-8")
    print(text.encode("cp1252", errors="replace").decode("cp1252"))


if __name__ == "__main__":
    main()
