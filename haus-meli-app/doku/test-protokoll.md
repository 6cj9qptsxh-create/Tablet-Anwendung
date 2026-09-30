Abnahme-Protokoll: Haus Meli OS (NAS Deployment)
Datum der Testung: ____________________
Test-Gerät(e): ____________________ (z.B. iPad Pro, iPhone 13, Windows PC)
Browser: ____________________ (z.B. Safari, Chrome)

🧭 Phase 1: System-Basis & Navigation (Smoke-Test)
[ ] Initiale Ladezeit: Die Seite lädt zügig und ohne weiße Fehlerseiten oder Abstürze.

[ ] Tab-Switching (SPA): Der Wechsel zwischen den Tabs (Shop, Wohnung, Events, Infos, Touren) funktioniert sofort und butterweich, ohne dass der Browser die Seite neu laden muss.

[ ] Bild-Ressourcen: Produktbilder (Shop) und Touren-Bilder werden korrekt geladen (keine gebrochenen Links).

[ ] Header-Elemente: Die Uhrzeit oben rechts tickt im Sekundentakt.

[ ] Mehr-Menü: Das Dropdown-Menü "Mehr" öffnet und schließt sich sauber, auch wenn man daneben klickt.

🛒 Phase 2: Shop & Bestellsystem (Cache & DB)
[ ] Modus-Wechsel: Der Wechsel zwischen "Lieferung 🚚" und "Selbstbedienung 🏪" aktualisiert die angezeigten Produkte korrekt.

[ ] Sale-Logik: Bei Aktions-Produkten ist das rote "-15%" Badge und der durchgestrichene Originalpreis zu sehen. Datum Check. Wenn Feiertage dazwischen sind wird es richtig erkannt

[ ] Warenkorb-Limit: Ein Produkt lässt sich in den Warenkorb legen. Der Plus-Button wird deaktiviert, wenn der maximale Lagerbestand (stock) erreicht ist.

[ ] Echtzeit-Sync (Härtetest): Ein Artikel wird auf Gerät A in den Warenkorb gelegt. Auf Gerät B wird "🔄 Synchronisieren" geklickt -> Der Artikel erscheint auf Gerät B.

[ ] Bestellung absenden: Klick auf "Kostenpflichtig bestellen" leert den Warenkorb und verschiebt die Artikel in das Historien-Modal.

[ ] Checkout-Modal: In der Historie erscheint unten der rote Button "Kostenpflichtig abrechnen" (falls der heutige Tag der Abreisetag ist).

[ ] QR-Code Generierung: Bei Auswahl "Bar / Überweisung" wird der Bank-QR-Code (EPC-Standard) mit korrektem Betrag, Name und IBAN generiert.

[ ] Abschluss: Klick auf "Zahlung getätigt & Abschließen" löscht die Historie. Ein Feedback kann eingetippt und erfolgreich gesendet werden.

📅 Phase 3: Eventkalender (Performance & Funktionen)
[ ] Scroll-Snapping: Horizontales Wischen im Kalender rastet sanft und exakt auf den einzelnen Tagen ein.

[ ] Live-Uhrzeit: Die rote Indikator-Linie wird bei der aktuellen Uhrzeit (zwischen 06:00 und 24:00 Uhr) korrekt angezeigt.

[ ] Feiertags-Generierung: Ostern oder andere gesetzliche Feiertage werden als ganztägige, rote Events oben im Kalender angezeigt.

[ ] Event anlegen (DB-Write): Klick auf den "+" Button (FAB). Ein "Test-Essen" für morgen kann angelegt, gespeichert und im Kalender gesehen werden.

[ ] Favoriten filtern: Ein Event wird angeklickt und mit dem Herz "❤️" markiert. Nach Klick auf "Mein Plan" im Filtermenü werden nur noch markierte/private Events angezeigt.

🥾 Phase 4: Touren & Lightbox (JavaScript & Touch)
[ ] Slider-Filterung: Das Bewegen der Distanz- und Höhenmeter-Slider aktualisiert die Touren-Liste dynamisch in Echtzeit.

[ ] Slider-Snap: Beim Loslassen der Regler rasten diese auf sauberen Werten (z.B. volle 50 Höhenmeter) ein.

[ ] Kategorie-Tabs: Der Wechsel zwischen "Wandern", "Bike & Hike", etc. lädt die entsprechenden Touren fehlerfrei.

[ ] Swipe-Lightbox (Touch): Ein Bild in der Lightbox lässt sich mit dem Finger nach links/rechts ziehen (Bild klebt 1:1 am Finger).

[ ] Geisterklick-Schutz: Nach einem erfolgreichen Swipe-Vorgang schließt sich die Galerie beim Loslassen nicht versehentlich.

[ ] Schließen: Ein Klick auf den dunklen Hintergrund der Lightbox schließt diese sauber.

ℹ️ Phase 5: Infos & Wetter (API-Test)
[ ] Wetter-API: Im Reiter "Infos" wird nach 1-2 Sekunden die 5-Tages-Wettervorschau (inkl. Icons, Temp., Regen, Wind) für Lauterach geladen.

[ ] Header-Wetter: Oben links in der Navigationsleiste wird das aktuelle Wetter-Icon und die Temperatur passend zur API angezeigt.

[ ] QR-Codes: Die Info-QR-Codes (WLAN & App-Download) werden erfolgreich von der externen API geladen und angezeigt.

[ ] Externe Verlinkungen: Links (z.B. Busfahrplan, Tourismusbüro) öffnen sich wie gewünscht in einem neuen Browser-Tab (target="_blank").