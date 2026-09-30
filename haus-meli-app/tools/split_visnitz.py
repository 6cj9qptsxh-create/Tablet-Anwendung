#!/usr/bin/env python3
"""Split Visnitz/Mittagkopf GPX into Hin + Retour segments."""
from __future__ import annotations

import json
import math
import re
from pathlib import Path

GPX_IN = Path(r"c:\Users\luggi\Downloads\2026-07-19_3120710416_Tour nach Visnitzer Wasserfall.gpx")
OUT = Path(__file__).resolve().parents[1] / "storage" / "tour_splits" / "visnitz-mittagkopf"


def hav(lat1, lon1, lat2, lon2):
    r = 6371000.0
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dl = math.radians(lon2 - lon1)
    a = math.sin(dphi / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * r * math.asin(min(1.0, math.sqrt(a)))


def parse_gpx(path: Path):
    import xml.etree.ElementTree as ET

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


def spur_return(pts, i_abzw, i_dest, window=40):
    """Nach Spur-Ziel wieder zum Abzweig (Lichtsee-Muster)."""
    i_back = i_dest
    best_d = float("inf")
    for i in range(i_dest, min(i_dest + window, len(pts))):
        d = hav(pts[i_abzw]["lat"], pts[i_abzw]["lng"], pts[i]["lat"], pts[i]["lng"])
        if d < best_d:
            best_d = d
            i_back = i
        if i > i_dest + 2 and d > best_d + 15 and best_d < 25:
            break
    return i_back, best_d


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


def slug(a: str, b: str) -> str:
    s = f"{a}-{b}".lower()
    s = re.sub(r"[^a-z0-9]+", "-", s).strip("-")
    return s


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


def emit_seg(out_dir: Path, segments: list, frm: str, to: str, points: list, direction: str):
    if len(points) < 2:
        return
    name = f"{frm} → {to}"
    st = stats(points)
    prefix = "hin" if direction == "hin" else "retour"
    gpx_name = f"{prefix}_{slug(frm, to)}.gpx"
    write_gpx(out_dir / gpx_name, name, points)
    segments.append({
        "name": name,
        "from": frm,
        "to": to,
        "gpx_file": gpx_name,
        "bidirectional": False,
        "out_and_back": False,
        "is_highlight": False,
        "direction": direction,
        **st,
    })
    print(f"  {direction:6} {name.replace(chr(0x2192), '->'):50} {st['distance_km']:.2f} km / {st['elevation_m']} Hm")


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    pts = parse_gpx(GPX_IN)
    print(f"Track points: {len(pts)}")

    # Abzweigung Wasserfall = Spur wie Lichtsee (nicht Tal→Wasserfall als Hauptlinie)
    i_tal_h, _ = nearest(pts, 47.060256, 10.374533, 0, 20)
    i_abzw_wf_h, _ = nearest(pts, 47.055967, 10.372470, i_tal_h, 40)
    i_wf_h, _ = nearest(pts, 47.055516, 10.372190, i_abzw_wf_h, 50)
    i_abzw_wf_back, d_wf_back = spur_return(pts, i_abzw_wf_h, i_wf_h, window=25)

    i_fw_h, _ = nearest(pts, 47.051719, 10.376515, i_abzw_wf_back, 80)
    i_aus, _ = nearest(pts, 47.052268, 10.368026, i_fw_h, 110)
    i_fw_back, _ = nearest(pts, 47.051719, 10.376515, i_aus, 160)
    i_alpe_h, _ = nearest(pts, 47.037890, 10.371955, i_fw_back, 200)
    i_abzw_h, _ = nearest(pts, 47.050149, 10.385977, i_alpe_h, 250)
    i_gipfel, _ = nearest(pts, 47.052204, 10.394967, i_abzw_h, 300)

    i_abzw_r, _ = nearest(pts, 47.050149, 10.385977, i_gipfel + 1, 350)
    i_alpe_r, _ = nearest(pts, 47.037890, 10.371955, i_abzw_r, 400)
    i_fw_r, _ = nearest(pts, 47.051719, 10.376515, i_alpe_r, 450)

    # Retour: Spur Wasserfall analog Hin
    i_abzw_wf_r, _ = nearest(pts, 47.055967, 10.372470, i_fw_r, 470)
    i_wf_r, _ = nearest(pts, 47.055516, 10.372190, i_abzw_wf_r, 480)
    i_abzw_wf_r_back, _ = spur_return(pts, i_abzw_wf_r, i_wf_r, window=25)
    i_tal_r, _ = nearest(pts, 47.060256, 10.374533, i_abzw_wf_r_back, len(pts) - 1)

    print("indices", {
        "tal_h": i_tal_h,
        "abzw_wf_h": i_abzw_wf_h,
        "wf_h": i_wf_h,
        "abzw_wf_back": i_abzw_wf_back,
        "d_wf_back_m": round(d_wf_back, 1),
        "fw_h": i_fw_h,
        "aus": i_aus,
        "fw_back": i_fw_back,
        "alpe_h": i_alpe_h,
        "abzw_h": i_abzw_h,
        "gipfel": i_gipfel,
        "abzw_r": i_abzw_r,
        "alpe_r": i_alpe_r,
        "fw_r": i_fw_r,
        "abzw_wf_r": i_abzw_wf_r,
        "wf_r": i_wf_r,
        "abzw_wf_r_back": i_abzw_wf_r_back,
        "tal_r": i_tal_r,
    })

    nodes = [
        {"name": "Diasbahn Talstation", "lat": 47.060256, "lng": 10.374533, "flags": ["seilbahn", "bus", "auto"]},
        {"name": "Abzweigung Wasserfall", "lat": 47.055967, "lng": 10.372470, "flags": ["junction"]},
        {"name": "Visnitzer Wasserfall", "lat": 47.055516, "lng": 10.372190, "flags": ["highlight"]},
        {"name": "Abzweigung Fahrweg", "lat": 47.051719, "lng": 10.376515, "flags": ["junction"]},
        {"name": "schöne Aussicht", "lat": 47.052268, "lng": 10.368026, "flags": []},
        {
            "name": "Visnitz Alpe",
            "lat": 47.037890,
            "lng": 10.371955,
            "flags": ["hut"],
            "offers_food": True,
            "offers_lodging": False,
            "season_open": "19.06. – 27.09.",
        },
        {"name": "Abzweigung Mittagkopf", "lat": 47.050149, "lng": 10.385977, "flags": ["junction"]},
        {"name": "Mittagkopf-Gipfel", "lat": 47.052204, "lng": 10.394967, "flags": ["highlight"]},
    ]

    segments: list = []
    # Hin — Wasserfall als Spur ab Abzweigung
    emit_seg(OUT, segments, "Diasbahn Talstation", "Abzweigung Wasserfall", pts[i_tal_h:i_abzw_wf_h + 1], "hin")
    emit_seg(OUT, segments, "Abzweigung Wasserfall", "Visnitzer Wasserfall", pts[i_abzw_wf_h:i_wf_h + 1], "hin")
    emit_seg(OUT, segments, "Visnitzer Wasserfall", "Abzweigung Wasserfall", pts[i_wf_h:i_abzw_wf_back + 1], "retour")
    emit_seg(OUT, segments, "Abzweigung Wasserfall", "Abzweigung Fahrweg", pts[i_abzw_wf_back:i_fw_h + 1], "hin")
    emit_seg(OUT, segments, "Abzweigung Fahrweg", "schöne Aussicht", pts[i_fw_h:i_aus + 1], "hin")
    emit_seg(OUT, segments, "schöne Aussicht", "Abzweigung Fahrweg", pts[i_aus:i_fw_back + 1], "retour")
    emit_seg(OUT, segments, "Abzweigung Fahrweg", "Visnitz Alpe", pts[i_fw_back:i_alpe_h + 1], "hin")
    emit_seg(OUT, segments, "Visnitz Alpe", "Abzweigung Mittagkopf", pts[i_alpe_h:i_abzw_h + 1], "hin")
    emit_seg(OUT, segments, "Abzweigung Mittagkopf", "Mittagkopf-Gipfel", pts[i_abzw_h:i_gipfel + 1], "hin")
    # Retour
    emit_seg(OUT, segments, "Mittagkopf-Gipfel", "Abzweigung Mittagkopf", pts[i_gipfel:i_abzw_r + 1], "retour")
    emit_seg(OUT, segments, "Abzweigung Mittagkopf", "Visnitz Alpe", pts[i_abzw_r:i_alpe_r + 1], "retour")
    emit_seg(OUT, segments, "Visnitz Alpe", "Abzweigung Fahrweg", pts[i_alpe_r:i_fw_r + 1], "retour")
    # Spur Abzw↔Wasserfall nur einmal (Hin) — Retour nutzt dieselben Kanten
    emit_seg(OUT, segments, "Abzweigung Fahrweg", "Abzweigung Wasserfall", pts[i_fw_r:i_abzw_wf_r + 1], "retour")
    emit_seg(OUT, segments, "Abzweigung Wasserfall", "Diasbahn Talstation", pts[i_abzw_wf_r_back:i_tal_r + 1], "retour")

    data = {
        "nodes": nodes,
        "segments": segments,
        "meta": {
            "source_gpx": str(GPX_IN),
            "note": (
                "Wasserfall = Spur Hin+Retour ab Abzweigung Wasserfall (wie Lichtsee). "
                "Aussicht = Spur ab Abzweigung Fahrweg. Retour ohne Aussicht."
            ),
        },
    }
    (OUT / "segments.json").write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    (OUT / "README.md").write_text(
        "# Visnitz / Mittagkopf\n\n"
        "Talstation → Abzw Wasserfall ⇄ Wasserfall → Fahrweg ⇄ Aussicht → Alpe → Mittagkopf → Retour.\n"
        "Visnitz Alpe: Essen, Saison 19.06.–27.09.\n",
        encoding="utf-8",
    )
    print(f"Wrote {len(segments)} segments → {OUT}")


if __name__ == "__main__":
    main()
