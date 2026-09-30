#!/usr/bin/env python3
"""Split GPX tracks by ordered nodes (nearest track point, monotonic)."""
from __future__ import annotations

import json
import math
import re
import xml.etree.ElementTree as ET
from pathlib import Path

NS = {"g": "http://www.topografix.com/GPX/1/1"}


def haversine_m(lat1, lon1, lat2, lon2):
    r = 6371000.0
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dl = math.radians(lon2 - lon1)
    a = math.sin(dphi / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * r * math.asin(min(1.0, math.sqrt(a)))


def parse_gpx(path: Path):
    tree = ET.parse(path)
    root = tree.getroot()
    # tolerate default ns
    pts = []
    for el in root.iter():
        tag = el.tag.split("}")[-1]
        if tag not in ("trkpt", "rtept"):
            continue
        lat = float(el.attrib["lat"])
        lon = float(el.attrib["lon"])
        ele = None
        for child in el:
            if child.tag.split("}")[-1] == "ele" and child.text:
                try:
                    ele = float(child.text)
                except ValueError:
                    pass
        pts.append({"lat": lat, "lng": lon, "ele": ele})
    return pts


def nearest_index(points, lat, lng, start_from=0, window=None):
    best_i, best_d = start_from, float("inf")
    end = len(points) if window is None else min(len(points), start_from + window)
    for i in range(start_from, end):
        d = haversine_m(lat, lng, points[i]["lat"], points[i]["lng"])
        if d < best_d:
            best_d, best_i = d, i
    return best_i, best_d


def slice_stats(points):
    coords = []
    dist = 0.0
    elev = 0.0
    prev = None
    for p in points:
        coords.append([p["lng"], p["lat"]])
        if prev is not None:
            dist += haversine_m(prev["lat"], prev["lng"], p["lat"], p["lng"])
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
        "  <trk><name>" + name + "</name><trkseg>",
    ]
    for p in points:
        ele = f"<ele>{p['ele']:.1f}</ele>" if p.get("ele") is not None else ""
        lines.append(f'      <trkpt lat="{p["lat"]:.7f}" lon="{p["lng"]:.7f}">{ele}</trkpt>')
    lines += ["  </trkseg></trk>", "</gpx>"]
    path.write_text("\n".join(lines), encoding="utf-8")


def snap_nodes(points, nodes, min_gap_pts=3):
    """Snap each node in order; skip zero-length duplicates."""
    snapped = []
    cursor = 0
    for n in nodes:
        # search forward first; allow small lookback for GPS noise
        lookback = max(0, cursor - 15)
        idx, dist = nearest_index(points, n["lat"], n["lng"], lookback)
        if idx < cursor:
            idx, dist = nearest_index(points, n["lat"], n["lng"], cursor)
        if snapped and idx <= snapped[-1]["index"] + min_gap_pts and dist < 40:
            # same physical place as previous — merge / skip as separate cut
            snapped.append({
                **n,
                "index": snapped[-1]["index"],
                "snap_m": dist,
                "merged_with": snapped[-1]["name"],
                "skipped_cut": True,
            })
            continue
        if idx < cursor:
            idx = cursor
        snapped.append({**n, "index": idx, "snap_m": round(dist, 1), "skipped_cut": False})
        cursor = idx
    return snapped


def split_track(points, snapped, out_dir: Path, prefix: str):
    segments = []
    real = [s for s in snapped if not s.get("skipped_cut")]
    # keep skipped in report but only cut on real indices
    cuts = []
    for s in snapped:
        if s.get("skipped_cut"):
            cuts.append(s)
            continue
        cuts.append(s)

    ordered = [s for s in snapped]
    last_real_idx = None
    last_real_name = None
    for s in ordered:
        if s.get("skipped_cut"):
            continue
        if last_real_idx is None:
            last_real_idx = s["index"]
            last_real_name = s["name"]
            continue
        i0, i1 = last_real_idx, s["index"]
        if i1 <= i0:
            last_real_idx, last_real_name = i1, s["name"]
            continue
        chunk = points[i0 : i1 + 1]
        stats = slice_stats(chunk)
        slug = re.sub(r"[^a-zA-Z0-9]+", "-", f"{last_real_name}-{s['name']}").strip("-").lower()
        gpx_name = f"{prefix}_{slug}.gpx"
        write_gpx(out_dir / gpx_name, f"{last_real_name} → {s['name']}", chunk)
        segments.append({
            "name": f"{last_real_name} → {s['name']}",
            "from": last_real_name,
            "to": s["name"],
            "from_lat": points[i0]["lat"],
            "from_lng": points[i0]["lng"],
            "to_lat": points[i1]["lat"],
            "to_lng": points[i1]["lng"],
            "index_from": i0,
            "index_to": i1,
            "gpx_file": gpx_name,
            **stats,
        })
        last_real_idx, last_real_name = i1, s["name"]
    return segments


