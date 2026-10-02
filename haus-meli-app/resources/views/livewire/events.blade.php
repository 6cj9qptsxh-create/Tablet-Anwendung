<div class="cal-app"
     data-cal-title="{{ $headerLabel }}"
     x-data="{
        showModal: !!(window.calModal && window.calModal.open),
        modalBusy: !!(window.calModal && window.calModal.busy),
        modalIntent: (window.calModal && window.calModal.intent) || null,
        showShifts: window.calModal ? window.calModal.showShifts !== false : true,
        modalOpening: false,
        modalClosing: false,
        originX: (window.calModalOrigin && window.calModalOrigin.x) || (window.innerWidth / 2),
        originY: (window.calModalOrigin && window.calModalOrigin.y) || (window.innerHeight / 2),
        animSkip: 0,
        monthPreview: null,
        filterOpen: Boolean($wire.filterOpen),
        who: (localStorage.getItem('cal-device') === 'L' || localStorage.getItem('cal-device') === 'M') ? localStorage.getItem('cal-device') : '',
        pickWho(code) {
            if (code !== 'L' && code !== 'M') return;
            localStorage.setItem('cal-device', code);
            this.who = code;
            this.$wire.setActor(code);
        },
        init() {
            if (this.who === 'L' || this.who === 'M') this.$wire.setActor(this.who);
            this.monthPreview = window._calTitle || this.$el.getAttribute('data-cal-title') || null;
            if (!this.showModal || !window.calModalAnimStart) return;
            const left = 700 - (Date.now() - window.calModalAnimStart);
            this.animSkip = Math.max(0, 700 - Math.max(0, left));
            this.modalOpening = left > 0;
            if (left > 0) {
                this._openT = setTimeout(() => { this.modalOpening = false; }, left);
            }
        },
        closeFilters() {
            if (!this.filterOpen) return;
            this.filterOpen = false;
            $wire.closeFilters();
            window.calUiBlockUntil = Date.now() + 500;
        },
        openFilters() {
            this.filterOpen = true;
            $wire.set('filterOpen', true);
        },
        modalHeading() {
            if (this.modalIntent === 'edit') return 'Termin bearbeiten';
            if (this.modalIntent === 'view') return 'Details';
            return 'Neuer Termin';
        },
        persistModal() {
            window.calModal = { open: this.showModal, busy: this.modalBusy, intent: this.modalIntent, showShifts: this.showShifts };
        },
        onOpenModal(e) {
            const d = (e && e.detail) || {};
            this.showModal = true;
            this.modalClosing = false;
            this.modalBusy = !d.ready;
            if (d.intent) this.modalIntent = d.intent;
            if (d.showShifts !== undefined) this.showShifts = d.showShifts !== false;
            const o = window.calModalOrigin || window.calLastPointer;
            if (o) {
                this.originX = o.x;
                this.originY = o.y;
            }
            if (!window.calModalAnimStart) {
                window.calModalAnimStart = Date.now();
                this.animSkip = 0;
                this.modalOpening = true;
                clearTimeout(this._openT);
                this._openT = setTimeout(() => { this.modalOpening = false; }, 700);
            } else {
                const elapsed = Date.now() - window.calModalAnimStart;
                this.animSkip = Math.min(700, Math.max(0, elapsed));
                this.modalOpening = elapsed < 700;
            }
            this.persistModal();
            document.body.classList.remove('cal-no-select');
        },
        closeModal() {
            if (!this.showModal || this.modalClosing) return;
            this.modalClosing = true;
            this.modalBusy = false;
            this.modalOpening = false;
            window.calModalAnimStart = null;
            window.calUiBlockUntil = Date.now() + 900;
            window.calModal = { open: false, busy: false, intent: null, showShifts: true };
            document.body.classList.remove('cal-no-select');
            clearTimeout(this._closeT);
            this._closeT = setTimeout(() => {
                this.showModal = false;
                this.modalClosing = false;
                this.modalIntent = null;
                this.persistModal();
            }, 700);
        },
        async saveAndClose() {
            const intent = this.modalIntent;
            this.closeModal();
            await new Promise(r => setTimeout(r, 700));
            try {
                await this.$wire.savePrivateEvent();
            } catch (_) {
                this.showModal = true;
                this.modalClosing = false;
                this.modalBusy = false;
                this.modalIntent = intent;
                this.persistModal();
            }
        },
        async deleteAndClose() {
            this.closeModal();
            await new Promise(r => setTimeout(r, 700));
            try {
                await this.$wire.deletePrivateEvent();
            } catch (_) {
                this.showModal = true;
                this.modalClosing = false;
                this.modalBusy = false;
                this.modalIntent = 'edit';
                this.persistModal();
            }
        }
     }"
     x-on:open-modal.window="onOpenModal($event)"
     x-on:close-modal.window="closeModal()"
     x-on:cal-month-title.window="monthPreview = $event.detail.title"
     x-on:pointerdown.window="if (filterOpen && !$event.target.closest('.cal-filter-menu, .cal-filter-toggle')) closeFilters()"
     x-on:click.window="if (filterOpen && !$event.target.closest('.cal-filter-menu, .cal-filter-toggle')) closeFilters()">

        <div class="cal-toolbar" :class="{ 'is-filter-open': filterOpen }"
             @pointerdown="if (filterOpen && !$event.target.closest('.cal-filter-menu, .cal-filter-toggle')) closeFilters()">
            <div class="cal-toolbar-top">
                @if($viewMode === 'agenda')
                    <h2 class="cal-heading">Agenda</h2>
                @else
                    <h2 class="cal-heading" wire:ignore>
                        <span id="cal-month-title">{{ $headerLabel }}</span>
                    </h2>
                @endif
                <div class="cal-toolbar-icons">
                    <div class="cal-filter-wrap">
                        <button type="button"
                                class="cal-icon-btn cal-filter-toggle {{ ($isFamily ? count($activeTypes) < 3 : ($showFavsOnly || count($inactiveLocations))) ? 'has-dot' : '' }}"
                                :class="{ 'is-open': filterOpen }"
                                @pointerdown.stop
                                @click="filterOpen ? closeFilters() : openFilters()"
                                title="Filter">
                            <span class="material-symbols-rounded">tune</span>
                        </button>
                        <div class="cal-filter-menu {{ $isFamily ? 'is-family' : '' }}" x-show="filterOpen" x-cloak
                             x-on:pointerdown.stop
                             x-on:click.stop>
                            @if($isFamily)
                                <button type="button" wire:click="toggleType('schicht')" class="filter-chk-btn {{ in_array('schicht', $activeTypes) ? 'checked' : '' }}">
                                    <div class="chk-box"></div>
                                    <span>Schichten</span>
                                </button>
                                <button type="button" wire:click="toggleType('privat')" class="filter-chk-btn {{ in_array('privat', $activeTypes) ? 'checked' : '' }}">
                                    <div class="chk-box"></div>
                                    <span>Termine</span>
                                </button>
                                <button type="button" wire:click="toggleType('geburtstag')" class="filter-chk-btn {{ in_array('geburtstag', $activeTypes) ? 'checked' : '' }}">
                                    <div class="chk-box"></div>
                                    <span>Geburtstag</span>
                                </button>
                                <div class="cal-filter-sep">Schichtvorlagen</div>
                                @forelse($shiftTemplates as $tpl)
                                    <div class="cal-tpl-row" style="--tpl-color: {{ $tpl->color ?: '#2563eb' }};">
                                        <strong class="cal-tpl-name">{{ $tpl->name }}</strong>
                                        <small class="cal-tpl-time">{{ $tpl->start_label }}–{{ $tpl->end_label }}</small>
                                        <button type="button" class="cal-tpl-ico {{ (int) $tplEditId === (int) $tpl->id ? 'is-on' : '' }}"
                                                wire:click="editShiftTemplate({{ $tpl->id }})" title="Bearbeiten">
                                            <span class="material-symbols-rounded">edit</span>
                                        </button>
                                        <button type="button" class="cal-tpl-ico"
                                                wire:click="deleteShiftTemplate({{ $tpl->id }})"
                                                wire:confirm="Vorlage löschen?" title="Löschen">
                                            <span class="material-symbols-rounded">delete</span>
                                        </button>
                                    </div>
                                @empty
                                    <div class="cal-tpl-empty">Noch keine Vorlagen</div>
                                @endforelse
                                <div class="cal-tpl-form">
                                    <input type="text" wire:model="tplName" class="form-input" placeholder="{{ $tplEditId ? 'Name' : 'Neue Vorlage' }}">
                                    <div class="cal-tpl-times">
                                        <input type="time" wire:model="tplStartTime" class="form-input">
                                        <input type="time" wire:model="tplEndTime" class="form-input">
                                        <input type="color" wire:model.live="tplColor" class="color-swatch-input" title="Farbe">
                                    </div>
                                    @error('tplEndTime') <div class="cal-tpl-error">{{ $message }}</div> @enderror
                                    <div class="cal-tpl-actions">
                                        @if($tplEditId)
                                            <button type="button" wire:click="resetTemplateForm" class="cal-tpl-cancel">Abbrechen</button>
                                        @endif
                                        <button type="button" wire:click="saveShiftTemplate" class="add-btn">{{ $tplEditId ? 'Speichern' : 'Anlegen' }}</button>
                                    </div>
                                </div>
                            @else
                                <button type="button" wire:click="toggleFavsOnly" class="filter-chk-btn {{ $showFavsOnly ? 'checked' : '' }}">
                                    <div class="chk-box"></div>
                                    <span>❤️ Mein Plan</span>
                                </button>
                                <button type="button" wire:click="toggleType('operating')" class="filter-chk-btn {{ in_array('operating', $activeTypes) ? 'checked' : '' }}">
                                    <div class="chk-box"></div>
                                    <span>🚠 Betrieb</span>
                                </button>
                                <button type="button" wire:click="toggleType('topevent')" class="filter-chk-btn {{ in_array('topevent', $activeTypes) ? 'checked' : '' }}">
                                    <div class="chk-box"></div>
                                    <span>⭐ Top-Events</span>
                                </button>
                                <button type="button" wire:click="toggleType('event')" class="filter-chk-btn {{ in_array('event', $activeTypes) ? 'checked' : '' }}">
                                    <div class="chk-box"></div>
                                    <span>📅 Events</span>
                                </button>
                                <button type="button" wire:click="toggleType('info')" class="filter-chk-btn {{ in_array('info', $activeTypes) ? 'checked' : '' }}">
                                    <div class="chk-box"></div>
                                    <span>ℹ️ Infos</span>
                                </button>
                                @if(count($availableLocations) > 0)
                                    <div class="cal-filter-sep">Orte</div>
                                    @foreach($availableLocations as $loc)
                                        <button type="button" wire:click="toggleLocation('{{ $loc }}')" class="filter-chk-btn {{ !in_array($loc, $inactiveLocations) ? 'checked' : '' }}">
                                            <div class="chk-box"></div>
                                            <span>📍 {{ $loc }}</span>
                                        </button>
                                    @endforeach
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="cal-toolbar-row">
                <div class="cal-picker cal-today-wrap">
                    <button type="button"
                            class="toggle-btn cal-chip"
                            @click="window.calWeekPos = null; window.calWeekJumpDate = null; @if($viewMode === 'week') window.calWeekForceToday = Date.now(); @endif"
                            wire:click="goToToday">Heute</button>
                </div>
                <div class="cal-picker cal-view-switch" role="tablist" aria-label="Kalenderansicht">
                    <button type="button"
                            role="tab"
                            wire:click="setViewMode('week')"
                            @click="window.calWeekPos = null; window.calWeekJumpDate = null"
                            class="toggle-btn cal-chip {{ $viewMode === 'week' ? 'active' : '' }}">3-Tage</button>
                    <button type="button" role="tab" wire:click="setViewMode('month')" class="toggle-btn cal-chip {{ $viewMode === 'month' ? 'active' : '' }}">Monat</button>
                    <button type="button" role="tab" wire:click="setViewMode('agenda')" class="toggle-btn cal-chip {{ $viewMode === 'agenda' ? 'active' : '' }}">Agenda</button>
                </div>
            </div>
        </div>

        @if($isFamily)
            <div class="cal-who-once" x-show="!who" x-cloak>
                <div class="cal-who-once-card" @click.stop>
                    <p>Wer nutzt dieses Gerät?</p>
                    <div class="cal-who-once-row">
                        <button type="button" class="cal-who is-m" @click="pickWho('M')">M</button>
                        <button type="button" class="cal-who is-l" @click="pickWho('L')">L</button>
                    </div>
                </div>
            </div>
        @endif

        <div class="cal-filter-scrim"
             x-show="filterOpen"
             x-cloak
             x-on:pointerdown.prevent.stop="closeFilters()"
             x-on:click.prevent.stop="closeFilters()"></div>

        <div class="card outlook-calendar cal-view-{{ $viewMode }}">

            @if($viewMode === 'agenda')
                <div class="agenda-split" wire:poll.60s>
                    @if($isFamily)
                        <div class="agenda-family">
                            @foreach ([
                                'notes' => ['title' => 'Termine', 'entries' => $agendaNotes, 'empty' => 'Keine Termine'],
                                'birthdays' => ['title' => 'Geburtstage', 'entries' => $agendaBirthdays, 'empty' => 'Keine Geburtstage'],
                                'shifts' => ['title' => 'Schichten', 'entries' => $agendaShifts, 'empty' => 'Keine Schichten'],
                            ] as $colKey => $col)
                                <section class="agenda-pane agenda-pane--{{ $colKey }}" data-col="{{ $colKey }}">
                                    <div class="agenda-col-title">{{ $col['title'] }}</div>
                                    <div class="agenda-col"
                                         x-data
                                         @scroll.throttle.200ms="
                                            const el = $event.target;
                                            if (el.scrollTop + el.clientHeight > el.scrollHeight - 200) $wire.extendFuture(21);
                                         ">
                                        @forelse($col['entries'] as $entry)
                                            @php $ev = $entry['event']; @endphp
                                            <button type="button" class="agenda-item {{ !empty($ev->is_outline) ? 'is-outline' : '' }} {{ !empty($entry['is_today']) ? 'is-today' : '' }}"
                                                    wire:key="ag-{{ $entry['date'] }}-{{ $colKey }}-{{ $ev->id }}"
                                                    x-data="calEventOpen('{{ $ev->id }}', '{{ $entry['date'] }}', {{ !empty($ev->is_private) ? 'true' : 'false' }})"
                                                    @pointerdown="onDown($event)"
                                                    @click="onClick()"
                                                    style="--event-color: {{ $ev->color ?? 'var(--accent)' }}; border-left-color: {{ $ev->color ?? 'var(--accent)' }};">
                                                @include('livewire.partials.cal-who', ['ev' => $ev, 'stack' => true])
                                                <span class="agenda-date">{{ $entry['date_short'] }}</span>
                                                @if($colKey !== 'birthdays')
                                                    <span class="agenda-time">{{ $entry['time_label'] }}</span>
                                                @endif
                                                <span class="agenda-title">
                                                    <span class="agenda-title-text">@if(!empty($ev->title_icon))<span class="material-symbols-rounded cal-ev-ico">{{ $ev->title_icon }}</span>@endif{{ $ev->display_title ?? $ev->title }}</span>
                                                    @if($colKey === 'birthdays' && !empty($ev->age))
                                                        <span class="agenda-age">{{ $ev->age }}J</span>
                                                    @endif
                                                </span>
                                                @if($ev->location)
                                                    <span class="agenda-loc">📍 {{ $ev->location }}</span>
                                                @endif
                                                @if(!empty($ev->url))
                                                    <span class="agenda-link" role="link" @click.stop="window.open(@js($ev->url), '_blank', 'noopener')">
                                                        <span class="material-symbols-rounded">link</span> Link
                                                    </span>
                                                @endif
                                            </button>
                                        @empty
                                            <div class="agenda-empty">{{ $col['empty'] }}</div>
                                        @endforelse
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    @else
                <div class="agenda-list"
                     x-data
                     @scroll.throttle.200ms="
                        const el = $event.target;
                        if (el.scrollTop + el.clientHeight > el.scrollHeight - 200) $wire.extendFuture(21);
                     ">
                        @forelse($agendaItems as $group)
                            <div class="agenda-day-group {{ $group['is_today'] ? 'is-today' : '' }}" wire:key="ag-{{ $group['date'] }}" data-date="{{ $group['date'] }}">
                                <div class="agenda-day-label">{{ $group['label'] }}</div>
                                @foreach($group['entries'] as $entry)
                                    @php $ev = $entry['event']; @endphp
                                    <button type="button" class="agenda-item {{ !empty($ev->is_outline) ? 'is-outline' : '' }}"
                                            wire:key="ag-{{ $entry['date'] }}-{{ $ev->id }}"
                                            x-data="calEventOpen('{{ $ev->id }}', '{{ $entry['date'] }}', {{ !empty($ev->is_private) ? 'true' : 'false' }})"
                                            @pointerdown="onDown($event)"
                                            @click="onClick()"
                                            style="--event-color: {{ $ev->color ?? 'var(--accent)' }}; border-left-color: {{ $ev->color ?? 'var(--accent)' }};">
                                        @include('livewire.partials.cal-who', ['ev' => $ev, 'stack' => true])
                                        <span class="agenda-time">{{ $entry['time_label'] }}</span>
                                        <span class="agenda-title">
                                            @if(!empty($ev->title_icon))<span class="material-symbols-rounded cal-ev-ico">{{ $ev->title_icon }}</span>@endif{{ $ev->title }}
                                            @if(!$ev->is_private && !empty($ev->is_favorite)) ❤️ @endif
                                        </span>
                                        @if($ev->location)
                                            <span class="agenda-loc">📍 {{ $ev->location }}</span>
                                        @endif
                                        @if(!empty($ev->url))
                                            <span class="agenda-link" role="link" @click.stop="window.open(@js($ev->url), '_blank', 'noopener')">
                                                <span class="material-symbols-rounded">link</span> Link
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @empty
                            <div class="agenda-empty">Keine Termine in diesem Zeitraum.</div>
                        @endforelse
                </div>
                    @endif
                </div>

            @elseif($viewMode === 'month')
                <div class="events-month-shell"
                     data-labels='@json(collect($monthBand)->pluck("label")->values())'
                     x-data="calMonthBand()"
                     x-init="init()">
                    <div class="events-month-weekdays">
                        @foreach(['Mo','Di','Mi','Do','Fr','Sa','So'] as $wd)
                            <div class="events-month-weekday">{{ $wd }}</div>
                        @endforeach
                    </div>
                    <div class="events-month-viewport"
                         :class="{ 'is-selecting': selecting }"
                         @pointerdown="bandDown($event)"
                         @pointermove.window="bandMove($event)"
                         @pointerup.window="bandUp($event)"
                         @pointercancel.window="bandUp($event)"
                         @touchmove.window="bandTouchMove($event)">
                        <div class="events-month-band"
                             :style="bandStyle()"
                             :class="{ 'is-dragging': dragging, 'is-settling': settling }">
                            @foreach($monthBand as $panel)
                                <div class="events-month-panel" wire:key="mp-{{ $panel['key'] }}" data-offset="{{ $panel['offset'] }}" data-label="{{ $panel['label'] }}">
                                    @foreach($panel['weeks'] as $week)
                                        <div class="events-month-week" wire:key="mw-{{ $panel['key'] }}-{{ $week[0]['date'] ?? $loop->index }}">
                                            @foreach($week as $day)
                                                <div class="events-month-day {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['in_month'] ? '' : 'is-outside' }}"
                                                     wire:key="md-{{ $day['date'] }}"
                                                     data-date="{{ $day['date'] }}"
                                                     :class="{ 'is-range': isPicked('{{ $day['date'] }}') }">
                                                    <span class="events-month-num" title="3-Tage-Ansicht">{{ $day['day_num'] }}</span>
                                                    <div class="events-month-pills">
                                                        @foreach(array_slice($day['events'], 0, 3) as $ev)
                                                            <span class="events-month-pill {{ !empty($ev->is_outline) ? 'is-outline' : '' }}"
                                                                  wire:key="mpill-{{ $day['date'] }}-{{ $ev->id }}"
                                                                  style="--pill-color: {{ $ev->color }};"
                                                                  title="{{ $ev->title }}"
                                                                  x-data="calEventOpen('{{ $ev->id }}', '{{ $day['date'] }}', {{ !empty($ev->is_private) ? 'true' : 'false' }})"
                                                                  @pointerdown="onDown($event)"
                                                                  @click.stop="onClick()">
                                                                @if(!empty($ev->title_icon))<span class="material-symbols-rounded cal-ev-ico">{{ $ev->title_icon }}</span>@endif<span class="events-month-pill-name">{{ $ev->short }}</span>
                                                                @include('livewire.partials.cal-who', ['ev' => $ev])
                                                            </span>
                                                        @endforeach
                                                        @if(count($day['events']) > 3)
                                                            <span class="events-month-more">+{{ count($day['events']) - 3 }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            @else
            {{-- Outlook: H-Scroller + V-Scroller getrennt, Zeitspalte per Sync --}}
            <div class="cal-week-shell"
                 wire:ignore
                 wire:key="week-{{ $weekEpoch }}"
                 x-data="calWeekScroll()"
                 x-init="init()"
                 :style="weekShellStyle()"
                 @cal-scroll-today.window="scrollToToday(true)"
                 @cal-scroll-date.window="if ($event.detail && $event.detail.date) scrollToDate($event.detail.date, false)">

                <div class="cal-week-top">
                    <div class="time-col-header">
                        <span style="writing-mode: vertical-rl; transform: rotate(180deg); font-size: 0.75rem; color: var(--muted); letter-spacing: 2px; text-transform: uppercase;">Ganztägig</span>
                    </div>
                    <div class="cal-week-h-head" x-ref="hHead">
                        <div class="cal-week-head-track" :style="trackStyle()">
                        @foreach($days as $day)
                            <div class="day-col-header {{ $day['date'] === $todayDate ? 'is-today' : '' }}" data-date="{{ $day['date'] }}">
                                <div class="day-title">
                                    <span class="date-badge">{{ $day['day_name'] }}</span>
                                </div>
                                <div class="all-day-area"
                                     role="button"
                                     title="Ganztägigen Termin erstellen"
                                     @click="if ((window.calUiBlockUntil && Date.now() < window.calUiBlockUntil) || $event.target.closest('.event-card')) return; window.calOpenCreateNow($wire, '{{ $day['date'] }}')">
                                    @foreach($day['all_day_events'] as $ev)
                                        <div class="event-card all-day {{ !empty($ev->is_outline) ? 'is-outline' : '' }}"
                                             data-event-id="{{ $ev->id }}"
                                             x-data="calEventOpen('{{ $ev->id }}', '{{ $day['date'] }}', {{ !empty($ev->is_private) ? 'true' : 'false' }})"
                                             @pointerdown="onDown($event)"
                                             @click.stop="onClick()"
                                            style="--event-color: {{ $ev->color ?? 'var(--accent)' }}; background-color: {{ $ev->color ? 'color-mix(in srgb, '.$ev->color.', transparent 85%)' : 'var(--surface)' }}; border-left-color: {{ $ev->color ?? 'var(--accent)' }}; cursor: pointer; border-left-width: 3px; border-left-style: solid;">
                                            @include('livewire.partials.cal-who', ['ev' => $ev, 'stack' => true])
                                            <span class="title" style="font-weight: 600; color: var(--text);">@if(!empty($ev->title_icon))<span class="material-symbols-rounded cal-ev-ico">{{ $ev->title_icon }}</span>@endif{{ $ev->display_title ?? $ev->title }}</span>
                                            @if(!$ev->is_private && !empty($ev->is_favorite))
                                                <span style="font-size: 0.85rem;">❤️</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        </div>
                    </div>
                </div>

                <div class="cal-week-bottom" x-ref="vBody"
                     @scroll.passive="queueOverflowHints()"
                     x-init="if (window.calWeekPos) $el.scrollTop = window.calWeekPos.vTop || 0">
                    <div class="cal-week-times">
                        <div class="time-col-body">
                            @for ($i = 6; $i <= 23; $i++)
                                <div class="time-label" style="top: {{ (($i - 6) * 60) + 15 }}px;">{{ sprintf('%02d:00', $i) }}</div>
                            @endfor
                        </div>
                    </div>

                    <div class="cal-week-h-body" id="cal-scroll" x-ref="hBody">
                            <div class="cal-week-track" :style="trackStyle()">
                                <div class="calendar-body-row">
                                    @foreach($days as $day)
                                        <div class="day-col-body {{ $day['date'] === $todayDate ? 'is-today' : '' }}"
                                             data-date="{{ $day['date'] }}"
                                             x-data="calSlotCreate('{{ $day['date'] }}')"
                                             :class="{ 'is-selecting': active }"
                                             @pointerdown="onDown($event)">

                                            <template x-if="active">
                                                <div class="cal-create-ghost is-ready" :style="ghostStyle()"></div>
                                            </template>

                                            @if($day['date'] === $todayDate)
                                                <div class="current-time-indicator"
                                                    wire:ignore
                                                    x-data="{
                                                        topPx: 0,
                                                        updateLine() {
                                                            let d = new Date();
                                                            let h = d.getHours();
                                                            if(h >= 6 && h < 24) {
                                                                this.topPx = ((h - 6) * 60) + d.getMinutes() + 15;
                                                                this.$el.style.display = 'block';
                                                            } else {
                                                                this.$el.style.display = 'none';
                                                            }
                                                        }
                                                    }"
                                                    x-init="updateLine(); setInterval(() => updateLine(), 60000)"
                                                    :style="`top: ${topPx}px;`">
                                                </div>
                                            @endif

                                            @foreach($day['timed_events'] as $ev)
                                                @php
                                                    $cardStyle = '--event-color: '.($ev->color ?? 'var(--accent)').'; left: calc('.($ev->left_pct).'% + 2px); width: calc('.($ev->width_pct).'% - 4px); background-color: '.($ev->color ? 'color-mix(in srgb, '.$ev->color.', transparent 80%)' : 'var(--accent-soft)').'; border-left-color: '.($ev->color ?? 'var(--accent)').'; cursor: pointer;';
                                                @endphp
                                                @if(!empty($ev->is_private))
                                                <div class="event-card timed is-private {{ !empty($ev->is_outline) ? 'is-outline' : '' }}"
                                                     data-event-id="{{ $ev->id }}"
                                                     x-data="calEventResize({{ (int) $ev->id }}, {{ (int) $ev->top_px }}, {{ (int) $ev->height_px }})"
                                                     :class="{ 'is-resizing': resizing }"
                                                     :style="boxStyle() + {{ json_encode($cardStyle) }}"
                                                     @pointerdown="onCardPointerDown($event)"
                                                     @click="onCardClick()">
                                                    @include('livewire.partials.cal-who', ['ev' => $ev, 'stack' => true])
                                                    <div class="event-resize-handle top" title="Start verschieben"
                                                         @pointerdown.stop="onHandlePointerDown('start', $event)"></div>
                                                    <div class="event-resize-handle bottom" title="Ende verschieben"
                                                         @pointerdown.stop="onHandlePointerDown('end', $event)"></div>
                                                    <div style="display: flex; flex-direction: column; height: 100%; pointer-events: none;">
                                                        <strong style="padding-right: 15px; display: flex; justify-content: space-between; gap: 8px;">@if(!empty($ev->title_icon))<span class="material-symbols-rounded cal-ev-ico">{{ $ev->title_icon }}</span>@endif<span>{{ $ev->display_title ?? $ev->title }}</span></strong>
                                                        @if($ev->location)
                                                            <div class="location">📍 {{ $ev->location }}</div>
                                                        @endif
                                                        @if(!empty($ev->url))
                                                            <div class="cal-card-link" @click.stop="window.open(@js($ev->url), '_blank', 'noopener')"><span class="material-symbols-rounded">open_in_new</span>Link</div>
                                                        @endif
                                                    </div>
                                                </div>
                                                @else
                                                <div class="event-card timed"
                                                     data-event-id="{{ $ev->id }}"
                                                     x-data="calEventOpen('{{ $ev->id }}', '{{ $day['date'] }}', false)"
                                                     @pointerdown="onDown($event)"
                                                     @click="onClick()"
                                                     style="top: {{ $ev->top_px }}px; height: {{ $ev->height_px }}px; {{ $cardStyle }}">
                                                    @if(!empty($ev->is_favorite))
                                                        <div style="position: absolute; top: 4px; right: 4px; font-size: 0.7rem; z-index: 5;">❤️</div>
                                                    @endif
                                                    <div style="display: flex; flex-direction: column; height: 100%;">
                                                        <strong style="padding-right: 15px; display: flex; justify-content: space-between; gap: 8px;">@if(!empty($ev->title_icon))<span class="material-symbols-rounded cal-ev-ico">{{ $ev->title_icon }}</span>@endif<span>{{ $ev->display_title ?? $ev->title }}</span></strong>
                                                        @if($ev->location)
                                                            <div class="location">📍 {{ $ev->location }}</div>
                                                        @endif
                                                        @if(!empty($ev->url))
                                                            <div class="cal-card-link" @click.stop="window.open(@js($ev->url), '_blank', 'noopener')"><span class="material-symbols-rounded">open_in_new</span>Link</div>
                                                        @endif
                                                    </div>
                                                </div>
                                                @endif
                                            @endforeach
                                            <div class="cal-day-more cal-day-more-up" hidden aria-hidden="true">
                                                <span class="material-symbols-rounded">keyboard_arrow_up</span>
                                            </div>
                                            <div class="cal-day-more-fill" aria-hidden="true"></div>
                                            <div class="cal-day-more cal-day-more-down" hidden aria-hidden="true">
                                                <span class="material-symbols-rounded">keyboard_arrow_down</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

    <script>
        window.calSetMonthTitle = function (label) {
            if (label == null || label === '') return;
            window._calTitle = label;
            const span = document.getElementById('cal-month-title') || document.querySelector('.cal-heading span');
            if (span) span.textContent = label;
        };

        /** Monatsband: %-Transform; Titel folgt sofort beim Wischen. */
        window.calMonthBand = function () {
            return {
                bandX: 0,
                bandW: 0,
                dragging: false,
                settling: false,
                moved: false,
                lock: false,
                pending: 0,
                _flushing: false,
                selecting: false,
                selectedDates: [],
                rangeStart: null,
                startX: 0,
                startY: 0,
                axis: null,
                pointerId: null,
                _lpTimer: null,
                _tapOnNum: false,
                _tapOnPill: false,

                init() {
                    this.$nextTick(() => {
                        this.measure();
                        if (!this.lock && !this._flushing) this.bandX = 0;
                    });
                    this._onResize = () => {
                        if (!this.dragging) this.measure();
                    };
                    this._onRemeasure = () => {
                        if (!this.dragging) this.measure();
                    };
                    window.addEventListener('resize', this._onResize, { passive: true });
                    window.addEventListener('cal-remeasure', this._onRemeasure);
                    const vp = this.$el.querySelector('.events-month-viewport');
                    if (vp && window.ResizeObserver) {
                        this._ro = new ResizeObserver(() => {
                            if (!this.dragging) this.measure();
                        });
                        this._ro.observe(vp);
                    }
                    this._onMorph = ({ el }) => {
                        if (!this._flushing || !this.$el) return;
                        if (el === this.$el || (this.$el.contains && this.$el.contains(el))) {
                            this.snapBandHome();
                        }
                    };
                    if (window.Livewire && typeof Livewire.hook === 'function') {
                        Livewire.hook('morph.updated', this._onMorph);
                    }
                },

                destroy() {
                    this.clearLp();
                    if (this._onResize) window.removeEventListener('resize', this._onResize);
                    if (this._onRemeasure) window.removeEventListener('cal-remeasure', this._onRemeasure);
                    if (this._ro) this._ro.disconnect();
                },

                labelAt(idx) {
                    const panels = this.$el.querySelectorAll('.events-month-panel');
                    const fromPanel = panels[idx] && panels[idx].getAttribute('data-label');
                    if (fromPanel) return fromPanel;
                    try {
                        const raw = this.$el.getAttribute('data-labels');
                        const list = raw ? JSON.parse(raw) : [];
                        return (Array.isArray(list) && list[idx]) ? list[idx] : '';
                    } catch (_) {
                        return '';
                    }
                },

                measure() {
                    const vp = this.$el.querySelector('.events-month-viewport');
                    const h = vp ? vp.clientHeight : 0;
                    if (h >= 80) this.bandW = h;
                    if (!this.dragging && !this.settling && !this.moved) this.previewTitle(1);
                },

                bandGap() {
                    const vp = this.$el.querySelector('.events-month-viewport');
                    if (!vp) return 16;
                    const g = parseFloat(getComputedStyle(vp).getPropertyValue('--cal-month-gap'));
                    return Number.isFinite(g) ? g : 16;
                },

                bandStep() {
                    return (this.bandW > 0 ? this.bandW : 1) + this.bandGap();
                },

                bandStyle() {
                    const dx = this.bandX;
                    if (!(this.bandW > 0) && !this.dragging && !this.settling && !this.moved) {
                        return '';
                    }
                    const step = this.bandStep();
                    if (this.dragging || this.settling || this.moved) {
                        if (dx < -8) this.previewTitle(2);
                        else if (dx > 8) this.previewTitle(0);
                        else this.previewTitle(1);
                    }
                    return 'transform: translate3d(0, ' + (-step + dx) + 'px, 0);';
                },

                previewTitle(idx) {
                    const label = this.labelAt(idx);
                    if (label) window.calSetMonthTitle(label);
                },

                snapBandHome() {
                    this.settling = false;
                    this.bandX = 0;
                    const band = this.$el && this.$el.querySelector('.events-month-band');
                    if (!band) return;
                    band.classList.remove('is-settling');
                    band.style.transition = 'none';
                    band.style.transform = 'translate3d(0, ' + (-this.bandStep()) + 'px, 0)';
                    requestAnimationFrame(() => {
                        if (this.dragging || !band) return;
                        band.style.transition = '';
                        band.style.transform = '';
                    });
                },

                clearPreview() {
                    window.calSetMonthTitle(this.labelAt(1) || window._calTitle || '');
                },

                isPicked(date) {
                    return this.selectedDates.indexOf(date) !== -1;
                },

                clearLp() {
                    if (this._lpTimer) {
                        clearTimeout(this._lpTimer);
                        this._lpTimer = null;
                    }
                },

                dayAtPoint(x, y) {
                    const el = document.elementFromPoint(x, y);
                    const day = el && el.closest ? el.closest('.events-month-day') : null;
                    return day && day.dataset ? day.dataset.date : null;
                },

                datesBetween(a, b) {
                    if (!a || !b) return a ? [a] : [];
                    let start = a < b ? a : b;
                    const end = a < b ? b : a;
                    const out = [];
                    const cur = new Date(start + 'T00:00:00');
                    const last = new Date(end + 'T00:00:00');
                    while (cur <= last && out.length < 62) {
                        const y = cur.getFullYear();
                        const m = String(cur.getMonth() + 1).padStart(2, '0');
                        const d = String(cur.getDate()).padStart(2, '0');
                        out.push(y + '-' + m + '-' + d);
                        cur.setDate(cur.getDate() + 1);
                    }
                    return out;
                },

                finishSelect() {
                    const dates = this.selectedDates.slice().sort();
                    this.selecting = false;
                    this.selectedDates = [];
                    this.rangeStart = null;
                    this.dragging = false;
                    this.moved = false;
                    this.axis = null;
                    this.pointerId = null;
                    try { document.body.classList.remove('cal-no-select'); } catch (_) {}
                    if (!dates.length) return;
                    if (window.calModal && window.calModal.open) return;
                    window.calOpenCreateNow(this.$wire, dates[0], null, null, dates);
                },

                bandDown(e) {
                    if (this.selecting) return;
                    if (e.button != null && e.button !== 0) return;
                    if (window.calUiBlockUntil && Date.now() < window.calUiBlockUntil) return;
                    if (this.lock || this._flushing) {
                        this._lockPointer = e.pointerId;
                        this._lockStartY = e.clientY;
                        try { e.currentTarget && e.currentTarget.setPointerCapture(e.pointerId); } catch (_) {}
                        return;
                    }
                    if (this.dragging) return;
                    this.measure();
                    this.dragging = true;
                    this.settling = false;
                    this.moved = false;
                    this.axis = null;
                    this.selecting = false;
                    this.selectedDates = [];
                    this.pointerId = e.pointerId;
                    this.startX = e.clientX;
                    this.startY = e.clientY;
                    this._capEl = e.currentTarget;

                    const onEvent = !!e.target.closest('.events-month-pill');
                    const startDay = e.target.closest('.events-month-day');
                    const startDate = startDay && startDay.dataset ? startDay.dataset.date : null;
                    this.rangeStart = startDate;
                    this._tapOnPill = onEvent;
                    this._tapOnNum = !!e.target.closest('.events-month-num');
                    this.clearLp();
                    if (!startDate || onEvent) return;

                    this._lpTimer = setTimeout(() => {
                        this._lpTimer = null;
                        if (this.axis === 'y') return;
                        this.selecting = true;
                        this.dragging = false;
                        this.moved = true;
                        this.axis = 'select';
                        this.selectedDates = [startDate];
                        try { this._capEl && this._capEl.setPointerCapture(this.pointerId); } catch (_) {}
                        try { if (navigator.vibrate) navigator.vibrate([18, 35, 22]); } catch (_) {}
                        try { document.body.classList.add('cal-no-select'); } catch (_) {}
                    }, 480);
                },

                bandMove(e) {
                    if (this.pointerId != null && e.pointerId !== this.pointerId) return;

                    if (this.selecting) {
                        const d = this.dayAtPoint(e.clientX, e.clientY);
                        if (d) this.selectedDates = this.datesBetween(this.rangeStart, d);
                        e.preventDefault();
                        return;
                    }

                    if (!this.dragging) return;
                    const dx = e.clientX - this.startX;
                    const dy = e.clientY - this.startY;

                    if (!this.axis) {
                        if (Math.abs(dx) < 10 && Math.abs(dy) < 10) return;
                        this.axis = Math.abs(dy) >= Math.abs(dx) ? 'y' : 'x';
                        if (this.axis === 'y') {
                            this.clearLp();
                        }
                        if (this.axis === 'x') {
                            this.dragging = false;
                            this.pointerId = null;
                            this.axis = null;
                            return;
                        }
                        try { this._capEl && this._capEl.setPointerCapture(e.pointerId); } catch (_) {}
                        this.moved = true;
                    }
                    if (this.axis !== 'y') return;

                    this.bandX = dy;
                    const w = this.bandW || 1;
                    if (dx < -8) this.previewTitle(2);
                    else if (dx > 8) this.previewTitle(0);
                    else this.previewTitle(1);
                    e.preventDefault();
                },

                bandTouchMove(e) {
                    if (!this.dragging || !e.touches || !e.touches[0]) return;
                    this.bandMove({
                        pointerId: this.pointerId,
                        clientX: e.touches[0].clientX,
                        clientY: e.touches[0].clientY,
                        preventDefault: () => { if (e.cancelable) e.preventDefault(); },
                    });
                },

                goWeek(date) {
                    if (!date) return;
                    window.calWeekJumpDate = date;
                    window.calWeekPos = null;
                    this.$wire.openWeekAt(date);
                },

                async bandUp(e) {
                    if (this._lockPointer != null && e && e.pointerId != null && e.pointerId === this._lockPointer) {
                        const dy = e.clientY - this._lockStartY;
                        this._lockPointer = null;
                        if (Math.abs(dy) > 40) this.pending += (dy < 0 ? 1 : -1);
                        this.flushMonths();
                        return;
                    }
                    if (this.pointerId != null && e && e.pointerId != null && e.pointerId !== this.pointerId) return;
                    this.clearLp();
                    if (this.selecting) {
                        this.finishSelect();
                        return;
                    }

                    const tapped = this.dragging && !this.moved && !this.axis;
                    if (tapped) {
                        const date = this.rangeStart;
                        const toWeek = this._tapOnNum;
                        const onPill = this._tapOnPill;
                        this.dragging = false;
                        this.pointerId = null;
                        this.axis = null;
                        this.bandX = 0;
                        this._tapOnNum = false;
                        this._tapOnPill = false;
                        this.clearPreview();
                        if (onPill || !date) return;
                        if (window.calUiBlockUntil && Date.now() < window.calUiBlockUntil) return;
                        if (window.calModal && window.calModal.open) return;
                        if (toWeek) this.goWeek(date);
                        else window.calOpenCreateNow(this.$wire, date);
                        return;
                    }

                    if (!this.dragging && !this.moved) return;

                    const wasY = this.axis === 'y' && this.moved;
                    this.dragging = false;
                    this.pointerId = null;

                    if (!wasY) {
                        this.axis = null;
                        this.moved = false;
                        this.bandX = 0;
                        this.clearPreview();
                        return;
                    }

                    const step = this.bandStep();
                    let dir = 0;
                    if (this.bandX < -step * 0.12 || this.bandX < -48) dir = 1;
                    else if (this.bandX > step * 0.12 || this.bandX > 48) dir = -1;

                    this.axis = null;

                    if (dir === 0) {
                        this.settling = true;
                        this.bandX = 0;
                        this.clearPreview();
                        setTimeout(() => {
                            this.settling = false;
                            this.moved = false;
                        }, 200);
                        return;
                    }

                    this.pending += dir;
                    this.flushMonths();
                },

                async flushMonths() {
                    if (this._flushing) return;
                    this._flushing = true;
                    this.lock = true;
                    try {
                        while (this.pending !== 0) {
                            const steps = this.pending;
                            this.pending = 0;
                            const step = this.bandStep();
                            this.settling = true;
                            this.bandX = steps > 0 ? -step : step;
                            this.previewTitle(steps > 0 ? 2 : 0);
                            await new Promise(r => setTimeout(r, 140));
                            this.settling = false;
                            await this.$wire.changeMonth(steps);
                            this.snapBandHome();
                            this.measure();
                            this.previewTitle(1);
                        }
                    } catch (_) {
                        this.pending = 0;
                        this.snapBandHome();
                        this.previewTitle(1);
                    } finally {
                        this._flushing = false;
                        this.lock = false;
                        this.moved = false;
                    }
                },
            };
        };

        window.calRemoveWeekEvent = function (id) {
            const sid = String(id);
            document.querySelectorAll('.cal-week-shell [data-event-id="'+sid+'"]').forEach((el) => el.remove());
            if (typeof window.calRefreshWeekHints === 'function') window.calRefreshWeekHints();
        };

        window.calWeekScroll = function () {
            return {
                loading: false,
                hLeft: (window.calWeekPos && typeof window.calWeekPos.hLeft === 'number') ? window.calWeekPos.hLeft : 0,
                _programmatic: false,
                _extendCooldownUntil: 0,
                _syncing: false,
                _axis: null,
                _bridging: false,
                _startX: 0,
                _startY: 0,
                _startLeft: 0,
                _startAt: 0,
                _lastX: 0,
                _lastAt: 0,
                _ovY: '',

                init() {
                    this._onRemeasure = () => this.layoutWhenVisible();
                    this._onHash = () => this.queueOverflowHints();
                    window.addEventListener('cal-remeasure', this._onRemeasure);
                    window.addEventListener('hashchange', this._onHash);
                    this.$nextTick(() => {
                        this.layoutWhenVisible();
                        this.bindHBridge();
                        this.bindOverflowScroll();
                        window.calRefreshWeekHints = () => this.updateOverflowHints();
                        const hEl = this.h();
                        if (hEl && window.ResizeObserver) {
                            let lastW = hEl.clientWidth;
                            new ResizeObserver(() => {
                                const nowW = hEl.clientWidth;
                                if (nowW < 120 || Math.abs(nowW - lastW) < 1) return;
                                const oldDay = lastW / 3;
                                const idx = oldDay >= 40 ? Math.round(this.hLeft / oldDay) : 0;
                                lastW = nowW;
                                const day = this.measureDayWidth();
                                if (day >= 40) {
                                    this.setH(idx * day);
                                    this.updateOverflowHints();
                                }
                            }).observe(hEl);
                        }
                        window.addEventListener('resize', () => {
                            const keep = this.hLeft;
                            this.measureDayWidth();
                            this.setH(keep);
                            this.updateOverflowHints();
                        }, { passive: true });
                    });
                },

                destroy() {
                    this._dead = true;
                    this.hideOverflowHints();
                    if (this._onRemeasure) window.removeEventListener('cal-remeasure', this._onRemeasure);
                    if (this._onHash) window.removeEventListener('hashchange', this._onHash);
                    this.unbindOverflowScroll();
                    if (window.calRefreshWeekHints) window.calRefreshWeekHints = null;
                },

                hideOverflowHints() {
                    this.$el.querySelectorAll('.cal-day-more').forEach((el) => { el.hidden = true; });
                },

                bindOverflowScroll() {
                    const v = this.v() || this.$el.querySelector('.cal-week-bottom');
                    if (!v || this._onVScroll) return;
                    this._onVScroll = () => this.queueOverflowHints();
                    v.addEventListener('scroll', this._onVScroll, { passive: true });
                    v.addEventListener('touchmove', this._onVScroll, { passive: true });
                    this.$el.addEventListener('scroll', this._onVScroll, { capture: true, passive: true });
                },

                unbindOverflowScroll() {
                    const v = this.v() || this.$el.querySelector('.cal-week-bottom');
                    if (v && this._onVScroll) {
                        v.removeEventListener('scroll', this._onVScroll);
                        v.removeEventListener('touchmove', this._onVScroll);
                    }
                    if (this._onVScroll) this.$el.removeEventListener('scroll', this._onVScroll, { capture: true });
                    this._onVScroll = null;
                },

                weekShellStyle() {
                    const w = window.calWeekDayW;
                    return w ? ('--cal-day-w:' + w + 'px') : '';
                },

                saveWeekPos() {
                    if (this._dead || (window.calWeekForceToday && Date.now() - window.calWeekForceToday < 4000)) return;
                    const h = this.h();
                    const w = h && h.clientWidth ? h.clientWidth / 3 : 0;
                    if (w >= 40) window.calWeekDayW = w;
                    const dayW = this.colWidth();
                    window.calWeekPos = {
                        hLeft: this.hLeft,
                        hDays: dayW ? this.hLeft / dayW : null,
                        vTop: this.v() ? this.v().scrollTop : ((window.calWeekPos && window.calWeekPos.vTop) || 0),
                    };
                },

                restoreWeekPos() {
                    const saved = window.calWeekPos;
                    if (!saved || typeof saved.hLeft !== 'number') return false;
                    const day = this.colWidth();
                    const days = (typeof saved.hDays === 'number') ? saved.hDays : (day ? saved.hLeft / day : null);
                    this.hLeft = (day && days !== null) ? this.clampH(Math.round(days) * day) : saved.hLeft;
                    const v = this.v();
                    if (v && typeof saved.vTop === 'number') {
                        v.scrollTop = saved.vTop;
                    }
                    this.updateWeekTitle();
                    return true;
                },

                layoutWhenVisible() {
                    const apply = () => {
                        const w = this.measureDayWidth();
                        if (w < 40) return false;
                        const jump = window.calWeekJumpDate;
                        const force = window.calWeekForceToday && (Date.now() - window.calWeekForceToday < 4000);
                        if (force) {
                            window.calWeekForceToday = 0;
                            window.calWeekPos = null;
                            this.scrollToToday(false);
                        } else if (jump) {
                            window.calWeekJumpDate = null;
                            window.calWeekPos = null;
                            this.scrollToDate(jump, false);
                        } else if (!this.restoreWeekPos()) {
                            this.scrollToToday(false);
                        }
                        this.updateWeekTitle();
                        return true;
                    };
                    if (apply()) {
                        this.bindOverflowScroll();
                        this.updateOverflowHints();
                        this.$nextTick(() => this.updateOverflowHints());
                        setTimeout(() => this.updateOverflowHints(), 80);
                        return;
                    }
                    let n = 0;
                    const tick = () => {
                        if (apply() || ++n > 24) {
                            this.bindOverflowScroll();
                            this.updateOverflowHints();
                            this.$nextTick(() => this.updateOverflowHints());
                            setTimeout(() => this.updateOverflowHints(), 80);
                            return;
                        }
                        requestAnimationFrame(tick);
                    };
                    requestAnimationFrame(tick);
                },

                queueOverflowHints() {
                    if (this._hintFrame) return;
                    this._hintFrame = requestAnimationFrame(() => {
                        this._hintFrame = 0;
                        this.saveWeekPos();
                        this.updateOverflowHints();
                    });
                },

                updateOverflowHints() {
                    const v = this.v() || this.$el.querySelector('.cal-week-bottom');
                    const h = this.h() || this.$el.querySelector('.cal-week-h-body');
                    if (!v || !h || v.clientHeight < 40) {
                        this.hideOverflowHints();
                        return;
                    }
                    const vRect = v.getBoundingClientRect();
                    const hRect = h.getBoundingClientRect();
                    const slop = 8;

                    this.$el.querySelectorAll('.day-col-body').forEach((col) => {
                        const colRect = col.getBoundingClientRect();
                        const onScreen = colRect.right > hRect.left + 8 && colRect.left < hRect.right - 8;
                        let moreUp = false;
                        let moreDown = false;
                        if (onScreen) {
                            col.querySelectorAll('.event-card.timed').forEach((ev) => {
                                const r = ev.getBoundingClientRect();
                                if (r.bottom <= vRect.top + slop) moreUp = true;
                                if (r.top >= vRect.bottom - slop) moreDown = true;
                            });
                        }

                        const upEl = col.querySelector('.cal-day-more-up');
                        const downEl = col.querySelector('.cal-day-more-down');
                        if (upEl) upEl.hidden = !moreUp;
                        if (downEl) downEl.hidden = !moreDown;
                    });
                },

                h() { return this.$refs.hBody; },
                v() { return this.$refs.vBody; },
                head() { return this.$refs.hHead; },

                trackStyle() {
                    return 'left:' + (-this.hLeft) + 'px';
                },

                measureDayWidth() {
                    const h = this.h();
                    if (!h) return 160;
                    const w = h.clientWidth / 3;
                    if (w >= 40) {
                        window.calWeekDayW = w;
                        this.$el.style.setProperty('--cal-day-w', w + 'px');
                    }
                    return w;
                },

                colWidth() {
                    const col = this.$el.querySelector('.day-col-body');
                    return (col && col.offsetWidth) ? col.offsetWidth : this.measureDayWidth();
                },

                dayCount() {
                    return this.$el.querySelectorAll('.day-col-body').length;
                },

                scrollToToday(smooth) {
                    this.scrollToDate(null, smooth);
                },

                scrollToDate(date, smooth) {
                    this.measureDayWidth();
                    const col = date
                        ? this.$el.querySelector('.day-col-body[data-date="' + date + '"]')
                        : this.$el.querySelector('.day-col-body.is-today');
                    if (!col) return;
                    const left = col.offsetLeft;
                    if (smooth) this.snapTo(left);
                    else this.setH(left);
                    this.queueOverflowHints();
                },

                withProgrammatic(fn, ms) {
                    this._programmatic = true;
                    fn();
                    clearTimeout(this._progTimer);
                    this._progTimer = setTimeout(() => { this._programmatic = false; }, ms || 400);
                },

                clearCreates() {
                    window.dispatchEvent(new CustomEvent('cal-clear-create'));
                },

                clampH(left) {
                    const day = this.colWidth();
                    const max = Math.max(0, (this.dayCount() - 3) * day);
                    return Math.max(0, Math.min(max, left));
                },

                setH(left) {
                    this.hLeft = this.clampH(left);
                    this.updateWeekTitle();
                    this.maybeExtend();
                    this.queueOverflowHints();
                },

                updateWeekTitle() {
                    const day = this.colWidth();
                    const cols = this.$el.querySelectorAll('.day-col-body[data-date]');
                    if (!day || !cols.length) return;
                    const idx = Math.max(0, Math.min(cols.length - 1, Math.round(this.hLeft / day)));
                    const iso = cols[idx].getAttribute('data-date');
                    if (!iso) return;
                    const d = new Date(iso + 'T12:00:00');
                    const label = d.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
                    window.calSetMonthTitle(label);
                },

                snapTo(target) {
                    target = this.clampH(target);
                    const from = this.hLeft;
                    const t0 = performance.now();
                    const dur = 240;
                    this._programmatic = true;
                    const step = (now) => {
                        const t = Math.min(1, (now - t0) / dur);
                        const ease = 1 - Math.pow(1 - t, 3);
                        this.hLeft = from + (target - from) * ease;
                        this.queueOverflowHints();
                        if (t < 1) {
                            requestAnimationFrame(step);
                        } else {
                            this.setH(target);
                            this._programmatic = false;
                        }
                    };
                    requestAnimationFrame(step);
                },

                snapH(startLeft, dx, velocity) {
                    const day = this.colWidth();
                    if (!day) return;

                    let target;
                    if (Math.abs(velocity) > 0.38 || Math.abs(dx) > day * 1.15) {
                        const dir = dx < 0 ? 1 : -1;
                        const page = Math.round(startLeft / (day * 3));
                        target = (page + dir) * day * 3;
                    } else {
                        target = Math.round(this.hLeft / day) * day;
                    }
                    this.snapTo(target);
                },

                /**
                 * H-Wischen am Raster, Listener NICHT auf dem Vertikal-Scroller
                 * (sonst kein natives Momentum).
                 */
                bindHBridge() {
                    const grid = this.h();
                    if (!grid) return;
                    const THRESH = 10;

                    const onStart = (e, clientX, clientY) => {
                        if (this.loading) return false;
                        if (e.target.closest('.cal-create-ghost, .event-resize-handle')) {
                            this._bridging = false;
                            return false;
                        }
                        this._bridging = true;
                        this._axis = null;
                        this._startX = clientX;
                        this._startY = clientY;
                        this._lastX = clientX;
                        this._startLeft = this.hLeft;
                        this._vStart = this.v() ? this.v().scrollTop : 0;
                        this._startAt = Date.now();
                        this._lastAt = this._startAt;
                        return true;
                    };

                    const onMove = (clientX, clientY, e) => {
                        if (!this._bridging) return;
                        if (document.body.classList.contains('cal-no-select')
                            || grid.querySelector('.day-col-body.is-selecting')) {
                            this._bridging = false;
                            return;
                        }

                        const dx = clientX - this._startX;
                        const dy = clientY - this._startY;

                        if (!this._axis) {
                            if (Math.abs(dx) < THRESH && Math.abs(dy) < THRESH) return;
                            this._axis = Math.abs(dx) >= Math.abs(dy) ? 'x' : 'y';
                            if (this._axis === 'x') {
                                this.clearCreates();
                            }
                        }

                        if (this._axis === 'x') {
                            this.setH(this._startLeft - dx);
                            this._lastX = clientX;
                            this._lastAt = Date.now();
                            if (e && e.cancelable) e.preventDefault();
                        }
                    };

                    const onEnd = () => {
                        if (!this._bridging) return;
                        const wasX = this._axis === 'x';
                        const startLeft = this._startLeft;
                        const dx = this._lastX - this._startX;
                        const elapsed = Math.max(1, this._lastAt - this._startAt);
                        const velocity = Math.abs(dx) / elapsed;

                        this._bridging = false;
                        this._axis = null;
                        this._extendCooldownUntil = Math.max(this._extendCooldownUntil, Date.now() + 280);

                        if (!wasX || Math.abs(dx) < 8) return;
                        this.snapH(startLeft, dx, velocity);
                    };

                    let moveNonPassive = null;

                    grid.addEventListener('touchstart', (e) => {
                        if (!e.touches[0]) return;
                        if (!onStart(e, e.touches[0].clientX, e.touches[0].clientY)) return;

                        const firstMove = (ev) => {
                            if (!ev.touches[0] || !this._bridging) return;
                            const dx = ev.touches[0].clientX - this._startX;
                            const dy = ev.touches[0].clientY - this._startY;
                            if (Math.abs(dx) < THRESH && Math.abs(dy) < THRESH) return;

                            this._axis = Math.abs(dx) >= Math.abs(dy) ? 'x' : 'y';
                            grid.removeEventListener('touchmove', firstMove);

                            if (this._axis === 'y') {
                                return;
                            }

                            this.clearCreates();

                            moveNonPassive = (ev2) => {
                                if (!this._bridging || !ev2.touches[0]) return;
                                onMove(ev2.touches[0].clientX, ev2.touches[0].clientY, ev2);
                            };
                            grid.addEventListener('touchmove', moveNonPassive, { passive: false });
                            onMove(ev.touches[0].clientX, ev.touches[0].clientY, ev);
                        };

                        grid.addEventListener('touchmove', firstMove, { passive: true });
                        const cleanup = () => {
                            grid.removeEventListener('touchmove', firstMove);
                            if (moveNonPassive) {
                                grid.removeEventListener('touchmove', moveNonPassive);
                                moveNonPassive = null;
                            }
                            onEnd();
                        };
                        grid.addEventListener('touchend', cleanup, { passive: true, once: true });
                        grid.addEventListener('touchcancel', cleanup, { passive: true, once: true });
                    }, { passive: true });

                    // Header: gleicher Pager (kein eigener Scroll mehr)
                    const head = this.head();
                    if (head) {
                        head.addEventListener('touchstart', (e) => {
                            if (!e.touches[0] || this.loading) return;
                            this._bridging = true;
                            this._axis = 'x';
                            this._startX = e.touches[0].clientX;
                            this._startY = e.touches[0].clientY;
                            this._lastX = this._startX;
                            this._startLeft = this.hLeft;
                            this._startAt = Date.now();
                            this._lastAt = this._startAt;
                        }, { passive: true });
                        head.addEventListener('touchmove', (e) => {
                            if (!this._bridging || this._axis !== 'x' || !e.touches[0]) return;
                            const x = e.touches[0].clientX;
                            this.setH(this._startLeft - (x - this._startX));
                            this._lastX = x;
                            this._lastAt = Date.now();
                            if (e.cancelable) e.preventDefault();
                        }, { passive: false });
                        const headEnd = () => onEnd();
                        head.addEventListener('touchend', headEnd, { passive: true });
                        head.addEventListener('touchcancel', headEnd, { passive: true });
                    }
                },

                async maybeExtend() {
                    if (this.loading || this._programmatic || this._bridging) return;
                    if (Date.now() < this._extendCooldownUntil) return;
                    const w = this.colWidth();
                    if (!w) return;
                    const atStart = this.hLeft <= 8;
                    const atEnd = this.hLeft >= this.clampH(999999) - 8;
                    if (!atStart && !atEnd) return;

                    this.loading = true;
                    this._extendCooldownUntil = Date.now() + 1200;
                    this._programmatic = true;
                    try {
                        if (atStart) {
                            const keep = this.hLeft;
                            await this.$wire.extendPast(14);
                            await this.$nextTick();
                            this.measureDayWidth();
                            this.hLeft = keep + this.colWidth() * 14;
                        } else {
                            await this.$wire.extendFuture(21);
                            await this.$nextTick();
                            this.measureDayWidth();
                        }
                    } finally {
                        requestAnimationFrame(() => {
                            this._programmatic = false;
                            this.loading = false;
                        });
                    }
                },
            };
        };

        window.calSlotCreate = function (date) {
            return {
                date,
                active: false,
                startMin: 0,
                endMin: 0,
                pointerId: null,
                _lpTimer: null,
                _lpMove: null,
                _lpUp: null,
                _onClear: null,
                _cancelled: false,
                _lockV: null,
                _lockTop: 0,

                init() {
                    this._onClear = () => this.reset();
                    window.addEventListener('cal-clear-create', this._onClear);
                    this._holdScroll = () => {
                        if (this._lockV) this._lockV.scrollTop = this._lockTop;
                    };
                },

                destroy() {
                    this.reset();
                    if (this._onClear) {
                        window.removeEventListener('cal-clear-create', this._onClear);
                        this._onClear = null;
                    }
                },

                lockScroll() {
                    const v = this.$el.closest('.cal-week-bottom');
                    if (!v || this._lockV) return;
                    this._lockV = v;
                    this._lockTop = v.scrollTop;
                    v.classList.add('is-create-lock');
                    v.addEventListener('scroll', this._holdScroll);
                },

                unlockScroll() {
                    if (!this._lockV) return;
                    this._lockV.classList.remove('is-create-lock');
                    this._lockV.removeEventListener('scroll', this._holdScroll);
                    this._lockV = null;
                },

                armCapture() {
                    if (this._cap) return;
                    const el = document.createElement('div');
                    el.setAttribute('data-cal-capture', '1');
                    el.style.cssText = 'position:fixed;inset:0;z-index:60000;touch-action:none;';
                    document.body.appendChild(el);
                    this._cap = el;
                    this._blockPage = (ev) => {
                        if (ev.cancelable) ev.preventDefault();
                    };
                    el.addEventListener('touchmove', this._blockPage, { passive: false });
                    document.addEventListener('touchmove', this._blockPage, { passive: false });
                    if (this._lpMove) el.addEventListener('pointermove', this._lpMove, { passive: false });
                    if (this._lpUp) {
                        el.addEventListener('pointerup', this._lpUp);
                        el.addEventListener('touchend', this._lpUp);
                    }
                    try { el.setPointerCapture(this.pointerId); } catch (_) {}
                    try {
                        document.documentElement.classList.add('cal-creating');
                        document.body.classList.add('cal-creating');
                    } catch (_) {}
                },

                dropCapture() {
                    if (this._blockPage) {
                        document.removeEventListener('touchmove', this._blockPage);
                        this._blockPage = null;
                    }
                    try {
                        document.documentElement.classList.remove('cal-creating');
                        document.body.classList.remove('cal-creating');
                    } catch (_) {}
                    if (!this._cap) return;
                    try { this._cap.remove(); } catch (_) {}
                    this._cap = null;
                },

                yToMin(clientY) {
                    const rect = this.$el.getBoundingClientRect();
                    const y = clientY - rect.top;
                    // 15px = Offset der 06:00-Linie; -15 min = Finger sitzt optisch etwas tiefer
                    const raw = Math.max(0, Math.min(18 * 60, y - 15 - 15));
                    return Math.round(raw / 30) * 30;
                },

                minToTime(m) {
                    const total = 6 * 60 + m;
                    const h = Math.floor(total / 60);
                    const min = total % 60;
                    return String(h).padStart(2, '0') + ':' + String(min).padStart(2, '0');
                },

                ghostStyle() {
                    const top = Math.min(this.startMin, this.endMin) + 15;
                    const height = Math.max(30, Math.abs(this.endMin - this.startMin));
                    return `top:${top}px;height:${height}px;`;
                },

                reset() {
                    this._cancelled = true;
                    if (this._preLock) {
                        clearTimeout(this._preLock);
                        this._preLock = null;
                    }
                    if (this._lpTimer) {
                        clearTimeout(this._lpTimer);
                        this._lpTimer = null;
                    }
                    if (this._lpMove) {
                        document.removeEventListener('pointermove', this._lpMove);
                        this._lpMove = null;
                    }
                    if (this._lpUp) {
                        document.removeEventListener('pointerup', this._lpUp);
                        document.removeEventListener('pointercancel', this._lpUp);
                        this._lpUp = null;
                    }
                    this.active = false;
                    this.pointerId = null;
                    this.dropCapture();
                    this.unlockScroll();
                    try { document.body.classList.remove('cal-no-select'); } catch (_) {}
                },

                onDown(e) {
                    if (window.calUiBlockUntil && Date.now() < window.calUiBlockUntil) return;
                    if (e.button != null && e.button !== 0) return;
                    if (e.target.closest('.event-card')) return;

                    this.reset();
                    this._cancelled = false;
                    this.pointerId = e.pointerId;
                    this.startMin = this.yToMin(e.clientY);
                    this.endMin = Math.min(18 * 60, this.startMin + 60);

                    const startX = e.clientX;
                    const startY = e.clientY;
                    const scroller = this.$el.closest('.cal-week-bottom');
                    const scroll0 = scroller ? scroller.scrollTop : 0;

                    this._tapDirty = false;
                    this._lpMove = (ev) => {
                        if (this.pointerId != null && ev.pointerId !== this.pointerId) return;
                        const scrolled = scroller ? Math.abs(scroller.scrollTop - scroll0) : 0;
                        const dist = Math.hypot(ev.clientX - startX, ev.clientY - startY);
                        if (dist > 16) this._tapDirty = true;
                        if (scrolled > 8 || dist > 36) {
                            this.reset();
                        }
                    };
                    this._lpUp = (ev) => {
                        if (this._cancelled) return;
                        if (ev && ev.type === 'pointercancel') {
                            this.reset();
                            return;
                        }
                        if (this.active) return;
                        if (this._tapDirty) {
                            this.reset();
                            return;
                        }
                        const date = this.date;
                        const start = this.minToTime(this.startMin);
                        const end = this.minToTime(Math.min(18 * 60, this.startMin + 60));
                        this.reset();
                        window.calOpenCreateNow(this.$wire, date, start, end);
                    };

                    document.addEventListener('pointermove', this._lpMove, { passive: true });
                    document.addEventListener('pointerup', this._lpUp);
                    document.addEventListener('pointercancel', this._lpUp);

                    this._lpTimer = setTimeout(() => {
                        this._lpTimer = null;
                        if (this._cancelled) return;
                        if (scroller && Math.abs(scroller.scrollTop - scroll0) > 8) {
                            this.reset();
                            return;
                        }

                        if (this._lpMove) {
                            document.removeEventListener('pointermove', this._lpMove);
                            this._lpMove = null;
                        }
                        if (this._lpUp) {
                            document.removeEventListener('pointerup', this._lpUp);
                            document.removeEventListener('pointercancel', this._lpUp);
                            this._lpUp = null;
                        }

                        this.active = true;
                        this.lockScroll();
                        document.body.classList.add('cal-no-select');
                        try { if (navigator.vibrate) navigator.vibrate([18, 35, 22]); } catch (_) {}

                        this._lpMove = (ev) => {
                            if (!this.active || this._cancelled) return;
                            this.endMin = this.yToMin(ev.clientY);
                            if (this.endMin === this.startMin) this.endMin = this.startMin + 30;
                            if (ev.cancelable) ev.preventDefault();
                        };
                        this._lpUp = (ev) => {
                            if (ev && ev.type === 'pointercancel') return;
                            this.finishCreate();
                        };
                        this.armCapture();
                    }, 350);
                },

                finishCreate() {
                    if (!this.active || this._cancelled) {
                        this.reset();
                        return;
                    }

                    let a = this.startMin;
                    let b = Math.max(this.startMin, this.endMin);
                    if (this.endMin < this.startMin) {
                        a = this.endMin;
                        b = this.startMin;
                    }
                    if (b - a < 30) b = a + 60;
                    if (b > 18 * 60) {
                        b = 18 * 60;
                        if (b - a < 30) a = Math.max(0, b - 60);
                    }

                    const date = this.date;
                    const start = this.minToTime(a);
                    const end = this.minToTime(b);
                    this.reset();
                    window.calOpenCreateNow(this.$wire, date, start, end, null, false);
                },

                onCancel() { this.reset(); },
            };
        };

        window.calBlockSelect = function () {
            try {
                document.body.classList.add('cal-no-select');
                const sel = window.getSelection && window.getSelection();
                if (sel && sel.removeAllRanges) sel.removeAllRanges();
            } catch (_) {}
        };

        window.calOpenModalShell = function (intent, showShifts) {
            window.calBlockSelect();
            if (window.calLastPointer) {
                window.calModalOrigin = { x: window.calLastPointer.x, y: window.calLastPointer.y };
            }
            window.calModalAnimStart = null;
            const shifts = showShifts !== false;
            window.calModal = { open: true, busy: true, intent: intent, showShifts: shifts };
            window.dispatchEvent(new CustomEvent('open-modal', { detail: { ready: false, intent: intent, showShifts: shifts } }));
            window.calUiBlockUntil = Date.now() + 400;
        };

        window.calOpenEventNow = function (wire, id, date, isPrivate) {
            window.calOpenModalShell(isPrivate ? 'edit' : 'view');
            wire.openEventModal(String(id), date, !!isPrivate);
        };

        window.calOpenCreateNow = function (wire, date, start, end, dates, showShifts) {
            if (window.calUiBlockUntil && Date.now() < window.calUiBlockUntil) return;
            if (window.calModal && window.calModal.open) return;
            window.calOpenModalShell('create', showShifts);
            if (date) {
                wire.openCreateModalAt(date, start || null, end || null, dates || null, showShifts !== false);
            } else {
                wire.openCreateModal();
            }
        };

        if (!window._calSelectGuard) {
            window._calSelectGuard = true;
            document.addEventListener('pointerdown', function (e) {
                window.calLastPointer = { x: e.clientX, y: e.clientY };
            }, true);
            document.addEventListener('selectstart', function (e) {
                if (!document.body.classList.contains('is-cal-tab')) return;
                if (e.target && e.target.closest && e.target.closest('input, textarea')) return;
                e.preventDefault();
            });
        }

        window.calEventOpen = function (id, date, isPrivate) {
            return {
                id: String(id),
                date,
                isPrivate: !!isPrivate,
                _t: null,
                _moved: false,
                _sx: 0,
                _sy: 0,
                _move: null,
                _up: null,
                onDown(e) {
                    if (window.calUiBlockUntil && Date.now() < window.calUiBlockUntil) return;
                    if (e.button != null && e.button !== 0) return;
                    window.calBlockSelect();
                    this._moved = false;
                    this._sx = e.clientX;
                    this._sy = e.clientY;
                    this.clear();
                    const pointerId = e.pointerId;
                    this._move = (ev) => {
                        if (pointerId != null && ev.pointerId !== pointerId) return;
                        if (Math.hypot(ev.clientX - this._sx, ev.clientY - this._sy) > 10) {
                            this._moved = true;
                            this.clear();
                        }
                    };
                    this._up = () => this.clear();
                    document.addEventListener('pointermove', this._move, { passive: true });
                    document.addEventListener('pointerup', this._up);
                    document.addEventListener('pointercancel', this._up);
                },
                clear() {
                    if (this._t) {
                        clearTimeout(this._t);
                        this._t = null;
                    }
                    if (this._move) {
                        document.removeEventListener('pointermove', this._move);
                        this._move = null;
                    }
                    if (this._up) {
                        document.removeEventListener('pointerup', this._up);
                        document.removeEventListener('pointercancel', this._up);
                        this._up = null;
                    }
                },
                onClick() {
                    if (window.calUiBlockUntil && Date.now() < window.calUiBlockUntil) return;
                    if (this._moved) return;
                    window.calOpenEventNow(this.$wire, this.id, this.date, this.isPrivate);
                },
            };
        };

        window.calEventResize = function (id, initialTop, initialHeight) {
            return {
                id,
                top: initialTop,
                height: Math.max(30, initialHeight),
                resizing: false,
                skipClick: false,
                _move: null,
                _up: null,
                _lpTimer: null,
                _lpMove: null,
                _lpUp: null,

                boxStyle() {
                    return `top:${this.top}px;height:${this.height}px;`;
                },

                minToTime(m) {
                    const total = 6 * 60 + m;
                    const h = Math.floor(total / 60);
                    const min = total % 60;
                    return String(h).padStart(2, '0') + ':' + String(min).padStart(2, '0');
                },

                yToMin(clientY) {
                    const dayEl = this.$el.closest('.day-col-body');
                    const rect = dayEl.getBoundingClientRect();
                    const y = clientY - rect.top;
                    const raw = Math.max(0, Math.min(18 * 60, y - 15));
                    return Math.round(raw / 15) * 15;
                },

                clearLongPress() {
                    if (this._lpTimer) {
                        clearTimeout(this._lpTimer);
                        this._lpTimer = null;
                    }
                    if (this._lpMove) {
                        document.removeEventListener('pointermove', this._lpMove);
                        this._lpMove = null;
                    }
                    if (this._lpUp) {
                        document.removeEventListener('pointerup', this._lpUp);
                        document.removeEventListener('pointercancel', this._lpUp);
                        this._lpUp = null;
                    }
                },

                armLongPress(edge, e) {
                    if (e.button != null && e.button !== 0) return;
                    this.clearLongPress();
                    const startX = e.clientX;
                    const startY = e.clientY;
                    const pointerId = e.pointerId;

                    this._lpMove = (ev) => {
                        if (pointerId != null && ev.pointerId !== pointerId) return;
                        if (Math.hypot(ev.clientX - startX, ev.clientY - startY) > 10) {
                            this.clearLongPress();
                        }
                    };
                    this._lpUp = () => this.clearLongPress();

                    document.addEventListener('pointermove', this._lpMove, { passive: true });
                    document.addEventListener('pointerup', this._lpUp);
                    document.addEventListener('pointercancel', this._lpUp);

                    this._lpTimer = setTimeout(() => {
                        this.clearLongPress();
                        this.skipClick = true;
                        try { if (navigator.vibrate) navigator.vibrate([18, 35, 22]); } catch (_) {}
                        try { document.body.classList.add('cal-no-select'); } catch (_) {}
                        this.startResize(edge, e, pointerId);
                    }, 480);
                },

                onCardPointerDown(e) {
                    if (window.calUiBlockUntil && Date.now() < window.calUiBlockUntil) return;
                    if (e.target.closest('.event-resize-handle')) return;
                },

                onHandlePointerDown(edge, e) {
                    this.armLongPress(edge, e);
                },

                onCardClick() {
                    if (this.skipClick || this.resizing) {
                        this.skipClick = false;
                        return;
                    }
                    const day = this.$el.closest('[data-date]');
                    window.calOpenEventNow(this.$wire, this.id, day ? day.dataset.date : '', true);
                },

                startResize(edge, e, pointerId) {
                    if (this.resizing) return;
                    this.clearLongPress();
                    this.resizing = true;
                    this.skipClick = true;
                    const startAbs0 = this.top - 15;
                    const endAbs0 = startAbs0 + this.height;
                    const origTop = this.top;
                    const origHeight = this.height;
                    try { this.$el.setPointerCapture(pointerId != null ? pointerId : e.pointerId); } catch (_) {}

                    this._move = (ev) => {
                        if (pointerId != null && ev.pointerId !== pointerId) return;
                        const m = this.yToMin(ev.clientY);
                        if (edge === 'start') {
                            let startAbs = m;
                            if (endAbs0 - startAbs < 15) startAbs = endAbs0 - 15;
                            this.top = startAbs + 15;
                            this.height = endAbs0 - startAbs;
                        } else {
                            let endAbs = m;
                            if (endAbs - startAbs0 < 15) endAbs = startAbs0 + 15;
                            if (endAbs > 18 * 60) endAbs = 18 * 60;
                            this.height = endAbs - startAbs0;
                        }
                        this.skipClick = true;
                        ev.preventDefault();
                    };

                    this._up = (ev) => {
                        if (ev && ev.type === 'pointercancel') {
                            // Tablet feuert Cancel oft schon beim Long-Press — nicht speichern
                            return;
                        }
                        if (pointerId != null && ev && ev.pointerId != null && ev.pointerId !== pointerId) return;
                        document.removeEventListener('pointermove', this._move);
                        document.removeEventListener('pointerup', this._up);
                        document.removeEventListener('pointercancel', this._up);
                        try { document.body.classList.remove('cal-no-select'); } catch (_) {}
                        this.resizing = false;
                        if (this.top === origTop && this.height === origHeight) return;
                        const startAbs = this.top - 15;
                        const endAbs = startAbs + this.height;
                        this.$wire.resizePrivateEvent(this.id, this.minToTime(startAbs), this.minToTime(endAbs));
                    };

                    // Erst im nächsten Frame binden, sonst frisst ein verspätetes Cancel den Save
                    requestAnimationFrame(() => {
                        if (!this.resizing) return;
                        document.addEventListener('pointermove', this._move);
                        document.addEventListener('pointerup', this._up);
                    });
                },
            };
        };
    </script>

    <div x-show="showModal"
         style="display: none;"
         class="modal-backdrop"
         :class="{ 'is-busy': modalBusy || modalOpening, 'is-in': showModal && !modalClosing, 'is-out': modalClosing }"
         :style="`--cal-ox: ${originX}px; --cal-oy: ${originY}px; --cal-skip: ${animSkip}ms`">
        <div class="modal-pop" @pointerdown="if ($event.target === $el && !modalBusy && !modalOpening && !modalClosing) closeModal()">
        <div class="modal-card" @click.outside="if (!modalBusy && !modalOpening && !modalClosing) closeModal()">

            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 15px;">
                <h3 style="margin: 0; color: var(--text);" x-text="modalHeading()"></h3>
                <button @click="if (!modalOpening && !modalClosing) closeModal()" style="background: none; border: none; color: var(--muted); cursor: pointer;"><span class="material-symbols-rounded">close</span></button>
            </div>

            @if($modalMode === 'view')
                <h2 style="color: var(--text); margin-top: 0; margin-bottom: 5px;">{{ $modalTitle }}</h2>
                <div style="color: var(--muted); font-size: 0.95rem; margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px;">
                    <div>📅 {{ \Carbon\Carbon::parse($modalDate)->format('d.m.Y') }}</div>
                    @if($modalStartTime) <div>🕰️ {{ $modalStartTime }} - {{ $modalEndTime ?: 'Offen' }} Uhr</div> @endif
                    @if($modalLocation) <div>📍 {{ $modalLocation }}</div> @endif
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @if($modalUrl)
                        <a href="{{ $modalUrl }}" target="_blank" style="padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: var(--text); border-radius: 8px; text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <span class="material-symbols-rounded" style="font-size: 1.1rem;">open_in_new</span> Mehr Infos
                        </a>
                    @endif

                    <button wire:click="toggleFavoriteModal" style="padding: 12px; background: {{ $modalIsFavorite ? 'transparent' : 'var(--color-red-border)' }}; border: 1px solid var(--color-red-border); color: {{ $modalIsFavorite ? 'var(--color-red-border)' : 'var(--bg)' }}; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        {{ $modalIsFavorite ? '❤️ Favorit entfernen' : '🤍 Zu Favoriten hinzufügen' }}
                    </button>
                </div>

            @else
                @if($isFamily && $modalMode === 'create' && $modalShowShifts && $shiftTemplates->isNotEmpty())
                    <div class="cal-shift-chips" x-show="showShifts" x-cloak>
                        @foreach($shiftTemplates as $tpl)
                            <button type="button" class="cal-shift-chip"
                                    style="--chip-color: {{ $tpl->color ?? '#2563eb' }};"
                                    wire:click="createFromShiftTemplate({{ $tpl->id }})">
                                <strong>{{ $tpl->name }}</strong>
                                <small>{{ $tpl->start_label }}–{{ $tpl->end_label }}</small>
                            </button>
                        @endforeach
                    </div>
                    <div class="cal-filter-sep" x-show="showShifts" x-cloak>Oder eigener Termin</div>
                @endif
                <label>Was hast du vor?</label>
                <input type="text" wire:model.live.debounce.350ms="modalTitle" placeholder="{{ $isFamily ? 'z.B. Arzttermin' : 'z.B. Tischreservierung' }}" class="form-input">

                @if($isFamily && $modalEventType !== 'schicht' && ($modalIsBirthday || filled($modalBirthYear)))
                    <label>Geburtsjahr</label>
                    <input type="number" wire:model.live="modalBirthYear" inputmode="numeric" min="1900" max="{{ now('Europe/Vienna')->year }}" placeholder="z.B. 1992" class="form-input">
                    @error('modalBirthYear') <div class="cal-tpl-error">{{ $message }}</div> @enderror
                @endif

                <label>Beginn</label>
                <div class="cal-dt-row">
                    <input type="date" wire:model.live="modalDate" class="form-input">
                    <input type="time" wire:model="modalStartTime" class="form-input" title="Startzeit">
                </div>
                <label>Ende</label>
                <div class="cal-dt-row">
                    <input type="date" wire:model.live="modalEndDate" class="form-input" min="{{ $modalDate }}">
                    <input type="time" wire:model="modalEndTime" class="form-input" title="Endzeit">
                </div>
                @if($modalMode === 'create' && count($modalDates) > 1)
                    <button type="button" class="filter-chk-btn {{ $modalAsSeries ? 'checked' : '' }}"
                            wire:click="$toggle('modalAsSeries')"
                            style="margin-bottom: 15px;">
                        <div class="chk-box"></div>
                        <span>Serie (ein Termin pro Tag)</span>
                    </button>
                @endif

                @if(! $isFamily || $modalEventType !== 'schicht')
                    @if($modalShowLocation)
                        <label>Ort</label>
                        <div class="cal-extra-field">
                            <input type="text" wire:model="modalLocation" placeholder="z.B. Dorfplatz" class="form-input">
                            <button type="button" class="cal-extra-remove" wire:click="clearModalLocation" title="Ort entfernen">
                                <span class="material-symbols-rounded">close</span>
                            </button>
                        </div>
                    @endif
                    @if($modalShowUrl)
                        <label>Link</label>
                        <div class="cal-extra-field">
                            <input type="url" wire:model="modalUrl" placeholder="https://…" class="form-input" inputmode="url">
                            <button type="button" class="cal-extra-open" title="Link öffnen"
                                    @click="const v = $el.parentElement.querySelector('input').value.trim(); if (!v) return; const u = /^https?:\/\//i.test(v) ? v : 'https://' + v; window.open(u, '_blank', 'noopener')">
                                <span class="material-symbols-rounded">open_in_new</span>
                            </button>
                            <button type="button" class="cal-extra-remove" wire:click="clearModalUrl" title="Link entfernen">
                                <span class="material-symbols-rounded">close</span>
                            </button>
                        </div>
                        @error('modalUrl') <div class="cal-tpl-error">{{ $message }}</div> @enderror
                    @endif
                    @if($isFamily || ! $modalShowLocation || ! $modalShowUrl)
                    <div class="cal-extra-add">
                        @if($isFamily && ! $modalIsBirthday && ! filled($modalBirthYear))
                            <button type="button"
                                    class="cal-who cal-who-menu is-m {{ $modalHasM ? 'is-on' : '' }}"
                                    wire:click="toggleModalPerson('M')"
                                    aria-pressed="{{ $modalHasM ? 'true' : 'false' }}">M</button>
                            <button type="button"
                                    class="cal-who cal-who-menu is-l {{ $modalHasL ? 'is-on' : '' }}"
                                    wire:click="toggleModalPerson('L')"
                                    aria-pressed="{{ $modalHasL ? 'true' : 'false' }}">L</button>
                        @endif
                        @if(! $modalShowLocation)
                            <button type="button" wire:click="revealModalLocation">
                                <span class="material-symbols-rounded">add</span> Ort
                            </button>
                        @endif
                        @if(! $modalShowUrl)
                            <button type="button" wire:click="revealModalUrl">
                                <span class="material-symbols-rounded">add</span> Link
                            </button>
                        @endif
                    </div>
                    @endif
                @endif

                @if(! $isFamily || $modalEventType !== 'schicht')
                <button type="button" class="filter-chk-btn {{ $modalRepeatYearly ? 'checked' : '' }}"
                        wire:click="$toggle('modalRepeatYearly')"
                        style="margin-bottom: 15px;">
                    <div class="chk-box"></div>
                    <span>Jährlich wiederholen</span>
                </button>
                @endif

                <label>Farbe</label>
                <div class="color-row">
                    <input type="color" wire:model.live="modalColor" class="color-swatch-input" title="Farbe wählen" wire:change="$set('modalColorTouched', true)">
                    @foreach(['#c4b5fd', '#f9a8d4', '#fca5a5', '#fde68a', '#5eead4', '#e879f9'] as $swatch)
                        <button type="button" class="color-swatch {{ $modalColor === $swatch ? 'active' : '' }}"
                                style="background: {{ $swatch }};"
                                wire:click="pickModalColor('{{ $swatch }}')"></button>
                    @endforeach
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    @if($modalMode === 'edit')
                        <button type="button" @click="deleteAndClose()" style="padding: 0 15px; height: 42px; background: rgba(255,0,0,0.1); border: 1px solid var(--color-red-border); color: var(--color-red-border); border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Löschen">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                    @endif

                    <button type="button" @click="closeModal()" style="flex: 1; height: 42px; padding: 0 15px; background: transparent; border: 1px solid var(--border); color: var(--text); border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                        Abbrechen
                    </button>

                    <button type="button" @click="saveAndClose()" class="add-btn" style="flex: 2; height: 42px;">
                        Speichern
                    </button>
                </div>
            @endif
        </div>
        </div>
    </div>

</div>
