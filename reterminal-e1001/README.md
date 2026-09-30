# E1001 — schlankes Dashboard

Wohnzimmer + Terrasse (Sonoff) und die Privat-Agenda:

- Mitte: **Termine**
- Rechts oben: **Geburtstage**
- Rechts unten: **nächste Schichten**

Seite 2: **5-Tage-Vorschau** und **heutige Stunden**. Keine Temperatur/Luftfeuchte — die bleiben auf Seite 1.

| Taste | Funktion |
| --- | --- |
| Grün | nächste Seite |
| Links weiß | andere Seite |
| Rechts weiß | aktuelle Seite neu zeichnen |

## 1. Agenda-Feed in der Tablet-App

Die App unter Port 8081 liefert:

`http://192.168.1.10:8081/api/e1001/agenda?token=DEIN_TOKEN`

In der NAS-`.env` von haus-meli-app setzen:

```
E1001_AGENDA_TOKEN=irgendein-langes-geheimnis
```

## 2. Home Assistant

Direkt in `configuration.yaml` (kein Include nötig):

```yaml
rest:
  - resource: http://192.168.1.10:8081/api/e1001/agenda
    scan_interval: 300
    sensor:
      - name: E1001 Schichten
        unique_id: e1001_schichten
        value_template: "{{ value_json.shifts_count }}"
        json_attributes:
          - shifts_text
      - name: E1001 Termine
        unique_id: e1001_termine
        value_template: "{{ value_json.notes_count }}"
        json_attributes:
          - notes_text
      - name: E1001 Geburtstage
        unique_id: e1001_geburtstage
        value_template: "{{ value_json.birthdays_count }}"
        json_attributes:
          - birthdays_text
```

Danach in HA: Konfiguration prüfen, dann **REST-Entities neu laden** (oder HA neu starten).

Es entstehen:

- `sensor.e1001_schichten` (Attribut `shifts_text`)
- `sensor.e1001_termine` (Attribut `notes_text`)
- `sensor.e1001_geburtstage` (Attribut `birthdays_text`)

Ohne den Geburtstags-Sensor bleibt nach dem ESPHome-OTA die rechte obere Fläche leer.
Wenn die App ein `E1001_AGENDA_TOKEN` setzt, denselben Token als `params.token` ergänzen.

## 2b. Wetter für Seite 2

Dieselben zwei Sensoren kommen jetzt aus der Tablet-App (meteoblue, Ort Lauterach), nicht mehr aus `weather.forecast_home`.

In `configuration.yaml` unter `rest:` den zweiten Block aus `ha/e1001_rest.yaml` einfügen. Den alten `template:`-Block fürs Wetter entfernen, sonst gibt es die Entitäten doppelt.

Danach: Konfiguration prüfen, **REST-Entities neu laden**.

Es entstehen:

- `sensor.e1001_wetter_tage` (Attribut `days_text`)
- `sensor.e1001_wetter_stunden` (Attribut `hours_text`)

`days_text` hat pro Tag: Name, Symbol, Höchstwert, Tiefstwert, Regenwahrscheinlichkeit, Regenmenge, Sonnenstunden, Wind und acht Balkenhöhen (0–3 Uhr bis 21–24 Uhr, kommagetrennt). `hours_text` bleibt Uhrzeit, Symbol, Temperatur, Millimeter.

Ohne diese Sensoren zeigt Seite 2 „kein Wetter“. Die Balken zeichnet erst die aktuelle `reterminal-e1001.yaml`. Dafür das Gerät einmal neu flashen.

## 3. ESPHome flashen

`esphome/reterminal-e1001.yaml` ins ESPHome-Add-on. WLAN in `secrets.yaml`. USB-Flash, danach OTA.

Grün = Seite wechseln, rechts weiß = neu zeichnen.

Automatischer Refresh (`binary_sensor.e1001_jemand_da`):

- Handy **wach:** irgendein Netz (`Wi-Fi` oder `Cellular`) **und** kein Fokus
- oder LED Büro / LED TV / Stehlampe **an**
- Fokus Schlafen oder Flugmodus (kein Netz) → dieses Handy zählt nicht
- Taste zeichnet immer

Kein GPS / keine Activity. Alte Entität `E1001 Handy GPS bewegt` in HA löschen, falls sie noch da ist.

Companion-Sensoren auf dem iPhone einschalten (**Einstellungen → Companion App → Sensoren**):

- **Connection Type** → `sensor.iphone_von_lukas_connection_type` / `…melanie…` (`Wi-Fi` / `Cellular` / `No Connection`)
- **Focus** → `binary_sensor.iphone_von_lukas_focus` / `…melanie…`

Schlaf-Fokus: iOS **Einstellungen → Fokus → Fokusstatus** für Schlafen teilen. Home Assistant **nicht** unter „Erlaubte Apps“ für diesen Fokus. HA kennt nur Fokus an/aus, nicht den Namen — andere Foki (Fahren, Arbeit) zählen deshalb auch als Schlafen.

Reihenfolge ins Bett: **zuerst Fokus Schlafen**, dann Flugmodus. Sonst kann das Handy „kein Netz“ oft nicht mehr an HA schicken und der letzte WLAN-Stand bleibt liegen.

Companion-App: ja, die wird **auf dem Handy** installiert (Play Store / App Store: „Home Assistant“), mit eurer HA-URL anmelden. Danach gibt es `device_tracker.…` mit `home` / `not_home`.

OTA: Taste drücken oder in HA **OTA wach halten**, dann Wireless-Install. Nach dem Zeichnen (~6 s) geht das Gerät wieder schlafen.

`Jemand da` auf **an** zeichnet **nicht sofort**. Im Deep Sleep ist kein WLAN — HA erreicht das Terminal nicht. Es wacht zum Prüfen auf: in zwei Zeitfenstern **15 Min** (`6–9` und `16–20` Uhr), sonst **60 Min**. Dann liest es den Sensor und zeichnet (oder überspringt das Bild, wenn niemand da ist). Taste = sofort. Fenster/Intervalle oben in der ESPHome-YAML: `short_a_from` / `short_a_until`, `short_b_from` / `short_b_until`, `away_short_min`, `away_long_min`. Gleiche from/until = Fenster aus. Über Mitternacht z.B. `22` und `7`.
