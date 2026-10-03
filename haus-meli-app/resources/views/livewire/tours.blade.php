<div id="tours-app-container">

    <h2 class="super-heading tour-main-heading">
        <span class="material-symbols-rounded">explore</span>
        <span data-i18n="tours_title">Touren & Aktivitäten</span>
    </h2>

    <div class="card filter-section tours-filter-panel">
        <div class="filter-dropdown-row">
            <div class="multi-select-dd" id="filter-modes">
                <div class="dd-header dd-toggle-btn" data-target="filter-modes">
                    <span>Sportart</span><span class="custom-dd-arrow"></span>
                </div>
                <div class="dd-list"></div>
            </div>
            <div class="multi-select-dd" id="filter-startKinds">
                <div class="dd-header dd-toggle-btn" data-target="filter-startKinds">
                    <span>Erreichbarkeit</span><span class="custom-dd-arrow"></span>
                </div>
                <div class="dd-list"></div>
            </div>
            <div class="multi-select-dd" id="filter-diff">
                <div class="dd-header dd-toggle-btn" data-target="filter-diff">
                    <span>Schwierigkeit</span><span class="custom-dd-arrow"></span>
                </div>
                <div class="dd-list"></div>
            </div>
            <div class="multi-select-dd" id="filter-tags">
                <div class="dd-header dd-toggle-btn" data-target="filter-tags">
                    <span>Tags</span><span class="custom-dd-arrow"></span>
                </div>
                <div class="dd-list"></div>
            </div>
        </div>

                    <div class="tours-check-row">
            <label class="tours-check-option" for="filter-end-may-differ">
                <input type="checkbox" id="filter-end-may-differ">
                <span>Ende darf vom Start abweichen</span>
            </label>
            <label class="tours-check-option" for="filter-hut-overnight">
                <input type="checkbox" id="filter-hut-overnight">
                <span>Hüttenübernachtung</span>
            </label>
        </div>

        <div id="tours-mode-budgets" class="tours-mode-budgets" aria-label="Budgets pro Sportart"></div>
    </div>

    <div class="tours-map-block">
        <div class="tours-overview-toolbar">
            <div id="tours-map-legend" class="tours-map-legend"></div>
        </div>

        <p id="tours-overview-empty" class="tours-overview-empty" style="display:none;">
            Keine Segmente in der aktuellen Filterauswahl.
        </p>

        <div class="tours-map-layout">
            <div class="tours-map-col">
                <div class="tours-map-stage">
                    <div id="tours-overview-map" class="tours-overview-map"></div>
                    <button type="button" id="tours-map-load" class="tours-map-load">Karte laden</button>
                    <div id="tours-map-loading" class="tours-map-loading" hidden wire:ignore role="status" aria-live="polite">
                        <span class="tours-map-spinner" aria-hidden="true"></span>
                        <span>Karte wird geladen …</span>
                    </div>
                    <div id="tours-map-selection" class="tours-map-selection" hidden>
                        <div id="tours-sel-photos" class="tours-sel-photos" hidden></div>
                        <div class="tours-sel-body">
                            <strong id="tours-sel-title"></strong>
                            <div id="tours-sel-meta" class="tours-sel-meta"></div>
                            <div id="tours-sel-hut" class="tours-sel-hut" hidden></div>
                            <div id="tours-sel-stats" class="tours-sel-stats"></div>
                        </div>
                        <div id="tours-sel-route-actions" class="tours-sel-route-actions">
                            <button type="button" class="add-btn tours-sel-route-btn" id="tours-sel-route-btn" hidden>Zur Route hinzufügen</button>
                            <button type="button" class="add-btn tours-sel-route-btn tours-sel-alt-btn" id="tours-sel-start-btn" hidden>Als Start</button>
                            <button type="button" class="add-btn tours-sel-route-btn tours-sel-alt-btn" id="tours-sel-goal-btn" hidden>Als Ziel</button>
                            <button type="button" class="add-btn tours-sel-route-btn tours-sel-alt-btn" id="tours-sel-via-btn" hidden>Zwischenziel</button>
                        </div>
                    </div>
                    <div id="tours-mode-chooser" class="tours-mode-chooser" hidden>
                        <div class="tours-mode-chooser-inner">
                            <button type="button" class="tours-mode-chooser-close" id="tours-mode-chooser-close" title="Schließen" aria-label="Schließen">×</button>
                            <strong id="tours-mode-chooser-title">Sportart wählen</strong>
                            <div id="tours-mode-chooser-btns" class="tours-mode-chooser-btns"></div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="tours-route-bar" id="tours-route-bar">
                <div class="tours-route-head">
                    <strong>Routenplaner</strong>
                    <div class="tours-route-actions">
                        <button type="button" class="tours-route-icon-btn" id="tours-route-undo" title="Rückgängig" aria-label="Rückgängig">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 7v6h6"/>
                                <path d="M3 13a9 9 0 1 0 3-7.7L3 7"/>
                            </svg>
                        </button>
                        <button type="button" class="btn-delete tours-route-icon-btn" id="tours-route-clear" title="Route löschen" aria-label="Route löschen">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="tours-route-main">
                    <div class="tours-plan-box">
                        <div class="tours-plan-row">
                            <span class="tours-plan-label">Start</span>
                            <strong id="tours-plan-start" class="tours-plan-value">— tippen —</strong>
                        </div>
                        <div class="tours-plan-row">
                            <span class="tours-plan-label">Ziel</span>
                            <strong id="tours-plan-goal" class="tours-plan-value">— tippen —</strong>
                        </div>
                        <div class="tours-plan-row tours-plan-vias-row">
                            <span class="tours-plan-label">Via</span>
                            <div id="tours-plan-vias" class="tours-plan-vias"></div>
                        </div>
                        <div class="tours-trip-shape" role="radiogroup" aria-label="Fahrtform">
                            <label><input type="radio" name="tours-trip-shape" value="one_way"> Einfach</label>
                            <label><input type="radio" name="tours-trip-shape" value="out_and_back" checked> Hin+Zurück</label>
                            <label><input type="radio" name="tours-trip-shape" value="loop"> Rundweg</label>
                        </div>
                        <div class="tours-plan-actions-row">
                            <button type="button" class="add-btn" id="tours-plan-compute">Route berechnen</button>
                            <button type="button" class="add-btn tours-plan-secondary-btn" id="tours-plan-save" hidden>Speichern</button>
                        </div>
                        <div id="tours-plan-status" class="tours-plan-status"></div>
                    </div>
                    <span id="tours-route-hint" class="tours-route-hint">Start und Ziel auf der Karte wählen</span>
                    <div id="tours-route-stats" class="tours-route-stats"></div>
                    <div id="tours-route-start" class="tours-route-start" hidden></div>
                    <ul id="tours-route-steps" class="tours-route-steps"></ul>
                </div>
            </aside>
        </div>

        <div id="tours-saved-list" class="tours-saved-list" aria-label="Gespeicherte Touren"></div>
    </div>

    <details class="tours-segment-drawer" id="tours-segment-drawer">
        <summary class="tours-segment-drawer-summary">
            <span>Segmente</span>
            <span id="tours-segment-drawer-count" class="tours-segment-drawer-count"></span>
        </summary>
        <div id="hike-results" class="hike-grid"></div>
    </details>

    <div id="tours-takeaway-overlay" class="tours-takeaway-overlay" hidden>
        <div class="tours-takeaway-dialog" role="dialog" aria-modal="true" aria-labelledby="tours-takeaway-title">
            <div class="tours-takeaway-head">
                <strong id="tours-takeaway-title">Route mitnehmen</strong>
                <button type="button" class="tours-route-icon-btn" id="tours-takeaway-close" title="Schließen" aria-label="Schließen">×</button>
            </div>
            <p class="tours-takeaway-lead">QR scannen → Route auf der Karte mitlaufen. GPS im Browser nur mit HTTPS; sonst GPX speichern und in einer App öffnen.</p>
            <div id="tours-takeaway-lan" class="tours-takeaway-lan" hidden>
                <label for="tours-takeaway-lan-input">Handy-Link (PC-IP im WLAN)</label>
                <div class="tours-takeaway-lan-row">
                    <input type="url" id="tours-takeaway-lan-input" placeholder="http://192.168.1.4:8000" autocomplete="off" spellcheck="false">
                    <button type="button" class="add-btn" id="tours-takeaway-lan-apply">Übernehmen</button>
                </div>
                <p class="tours-takeaway-lan-hint">Herd (<code>.test</code>) ist nur lokal. Fürs Handy: <code>serve.bat</code> starten (Port 8000), dann diese Adresse eintragen.</p>
            </div>
            <div class="tours-takeaway-qr-wrap">
                <img id="tours-takeaway-qr" class="tours-takeaway-qr" alt="QR-Code zur Route" width="220" height="220">
            </div>
            <p id="tours-takeaway-status" class="tours-takeaway-status"></p>
            <a id="tours-takeaway-link" class="tours-takeaway-link" href="#" target="_blank" rel="noopener" hidden></a>
        </div>
    </div>

</div>
