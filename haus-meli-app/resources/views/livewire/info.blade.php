<div class="container info-app"
     @haus-weather-hour.window="$wire.$refresh()"
     x-data="{
        ownerInfo: {{ !empty($infoOwner) ? 'true' : 'false' }},
        section: (function () {
            const owner = {{ !empty($infoOwner) ? 'true' : 'false' }};
            const allowed = owner ? ['wetter', 'out'] : ['now', 'wetter', 'out', 'haus'];
            try {
                const saved = localStorage.getItem('hausMeliInfoSection');
                if (allowed.indexOf(saved) !== -1) return saved;
            } catch (e) {}
            return allowed[0];
        })(),
        copied: '',
        setSection(name) {
            if (this.ownerInfo && name !== 'wetter' && name !== 'out') return;
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
     }"
     x-init="
        let ort = null;
        let zone = '';
        try {
            ort = localStorage.getItem('hausMeliWeatherOrt');
            zone = localStorage.getItem('hausMeliWeatherZone') || '';
            if (!ort) {
                const old = localStorage.getItem('hausMeliWeatherPlace');
                if (old === 'kappl') { ort = 'kappl'; zone = 'tal'; }
                else if (old === 'kappl-ski') { ort = 'kappl'; zone = 'berg'; }
                else if (old === 'ischgl-ski') { ort = 'ischgl'; zone = 'berg'; }
                else if (old === 'see-ski') { ort = 'see'; zone = 'berg'; }
                else if (old === 'lauterach') ort = 'lauterach';
            }
        } catch (e) {}
        try {
            if (ort && (ort !== $wire.weatherOrt || (zone && zone !== $wire.weatherZone))) $wire.setWeatherChoice(ort, zone);
        } catch (e) {}
     ">

    <div class="info-toolbar card">
        <div class="info-switch" role="tablist" aria-label="Infobereiche">
            @unless($infoOwner)
            <button type="button" role="tab" class="info-switch-btn" :class="{ 'is-on add-btn': section === 'now' }" :aria-selected="section === 'now'" @click="setSection('now')">Aktuell</button>
            @endunless
            <button type="button" role="tab" class="info-switch-btn" :class="{ 'is-on add-btn': section === 'wetter' }" :aria-selected="section === 'wetter'" @click="setSection('wetter')">Wetter</button>
            <button type="button" role="tab" class="info-switch-btn" :class="{ 'is-on add-btn': section === 'out' }" :aria-selected="section === 'out'" @click="setSection('out')">Umgebung</button>
            @unless($infoOwner)
            <button type="button" role="tab" class="info-switch-btn" :class="{ 'is-on add-btn': section === 'haus' }" :aria-selected="section === 'haus'" @click="setSection('haus')">Haus</button>
            @endunless
        </div>
    </div>

    @unless($infoOwner)
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
        <livewire:info-wifi />
    </div>
    @endunless

    <div class="info-panel" x-show="section === 'wetter'" x-cloak>
        <div class="card wx-pick-card">
            <div class="custom-dd" x-data="{ open: false }" @click.away="open = false" :class="{ 'open': open }">
                <div class="custom-dd-header" @click="open = !open" tabindex="0" role="button" aria-label="Ort">
                    <div class="custom-dd-label">{{ $weatherOrts[$weatherOrt]['label'] ?? '' }}</div>
                    <span class="custom-dd-arrow"></span>
                </div>
                <div class="custom-dd-list" x-show="open" style="display: none;" x-transition>
                    @foreach($weatherOrts as $ortId => $ortOption)
                        <div class="custom-dd-item {{ $weatherOrt === $ortId ? 'selected' : '' }}"
                             wire:click="setWeatherOrt('{{ $ortId }}')"
                             @click="open = false; try { localStorage.setItem('hausMeliWeatherOrt', '{{ $ortId }}') } catch (e) {}">
                            {{ $ortOption['label'] }}
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="info-switch wx-zone" role="group" aria-label="Lage">
                @foreach($weatherOrts[$weatherOrt]['zones'] as $zoneId => $zone)
                    <button type="button"
                            class="info-switch-btn {{ $weatherZone === $zoneId ? 'is-on add-btn' : '' }}"
                            wire:click="setWeatherZone('{{ $zoneId }}')"
                            @click="try { localStorage.setItem('hausMeliWeatherZone', '{{ $zoneId }}') } catch (e) {}">@if($zoneId === 'tal')Tal · @elseif($zoneId === 'berg')Berg · @endif{{ $zone['asl'] }} m</button>
                @endforeach
            </div>
        </div>
        @if(empty($forecast['ok']))
            <p class="info-weather-loading">{{ $forecast['error'] ?? 'Wetterdaten fehlen.' }}</p>
        @else
            <div class="info-block wx-board-wrap" wire:key="wx-board-{{ $weatherOrt }}-{{ $weatherZone }}" x-data="{
                day: 0,
                _wxLock: false,
                _wxBooted: false,
                bootHours() {
                    if (this._wxBooted || section !== 'wetter') return;
                    const strip = this.$refs.hours;
                    if (!strip || strip.clientWidth === 0) return;
                    const now = strip.querySelector('.is-now');
                    const target = now || strip.querySelector('[data-day=\'0\']');
                    if (!target) return;
                    this._wxLock = true;
                    strip.scrollLeft = target.offsetLeft;
                    this._wxBooted = true;
                    const self = this;
                    setTimeout(() => { self._wxLock = false; }, 80);
                },
                goDay(index) {
                    this.day = index;
                    const strip = this.$refs.hours;
                    if (!strip) return;
                    let target = null;
                    if (index === 0) target = strip.querySelector('.is-now');
                    if (!target) target = strip.querySelector('[data-day=\'' + index + '\']');
                    if (!target) return;
                    this._wxLock = true;
                    this._wxBooted = true;
                    strip.scrollTo({ left: target.offsetLeft, behavior: 'smooth' });
                    const self = this;
                    clearTimeout(this._wxUnlock);
                    this._wxUnlock = setTimeout(() => { self._wxLock = false; }, 700);
                },
                noteHourScroll() {
                    if (this._wxLock || this._wxFrame) return;
                    const self = this;
                    this._wxFrame = requestAnimationFrame(() => {
                        self._wxFrame = 0;
                        self.syncDayFromHours();
                    });
                },
                syncDayFromHours() {
                    if (this._wxLock) return;
                    const strip = this.$refs.hours;
                    if (!strip) return;
                    const edge = strip.scrollLeft + 16;
                    const nodes = strip.children;
                    let found = 0;
                    for (let i = 0; i < nodes.length; i++) {
                        if (nodes[i].offsetLeft <= edge) found = parseInt(nodes[i].getAttribute('data-day'), 10) || 0;
                        else break;
                    }
                    if (found === this.day) return;
                    this.day = found;
                    const board = this.$refs.days;
                    const col = board && board.children[found];
                    if (!board || !col) return;
                    const left = col.offsetLeft;
                    const right = left + col.offsetWidth;
                    if (left < board.scrollLeft - 4 || right > board.scrollLeft + board.clientWidth + 4) {
                        board.scrollTo({ left: Math.max(0, left - 8), behavior: 'smooth' });
                    }
                }
            }" x-init="$nextTick(() => requestAnimationFrame(() => bootHours())); $watch('section', () => { $nextTick(() => requestAnimationFrame(() => bootHours())); });">
                <div class="wx-board" x-ref="days" role="tablist" aria-label="Tage">
                    @foreach($forecast['days'] as $index => $day)
                        <button type="button"
                                class="wx-col"
                                role="tab"
                                :class="{ 'is-on': day === {{ $index }} }"
                                :aria-selected="day === {{ $index }}"
                                @click="goDay({{ $index }})">
                            <span class="wx-col-name">{{ $day['name'] }}</span>
                            <span class="wx-col-icon-wrap">
                                @if($index > 0 && !empty($forecast['days'][$index - 1]['night']))
                                    <span class="wx-night is-{{ $forecast['days'][$index - 1]['night']['icon'] }}" title="{{ $forecast['days'][$index - 1]['night']['label'] }}">
                                        <span class="material-symbols-rounded">{{ $forecast['days'][$index - 1]['night']['icon'] }}</span>
                                    </span>
                                @endif
                                <span class="material-symbols-rounded wx-col-icon is-{{ $day['morning']['icon'] }}" title="Vormittag: {{ $day['morning']['label'] }}">{{ $day['morning']['icon'] }}</span>
                                <span class="material-symbols-rounded wx-col-icon is-{{ $day['afternoon']['icon'] }}" title="Nachmittag: {{ $day['afternoon']['label'] }}">{{ $day['afternoon']['icon'] }}</span>
                            </span>
                            <span class="wx-col-max">{{ $day['max'] }}°</span>
                            <span class="wx-col-min">{{ $day['min'] }}°</span>
                            <span class="wx-pop-pct">{{ $day['pop'] > 0 ? $day['pop'].'%' : '' }}</span>
                            <span class="wx-rain" aria-hidden="true">
                                @foreach($day['rain_slots'] as $slot)
                                    <span class="wx-rain-slot" title="{{ $slot['label'] }}{{ $slot['mm'] > 0 ? ' · '.$slot['text'] : '' }}">
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
                <div class="wx-hours" x-ref="hours" @scroll="noteHourScroll()">
                    @foreach($forecast['days'] as $index => $day)
                        @foreach($day['hours'] as $hour)
                            <div class="wx-hour {{ !empty($hour['is_now']) ? 'is-now' : '' }}" data-day="{{ $index }}">
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
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @unless($infoOwner)
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
    @endunless

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