def main():
    base = Path(r"C:\Users\luggi\Downloads")
    out = Path(r"C:\Tablet Anwendung\haus-meli-app\storage\tour_splits\hoher-riffler")
    out.mkdir(parents=True, exist_ok=True)

    # Abzweigung Lichtsee = gleiche Koordinaten wie Durrich Alpe → ein Knoten
    nodes = [
        {"name": "Haus Meli", "lat": 47.069961, "lng": 10.398723, "flags": ["start"]},
        {"name": "Durrich Alpe", "lat": 47.085255, "lng": 10.393960, "flags": ["highlight", "junction_lichtsee"]},
        {"name": "Lichtsee", "lat": 47.089489, "lng": 10.388942, "flags": ["highlight"]},
        {"name": "Blankasee", "lat": 47.099581, "lng": 10.376400, "flags": ["highlight"]},
        {"name": "Edmund-Graf-Hütte", "lat": 47.105967, "lng": 10.355834, "flags": ["highlight"]},
        {"name": "Hoher Riffler", "lat": 47.115954, "lng": 10.370957, "flags": ["highlight"]},
        {"name": "Abzweigung Niederelbe Hütte", "lat": 47.067735, "lng": 10.356623, "flags": ["junction"]},
        {"name": "Diasbahn Bergstation", "lat": 47.067115, "lng": 10.359023, "flags": ["seilbahn"]},
    ]

    # Note: separate node "Abzweigung Lichtsee" was same lat/lng as Durrich — merged.

    files = [
        ("hin", base / "2026-07-19_3122005120_Haus Meli - Hoher Riffler - Diasbahn.gpx"),
        ("retour", base / "2026-07-19_3122005120_Haus Meli - Hoher Riffler - Diasbahn (1).gpx"),
    ]

    report = {
        "nodes_used": nodes,
        "note": "Abzweigung Lichtsee hatte dieselben Koordinaten wie Durrich Alpe — als ein Knoten behandelt.",
        "seilbahn": {
            "idea": "Diasbahn Bergstation <-> Talstation als Segment mit 0 km / 0 Hm",
            "talstation": "Koordinaten fehlen noch — bitte nachreichen",
        },
        "tracks": [],
    }

    for prefix, path in files:
        points = parse_gpx(path)
        # Richtung: liegt Track-Start näher an Haus Meli oder an Diasbahn?
        start_n, end_n = nodes[0], nodes[-1]
        d_start_hm = haversine_m(points[0]["lat"], points[0]["lng"], start_n["lat"], start_n["lng"])
        d_start_db = haversine_m(points[0]["lat"], points[0]["lng"], end_n["lat"], end_n["lng"])
        node_order = list(nodes)
        if d_start_db + 80 < d_start_hm:
            node_order = list(reversed(nodes))
        snapped = snap_nodes(points, node_order)
        segs = split_track(points, snapped, out, prefix)
        report["tracks"].append({
            "file": path.name,
            "prefix": prefix,
            "point_count": len(points),
            "snapped_nodes": [
                {
                    "name": s["name"],
                    "index": s["index"],
                    "snap_m": s["snap_m"],
                    "skipped_cut": s.get("skipped_cut", False),
                    "merged_with": s.get("merged_with"),
                }
                for s in snapped
            ],
            "segments": [
                {
                    "name": s["name"],
                    "from": s["from"],
                    "to": s["to"],
                    "distance_km": s["distance_km"],
                    "elevation_m": s["elevation_m"],
                    "point_count": s["point_count"],
                    "gpx_file": s["gpx_file"],
                }
                for s in segs
            ],
        })
        # also dump full geojson bundle (without huge duplication in summary)
        (out / f"{prefix}_segments.json").write_text(
            json.dumps(segs, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )

    (out / "report.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")

    # human summary
    lines = ["# Hoher Riffler — GPX Split", ""]
    lines.append(report["note"])
    lines.append("")
    for tr in report["tracks"]:
        lines.append(f"## {tr['prefix']} — {tr['file']} ({tr['point_count']} Punkte)")
        lines.append("")
        lines.append("### Snap")
        for s in tr["snapped_nodes"]:
            extra = " [MERGED/skip]" if s["skipped_cut"] else ""
            lines.append(f"- {s['name']}: idx={s['index']}, {s['snap_m']} m{extra}")
        lines.append("")
        lines.append("### Segmente")
        for s in tr["segments"]:
            lines.append(
                f"- **{s['name']}**: {s['distance_km']} km, {s['elevation_m']} Hm, "
                f"{s['point_count']} pts -> `{s['gpx_file']}`"
            )
        lines.append("")
    lines.append("## Seilbahn")
    lines.append("Bergstation <-> Talstation: 0 km / 0 Hm — Talstation-Koordinaten noch offen.")
    (out / "README.md").write_text("\n".join(lines), encoding="utf-8")
    summary = "\n".join(lines)
    print(summary.encode("cp1252", errors="replace").decode("cp1252"))
    print(f"\nOutput: {out}")


if __name__ == "__main__":
    main()
