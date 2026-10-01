<div class="container info-app"
     @haus-weather-hour.window="$wire.$refresh()"
     x-data="{
        section: (function () {
            try {
                const saved = localStorage.getItem('hausMeliInfoSection');
                if (saved === 'now' || saved === 'haus' || saved === 'out' || saved === 'wetter') return saved;
            } catch (e) {}
            return 'now';
        })(),
        copied: '',
        setSection(name) {
            this.section = name;
            try { localStorage.setItem('hausMeliInfoSection', name); } catch (e) {}
            const pane = this.$root.closest('.tab-pane');
            if (pane) pane.scrollTo(0, 0);
        },
        async copy(key, text) {
            const value = (text || '').replace(/\s+/g, ' ').trim();
            if (!value || value === 'Laden...' || value === '...') return;
            let ok = false;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(value);
                    ok = true;
                }
            } catch (e) {}
            if (!ok) {
                try {
                    const field = document.createElement('textarea');
                    field.value = value;
                    field.setAttribute('readonly', '');
                    field.style.position = 'fixed';
                    field.style.left = '-9999px';
                    document.body.appendChild(field);
                    field.select();
                    ok = document.execCommand('copy');
                    field.remove();
                } catch (e) {}
            }
            if (!ok) return;
            this.copied = key;
            clearTimeout(this._copyT);
            this._copyT = setTimeout(() => { this.copied = ''; }, 1400);
        }
     }">

    <div class="info-toolbar">
        <div class="info-switch" role="tablist" aria-label="Infobereiche">
            <button type="button" role="tab" class="info-switch-btn" :class="{ 'is-on': section === 'now' }" :aria-selected="section === 'now'" @click="setSection('now')">Jetzt</button>
            <button type="button" role="tab" class="info-switch-btn" :class="{ 'is-on': section === 'haus' }" :aria-selected="section === 'haus'" @click="setSection('haus')">Haus</button>
            <button type="button" role="tab" class="info-switch-btn" :class="{ 'is-on': section === 'out' }" :aria-selected="section === 'out'" @click="setSection('out')">Umgebung</button>
            <button type="button" role="tab" class="info-switch-btn" :class="{ 'is-on': section === 'wetter' }" :aria-selected="section === 'wetter'" @click="setSection('wetter')">Wetter</button>
        </div>
    </div>

    <div class="info-panel" x-show="section === 'now'" x-cloak>
        <div class="info-sos">
            <div class="info-sos-nums">
                <a class="info-sos-btn" href="tel:144">
                    <span class="material-symbols-rounded">local_hospital</span>
                    <span>
                        <small>Rettung / Notarzt</small>
                        <strong>144</strong>
                    </span>
                </a>
                <a class="info-sos-btn" href="tel:133">
                    <span class="material-symbols-rounded">local_police</span>
                    <span>
                        <small>Polizei</small>
                        <strong>133</strong>
                    </span>
                </a>
            </div>
            <a class="info-line is-sos" href="tel:+43123456789">
                <span class="info-line-ico"><span class="material-symbols-rounded">stethoscope</span></span>
                <span class="info-line-text">
                    <strong>Allgemeinarzt Dr. Müller</strong>
                    <p>Dorfstraße 12 · +43 123 456 789</p>
                </span>
                <span class="material-symbols-rounded info-go">call</span>
            </a>
        </div>

        <div class="info-status">
            <span class="material-symbols-rounded">schedule</span>
            <span><strong data-i18n="lbl_checkout">Check-out</strong> bis 10:00 Uhr am Abreisetag</span>
        </div>

        <div class="info-kicker" data-i18n="info_wifi_title">Verbinden</div>
        <div class="info-block info-connect">
            <div class="info-copy-list">
                <button type="button" class="info-copy" :class="{ 'is-copied': copied === 'ssid' }" @click="copy('ssid', $refs.ssid.textContent)">
                    <span class="info-copy-body">
                        <span class="info-copy-label" data-i18n="info_network">Netzwerk</span>
                        <strong id="wifi-name-display" x-ref="ssid">Mein_Ferien_WLAN</strong>
                    </span>
                    <span class="material-symbols-rounded" x-text="copied === 'ssid' ? 'check' : 'content_copy'"></span>
                </button>
                <button type="button" class="info-copy is-mono" :class="{ 'is-copied': copied === 'pass' }" @click="copy('pass', $refs.pass.textContent)">
                    <span class="info-copy-body">
                        <span class="info-copy-label" data-i18n="info_password">Passwort</span>
                        <strong id="wifi-pass-display" x-ref="pass">urlaub2026</strong>
                    </span>
                    <span class="material-symbols-rounded" x-text="copied === 'pass' ? 'check' : 'content_copy'"></span>
                </button>
                <p class="info-app-note" data-i18n="info_app_text">Scannen Sie den App-Code, um die Anwendung auf dem Handy zu öffnen.</p>
            </div>
            <div class="info-qrs">
                <figure class="info-qr">
                    <img id="wifi-qr-code" src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=WIFI:T:WPA;S:Mein_Ferien_WLAN;P:urlaub2026;;" alt="WLAN QR" width="120" height="120" />
                    <figcaption>WLAN</figcaption>
                </figure>
                <figure class="info-qr info-app-qr">
                    <img id="app-url-qr-code" src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data={{ urlencode(rtrim((string) config('app.url'), '/').'/') }}" alt="App QR" width="120" height="120" />
                    <figcaption data-i18n="info_app_title">App</figcaption>
                </figure>
            </div>
        </div>
    </div>

    <div class="info-panel" x-show="section === 'wetter'" x-cloak>
        @if(empty($forecast['ok']))
            <p class="info-weather-loading">{{ $forecast['error'] ?? 'Wetterdaten fehlen.' }}</p>
        @else
            <div class="info-block wx-board-wrap" x-data="{ day: 0 }">
                <div class="wx-place">{{ $forecast['place'] }}</div>
                <div class="wx-board" role="tablist" aria-label="Tage">
                    @foreach($forecast['days'] as $index => $day)
                        <button type="button"
                                class="wx-col"
                                role="tab"
                                :class="{ 'is-on': day === {{ $index }} }"
                                :aria-selected="day === {{ $index }}"
                                @click="day = {{ $index }}">
                            <span class="wx-col-name">{{ $day['name'] }}</span>
                            <span class="wx-col-icon-wrap">
                                @if($index > 0 && !empty($forecast['days'][$index - 1]['night']))
                                    <span class="wx-night is-{{ $forecast['days'][$index - 1]['night']['icon'] }}" title="{{ $forecast['days'][$index - 1]['night']['label'] }}">
                                        <span class="material-symbols-rounded">{{ $forecast['days'][$index - 1]['night']['icon'] }}</span>
                                    </span>
                                @endif
                                <span class="material-symbols-rounded wx-col-icon is-{{ $day['icon'] }}">{{ $day['icon'] }}</span>
                            </span>
                            <span class="wx-col-max">{{ $day['max'] }}°</span>
                            <span class="wx-col-min">{{ $day['min'] }}°</span>
                            <span class="wx-pop-pct">{{ $day['pop'] > 0 ? $day['pop'].'%' : '' }}</span>
                            <span class="wx-rain" aria-hidden="true">
                                @foreach($day['rain_slots'] as $slot)
                                    <span class="wx-rain-slot" title="{{ $slot['label'] }}{{ $slot['mm'] > 0 ? ' · '.$slot['mm'].' mm' : '' }}">
                                        @if($slot['height'] > 0)
                                            <span class="wx-rain-bar" style="height: {{ $slot['height'] }}%"></span>
                                        @endif
                                    </span>
                                @endforeach
                            </span>
                            <span class="wx-col-rain">{{ $day['rain_text'] }}</span>
                            <span class="wx-col-sun {{ !empty($day['sun_text']) ? 'is-on' : '' }}">{{ $day['sun_text'] ?? '–' }}</span>
                            <span class="wx-col-wind">{{ $day['wind_text'] }}</span>
                        </button>
                    @endforeach
                </div>
                <p class="wx-rain-note">Blaue Balken sind der Regen im Tagesverlauf. Links ist die Nacht, rechts der Abend.</p>
                @foreach($forecast['days'] as $index => $day)
                    <div class="wx-detail" x-show="day === {{ $index }}" x-cloak>
                        <div class="wx-detail-head">
                            <strong>{{ $day['name'] }}</strong>
                            <span>{{ $day['title'] }} · {{ $day['label'] }} · {{ $day['min'] }}° bis {{ $day['max'] }}° · {{ $day['rain_text'] }}</span>
                        </div>
                        <div class="wx-hours" @if($index === 0) x-effect="
                            if (section !== 'wetter' || day !== 0) return;
                            $nextTick(() => requestAnimationFrame(() => requestAnimationFrame(() => {
                                const now = $el.querySelector('.is-now');
                                if (!now || $el.clientWidth === 0) return;
                                $el.scrollLeft = now.getBoundingClientRect().left - $el.getBoundingClientRect().left + $el.scrollLeft;
                            })));
                        " @endif>
                            @foreach($day['hours'] as $hour)
                                <div class="wx-hour {{ !empty($hour['is_now']) ? 'is-now' : '' }}">
                                    <span class="wx-hour-time">{{ $hour['time'] }}</span>
                                    <span class="material-symbols-rounded wx-hour-icon is-{{ $hour['icon'] }}">{{ $hour['icon'] }}</span>
                                    <strong>{{ $hour['temp'] }}°</strong>
                                    <span class="wx-hour-track">
                                        @if(($hour['rain_height'] ?? 0) > 0)
                                            <span class="wx-hour-bar" style="height: {{ $hour['rain_height'] }}%"></span>
                                        @endif
                                    </span>
                                    <span class="wx-hour-rain">{{ ($hour['rain'] ?? 0) > 0 ? $hour['rain_text'] : '–' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="info-panel" x-show="section === 'haus'" x-cloak>
        <div class="info-kicker" data-i18n="info_apt_title">Infos zur Wohnung</div>
        <div class="info-rows">
            <div class="info-line is-house">
                <span class="info-line-ico"><span class="material-symbols-rounded">schedule</span></span>
                <span class="info-line-text">
                    <strong data-i18n="lbl_checkout">Check-out</strong>
                    <p>Bis 10:00 Uhr am Abreisetag</p>
                </span>
            </div>
            <div class="info-line is-house">
                <span class="info-line-ico"><span class="material-symbols-rounded">volume_off</span></span>
                <span class="info-line-text">
                    <strong data-i18n="lbl_quiet">Ruhezeiten</strong>
                    <p>Bitte ab 22:00 Uhr Zimmerlautstärke</p>
                </span>
            </div>
            <div class="info-line is-house">
                <span class="info-line-ico"><span class="material-symbols-rounded">recycling</span></span>
                <span class="info-line-text">
                    <strong data-i18n="lbl_trash">Mülltrennung</strong>
                    <p>Behälter befinden sich im Hof</p>
                </span>
            </div>
        </div>

        <div class="info-kicker" data-i18n="info_how_title">So funktioniert's</div>
        <div class="info-rows">
            <details class="info-fold">
                <summary>
                    <span class="info-line-ico"><span class="material-symbols-rounded">tv</span></span>
                    <span class="info-line-text">
                        <strong data-i18n="lbl_tv">Smart TV</strong>
                        <p>Home drücken, dann Netflix oder YouTube</p>
                    </span>
                    <span class="material-symbols-rounded info-chev">expand_more</span>
                </summary>
                <ol>
                    <li>Fernbedienung „Home“ drücken</li>
                    <li>Netflix oder YouTube auswählen</li>
                </ol>
            </details>
            <details class="info-fold">
                <summary>
                    <span class="info-line-ico"><span class="material-symbols-rounded">coffee_maker</span></span>
                    <span class="info-line-text">
                        <strong data-i18n="lbl_coffee">Kaffeemaschine</strong>
                        <p>Hebel, Kapsel, Taste</p>
                    </span>
                    <span class="material-symbols-rounded info-chev">expand_more</span>
                </summary>
                <ol>
                    <li>Hebel ganz nach hinten drücken</li>
                    <li>Kapsel einlegen</li>
                    <li>Taste drücken</li>
                </ol>
            </details>
            <details class="info-fold">
                <summary>
                    <span class="info-line-ico"><span class="material-symbols-rounded">thermostat</span></span>
                    <span class="info-line-text">
                        <strong data-i18n="lbl_heating">Heizung</strong>
                        <p>Läuft automatisch</p>
                    </span>
                    <span class="material-symbols-rounded info-chev">expand_more</span>
                </summary>
                <ol>
                    <li>Die Heizung ist automatisch geregelt</li>
                    <li>Manuell über die Wandthermostate änderbar</li>
                </ol>
            </details>
        </div>
    </div>

    <div class="info-panel" x-show="section === 'out'" x-cloak>
        <div class="info-kicker" data-i18n="info_links_title">Nützliche Links</div>
        <div class="info-rows">
            <a class="info-line is-link" href="https://www.vvt.at/data.cfm?vpath=ma-wartbare-inhalte/fahrplan-pdfs/linie-26037614" target="_blank" rel="noopener">
                <span class="info-line-ico"><span class="material-symbols-rounded">directions_bus</span></span>
                <span class="info-line-text">
                    <strong>Busfahrplan</strong>
                    <p>Linie 260</p>
                </span>
                <span class="material-symbols-rounded info-go">open_in_new</span>
            </a>
            <a class="info-line is-link" href="https://www.kappl.com/de/winter" target="_blank" rel="noopener">
                <span class="info-line-ico"><span class="material-symbols-rounded">festival</span></span>
                <span class="info-line-text">
                    <strong>Tourismusbüro</strong>
                    <p>Events &amp; Tickets</p>
                </span>
                <span class="material-symbols-rounded info-go">open_in_new</span>
            </a>
        </div>

        <div class="info-kicker" data-i18n="info_rec_title">Unsere Empfehlungen</div>
        <div class="info-rows">
            <a class="info-line is-food" href="#" @click.prevent>
                <span class="info-line-ico"><span class="material-symbols-rounded">restaurant</span></span>
                <span class="info-line-text">
                    <strong>Pizzeria Da Luigi</strong>
                    <p>Beste Pizza &amp; Pasta</p>
                </span>
                <span class="material-symbols-rounded info-go">location_on</span>
            </a>
            <a class="info-line is-food" href="#" @click.prevent>
                <span class="info-line-ico"><span class="material-symbols-rounded">restaurant</span></span>
                <span class="info-line-text">
                    <strong>Gasthof Adler</strong>
                    <p>Gutbürgerliche Küche</p>
                </span>
                <span class="material-symbols-rounded info-go">location_on</span>
            </a>
            <a class="info-line is-food" href="#" @click.prevent>
                <span class="info-line-ico"><span class="material-symbols-rounded">bakery_dining</span></span>
                <span class="info-line-text">
                    <strong>Dorfbäckerei</strong>
                    <p>Frische Brötchen ab 6:00</p>
                </span>
                <span class="material-symbols-rounded info-go">location_on</span>
            </a>
        </div>
    </div>
</div>
