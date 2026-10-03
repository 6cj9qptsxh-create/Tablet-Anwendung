<?php

namespace App\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Events extends Component
{
    /** Erster Tag der Wochen-Leiste / Anker für Monat & Agenda */
    public $startDate;

    /** week | month | agenda */
    public $viewMode = 'week';

    /** Wie viele Tage in der Wochen-Leiste geladen sind (unendliches Scrollen) */
    public int $dayWindow = 21;

    /** Erhöhen = Wochenraster neu aufbauen (nicht beim bloßen Modal-Öffnen) */
    public int $weekEpoch = 0;

    public $activeTypes = ['operating', 'topevent', 'event', 'info', 'private', 'privat', 'holiday'];

    public $showFavsOnly = false;

    public $inactiveLocations = [];

    /** Familien-Gerät (Owner-Netz) — anderer Datenbestand + Filter */
    public bool $isFamily = false;

    public bool $filterOpen = false;

    public $modalEventType = 'privat';

    public $tplEditId = null;

    public $tplName = '';

    public $tplStartTime = '07:00';

    public $tplEndTime = '16:00';

    public $tplColor = '#2563eb';

    public $modalMode = 'create';

    public $modalEventId = null;

    public $modalTitle = '';

    public $modalDate = '';

    /** Mehrere Tage aus der Monatsauswahl */
    public array $modalDates = [];

    public $modalEndDate = '';

    /** true = ein Termin pro Tag, false = ein durchgehender Zeitraum */
    public bool $modalAsSeries = false;

    public $modalStartTime = '';

    public $modalEndTime = '';

    public $modalLocation = '';

    public bool $modalShowLocation = false;

    public $modalUrl = '';

    public bool $modalShowUrl = false;

    public $modalIsFavorite = false;

    public $modalColor = '#6c25b3';

    public bool $modalColorTouched = false;

    public bool $modalRepeatYearly = false;

    public $modalBirthYear = null;

    public bool $modalIsBirthday = false;

    public bool $modalHasM = false;

    public bool $modalHasL = false;

    public bool $modalShowShifts = true;

    public ?string $modalOwner = null;

    /** L oder M, lokal auf dem Gerät gemerkt. */
    public ?string $actor = null;

    public function mount(): void
    {
        $this->applyFamilyMode();
        // Wochen-Leiste beginnt eine Woche vor heute → heute mittig erreichbar
        $this->startDate = Carbon::today()->subDays(7)->format('Y-m-d');
    }

    public function hydrate(): void
    {
        $this->applyFamilyMode(false);
    }

    private function applyFamilyMode(bool $resetTypes = true): void
    {
        $this->isFamily = $this->detectFamilyDevice();
        if (! $this->isFamily) {
            return;
        }

        $allowed = $this->familyTypes();
        if ($resetTypes) {
            $this->activeTypes = $allowed;
        } else {
            $this->activeTypes = array_values(array_intersect($this->activeTypes, $allowed));
            if ($this->activeTypes === []) {
                $this->activeTypes = $allowed;
            }
        }
    }

    private function detectFamilyDevice(): bool
    {
        return \App\Support\ClientNetwork::isFamily();
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['week', 'month', 'agenda'], true)) {
            return;
        }

        $this->viewMode = $mode;

        if ($mode === 'week') {
            $this->startDate = Carbon::today()->subDays(7)->format('Y-m-d');
            $this->dayWindow = 21;
            $this->weekEpoch++;
            $this->dispatch('cal-scroll-today');
        } elseif ($mode === 'month') {
            $this->startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
            $this->dispatchMonthTitle();
        } else {
            $this->startDate = Carbon::today()->format('Y-m-d');
            $this->dayWindow = 42;
        }
    }

    /** Monat → 3-Tage-Woche um das angetippte Datum */
    public function openWeekAt(string $date): void
    {
        try {
            $target = Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        $this->viewMode = 'week';
        $this->startDate = $target->copy()->subDays(7)->format('Y-m-d');
        $this->dayWindow = max(21, $this->dayWindow);
        $this->weekEpoch++;
        $this->dispatch('cal-scroll-date', date: $target->format('Y-m-d'));
    }

    public function goToToday(): void
    {
        if ($this->viewMode === 'month') {
            $this->startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
            $this->dispatchMonthTitle();

            return;
        }

        if ($this->viewMode === 'agenda') {
            $this->startDate = Carbon::today()->format('Y-m-d');
            $this->dayWindow = 42;

            return;
        }

        // 3-Tage: Raster neu um heute legen (sonst bleibt wire:ignore auf dem Monatssprung)
        $this->startDate = Carbon::today()->subDays(7)->format('Y-m-d');
        $this->dayWindow = 56;
        $this->weekEpoch++;
        $this->dispatch('cal-scroll-today');
    }

    public function changeMonth(int $steps): void
    {
        if ($this->viewMode !== 'month') {
            return;
        }

        $this->startDate = Carbon::parse($this->startDate)
            ->startOfMonth()
            ->addMonths($steps)
            ->format('Y-m-d');

        $this->dispatchMonthTitle();
    }

    private function dispatchMonthTitle(): void
    {
        $title = Carbon::parse($this->startDate)
            ->startOfMonth()
            ->locale('de')
            ->translatedFormat('F Y');

        $this->js('window.calSetMonthTitle && window.calSetMonthTitle('.json_encode($title).')');
    }

    /** Weitere Tage nach vorne an die Wochen-/Agenda-Leiste anhängen */
    public function extendFuture(int $days = 21): void
    {
        if (! in_array($this->viewMode, ['week', 'agenda'], true)) {
            return;
        }

        $this->dayWindow = min(180, $this->dayWindow + max(7, (int) $days));
        $this->weekEpoch++;
    }

    /** Weitere Tage nach hinten voranstellen (Wochen-Leiste) */
    public function extendPast(int $days = 14): void
    {
        if ($this->viewMode !== 'week') {
            return;
        }

        $days = max(7, (int) $days);
        $this->startDate = Carbon::parse($this->startDate)->subDays($days)->format('Y-m-d');
        $this->dayWindow = min(180, $this->dayWindow + $days);
        $this->weekEpoch++;
        $this->dispatch('cal-prepended', days: $days);
    }

    public function toggleType($type): void
    {
        if ($this->isFamily && ! in_array($type, $this->familyTypes(), true)) {
            return;
        }

        if (in_array($type, $this->activeTypes)) {
            $this->activeTypes = array_values(array_diff($this->activeTypes, [$type]));
        } else {
            $this->activeTypes[] = $type;
        }
        $this->weekEpoch++;
    }

    public function closeFilters(): void
    {
        $this->filterOpen = false;
    }

    public function toggleLocation($location): void
    {
        if ($this->isFamily) {
            return;
        }

        if (in_array($location, $this->inactiveLocations)) {
            $this->inactiveLocations = array_diff($this->inactiveLocations, [$location]);
        } else {
            $this->inactiveLocations[] = $location;
        }
        $this->weekEpoch++;
    }

    public function toggleFavsOnly(): void
    {
        if ($this->isFamily) {
            return;
        }

        $this->showFavsOnly = ! $this->showFavsOnly;
        $this->weekEpoch++;
    }

    public function toggleFavorite($eventId, $date): void
    {
        if ($this->isFamily) {
            return;
        }

        $exists = DB::table('ferienwohnung_laravel.guest_favorites')
            ->where('event_id', $eventId)
            ->where('selected_date', $date)
            ->exists();

        if ($exists) {
            DB::table('ferienwohnung_laravel.guest_favorites')
                ->where('event_id', $eventId)
                ->where('selected_date', $date)
                ->delete();
        } else {
            DB::table('ferienwohnung_laravel.guest_favorites')
                ->insert(['event_id' => $eventId, 'selected_date' => $date]);
        }
    }

    public function toggleFavoriteModal(): void
    {
        $this->toggleFavorite($this->modalEventId, $this->modalDate);
        $this->modalIsFavorite = ! $this->modalIsFavorite;
    }

    public function openCreateModal(): void
    {
        $this->openCreateModalAt(Carbon::today()->format('Y-m-d'));
    }

    public function openCreateModalAt($date, $startTime = null, $endTime = null, $dates = null, $showShifts = true): void
    {
        $this->reset(['modalEventId', 'modalTitle', 'modalStartTime', 'modalEndTime', 'modalLocation', 'modalShowLocation', 'modalUrl', 'modalShowUrl', 'modalIsFavorite', 'modalBirthYear']);
        $this->modalMode = 'create';
        $this->modalEventType = 'privat';
        $this->modalAsSeries = false;
        $this->modalIsBirthday = false;
        $this->modalHasM = $this->actor === 'M';
        $this->modalHasL = $this->actor === 'L';
        $this->modalOwner = null;
        $this->modalShowShifts = ! in_array($showShifts, [false, 0, '0', 'false'], true);
        $this->modalDates = $this->normalizeDates($dates, $date);
        $this->modalDate = $this->modalDates[0] ?? Carbon::today()->format('Y-m-d');
        $this->modalEndDate = $this->modalDates[count($this->modalDates) - 1] ?? $this->modalDate;
        $this->modalStartTime = $startTime ? substr((string) $startTime, 0, 5) : '';
        $this->modalEndTime = $endTime ? substr((string) $endTime, 0, 5) : '';
        $this->modalColor = '#c4b5fd';
        $this->modalColorTouched = false;
        $this->modalRepeatYearly = false;
        $this->dispatch('open-modal', ready: true, showShifts: $this->modalShowShifts);
    }

    public function updatedModalTitle($value): void
    {
        $this->applyTitlePreset((string) $value);
    }

    public function updatedModalBirthYear($value): void
    {
        if ($value === '' || $value === null) {
            $this->modalBirthYear = null;

            return;
        }
        $this->modalIsBirthday = true;
        $this->modalRepeatYearly = true;
        $this->applyTitlePreset('Geburtstag');
    }

    public function updatedModalDate(): void
    {
        $this->syncModalRangeFromFields();
    }

    public function updatedModalEndDate(): void
    {
        $this->syncModalRangeFromFields();
    }

    public function pickModalColor(string $color): void
    {
        $this->modalColor = $color;
        $this->modalColorTouched = true;
    }

    public function openEventModal($id, $date, $isPrivate): void
    {
        $this->reset(['modalEventId', 'modalTitle', 'modalStartTime', 'modalEndTime', 'modalLocation', 'modalShowLocation', 'modalUrl', 'modalShowUrl', 'modalIsFavorite', 'modalBirthYear']);
        $this->modalDates = [];
        $this->modalAsSeries = false;
        $this->modalIsBirthday = false;
        $this->modalHasM = false;
        $this->modalHasL = false;
        $this->modalOwner = null;
        $this->modalEventId = $id;
        $this->modalDate = $date;
        $this->modalEndDate = $date;
        $this->modalColor = '#c4b5fd';
        $this->modalColorTouched = true;
        $this->modalRepeatYearly = false;
        $this->modalEventType = 'privat';

        if ($this->isFamily) {
            $this->modalMode = 'edit';
            $ev = DB::table('ferienwohnung_laravel.calendar_events')
                ->where('id', $id)
                ->whereIn('type', $this->familyTypes())
                ->first();
            if ($ev) {
                $this->modalEventType = ($ev->type ?? '') === 'schicht' ? 'schicht' : 'privat';
                $this->modalTitle = $ev->title;
                $this->modalStartTime = $ev->start_time ? Carbon::parse($ev->start_time)->format('H:i') : '';
                $this->modalEndTime = $ev->end_time ? Carbon::parse($ev->end_time)->format('H:i') : '';
                $this->modalLocation = $ev->location ?? '';
                $this->modalShowLocation = trim((string) $this->modalLocation) !== '';
                $this->modalUrl = $ev->url ?? '';
                $this->modalShowUrl = trim((string) $this->modalUrl) !== '';
                $this->modalColor = $ev->color ?: ($this->modalEventType === 'schicht' ? '#2563eb' : '#c4b5fd');
                $this->applyLoadedDates($ev->start_date ?? $date, $ev->end_date ?? $date, $this->modalStartTime, $this->modalEndTime);
                $this->modalRepeatYearly = (bool) ($ev->repeat_yearly ?? false);
                $people = $this->peopleFromEvent($ev);
                $this->modalHasM = str_contains($people, 'M');
                $this->modalHasL = str_contains($people, 'L');
                $this->modalOwner = $this->personCode($ev->owner ?? null);
                $this->modalIsBirthday = ($ev->type ?? '') === 'geburtstag' || $this->isBirthdayTitle((string) ($ev->title ?? ''));
                $this->modalBirthYear = ! empty($ev->birth_year) ? (int) $ev->birth_year : null;
            }
            $this->dispatch('open-modal', ready: true);

            return;
        }

        if ($isPrivate) {
            $this->modalMode = 'edit';
            $ev = DB::table('ferienwohnung_laravel.guest_private_events')->where('id', $id)->first();
            if ($ev) {
                $this->modalTitle = $ev->title;
                $this->modalStartTime = $ev->start_time ? Carbon::parse($ev->start_time)->format('H:i') : '';
                $this->modalEndTime = $ev->end_time ? Carbon::parse($ev->end_time)->format('H:i') : '';
                $this->modalLocation = $ev->location ?? '';
                $this->modalShowLocation = trim((string) $this->modalLocation) !== '';
                $this->modalUrl = $ev->url ?? '';
                $this->modalShowUrl = trim((string) $this->modalUrl) !== '';
                $this->modalColor = $ev->color ?: '#c4b5fd';
                $this->applyLoadedDates($ev->start_date ?? $date, $ev->end_date ?? $date, $this->modalStartTime, $this->modalEndTime);
                $this->modalRepeatYearly = (bool) ($ev->repeat_yearly ?? false);
            }
        } else {
            $this->modalMode = 'view';
            if (str_starts_with((string) $id, 'h_')) {
                $holidays = $this->getAustrianHolidays(Carbon::parse($date)->year);
                $this->modalTitle = $holidays[$date] ?? 'Feiertag';
            } else {
                $ev = DB::table('ferienwohnung_laravel.calendar_events')->where('id', $id)->first();
                if ($ev) {
                    $this->modalTitle = $ev->title;
                    $this->modalStartTime = $ev->start_time ? Carbon::parse($ev->start_time)->format('H:i') : '';
                    $this->modalEndTime = $ev->end_time ? Carbon::parse($ev->end_time)->format('H:i') : '';
                    $this->modalLocation = $ev->location;
                    $this->modalUrl = $ev->url ?? '';
                }
            }
            $this->modalIsFavorite = DB::table('ferienwohnung_laravel.guest_favorites')
                ->where('event_id', $id)
                ->where('selected_date', $date)
                ->exists();
        }

        $this->dispatch('open-modal', ready: true);
    }

    public function savePrivateEvent(): void
    {
        $this->validate([
            'modalTitle' => 'required|string|max:255',
            'modalDate' => 'required|date',
            'modalEndDate' => 'nullable|date',
            'modalBirthYear' => 'nullable|integer|min:1900|max:'.Carbon::now('Europe/Vienna')->year,
        ]);
        $this->syncModalRangeFromFields();

        $this->applyTitlePreset((string) $this->modalTitle);
        $this->ensureRepeatYearlyColumn();
        $this->ensureBirthYearColumn();
        $this->ensureGuestEventUrlColumn();
        $this->ensurePeopleColumns();
        if ($msg = $this->eventUrlError()) {
            $this->addError('modalUrl', $msg);

            return;
        }

        if ($this->isFamily) {
            $type = $this->modalEventType === 'schicht' ? 'schicht' : 'privat';
            $birthYear = $this->normalizedBirthYear();
            if ($type !== 'schicht' && ($this->isBirthdayTitle((string) $this->modalTitle) || $birthYear || $this->modalIsBirthday)) {
                $type = 'geburtstag';
            }
            if ($type === 'geburtstag') {
                $this->modalRepeatYearly = true;
            }
            $base = [
                'title' => $this->modalTitle,
                'start_time' => $this->modalStartTime ? substr((string) $this->modalStartTime, 0, 5).':00' : null,
                'end_time' => $this->modalEndTime ? substr((string) $this->modalEndTime, 0, 5).':00' : null,
                'location' => $type !== 'schicht' ? $this->normalizedEventLocation() : null,
                'url' => $type !== 'schicht' ? $this->normalizedEventUrl() : null,
                'type' => $type,
                'color' => $this->modalColor ?: ($type === 'schicht' ? '#2563eb' : '#6c25b3'),
                'show_dot' => 0,
                'show_month_info' => 0,
                'show_booking_detail' => 0,
                'show_guest_app' => 0,
                'repeat_yearly' => $this->modalRepeatYearly ? 1 : 0,
                'birth_year' => $type === 'geburtstag' ? $birthYear : null,
                'who' => in_array($type, ['schicht', 'geburtstag'], true) ? null : $this->modalWhoCode(),
                'with_partner' => in_array($type, ['schicht', 'geburtstag'], true) ? 0 : (($this->modalHasM && $this->modalHasL) ? 1 : 0),
                'updated_at' => now(),
            ];

            if ($this->modalMode === 'edit' && $this->modalEventId) {
                $base['start_date'] = $this->spanStartDate();
                $base['end_date'] = $this->spanEndDate($base['start_time'], $base['end_time']);
                DB::table('ferienwohnung_laravel.calendar_events')
                    ->where('id', $this->modalEventId)
                    ->whereIn('type', $this->familyTypes())
                    ->update($base);
            } elseif ($this->shouldCreateAsSeries()) {
                foreach ($this->datesToCreate() as $day) {
                    $row = $base;
                    $row['start_date'] = $day;
                    $row['end_date'] = $this->endDateForTimes($day, $base['start_time'], $base['end_time']);
                    $row['created_at'] = now();
                    $row['owner'] = $this->actorCode();
                    DB::table('ferienwohnung_laravel.calendar_events')->insert($row);
                }
            } else {
                $row = $base;
                $row['start_date'] = $this->spanStartDate();
                $row['end_date'] = $this->spanEndDate($base['start_time'], $base['end_time']);
                $row['created_at'] = now();
                $row['owner'] = $this->actorCode();
                DB::table('ferienwohnung_laravel.calendar_events')->insert($row);
            }

            $this->weekEpoch++;
            $this->dispatch('close-modal');

            return;
        }

        $data = [
            'title' => $this->modalTitle,
            'start_time' => $this->modalStartTime ?: null,
            'end_time' => $this->modalEndTime ?: null,
            'location' => $this->normalizedEventLocation(),
            'url' => $this->normalizedEventUrl(),
            'color' => $this->modalColor ?: '#6c25b3',
            'repeat_yearly' => $this->modalRepeatYearly ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($this->modalMode === 'edit' && $this->modalEventId) {
            $data['start_date'] = $this->spanStartDate();
            $data['end_date'] = $this->spanEndDate($data['start_time'], $data['end_time']);
            DB::table('ferienwohnung_laravel.guest_private_events')->where('id', $this->modalEventId)->update($data);
        } elseif ($this->shouldCreateAsSeries()) {
            foreach ($this->datesToCreate() as $day) {
                $row = $data;
                $row['start_date'] = $day;
                $row['end_date'] = $day;
                $row['created_at'] = now();
                DB::table('ferienwohnung_laravel.guest_private_events')->insert($row);
            }
        } else {
            $row = $data;
            $row['start_date'] = $this->spanStartDate();
            $row['end_date'] = $this->spanEndDate($data['start_time'], $data['end_time']);
            $row['created_at'] = now();
            DB::table('ferienwohnung_laravel.guest_private_events')->insert($row);
        }

        $this->weekEpoch++;
        $this->dispatch('close-modal');
    }

    public function deletePrivateEvent(): void
    {
        if (! $this->modalEventId) {
            return;
        }

        if ($this->isFamily) {
            DB::table('ferienwohnung_laravel.calendar_events')
                ->where('id', $this->modalEventId)
                ->whereIn('type', $this->familyTypes())
                ->delete();
        } else {
            DB::table('ferienwohnung_laravel.guest_private_events')->where('id', $this->modalEventId)->delete();
        }

        if ($this->viewMode === 'week') {
            $this->js('window.calRemoveWeekEvent('.json_encode((string) $this->modalEventId).')');
            $this->skipRender();
        }

        $this->dispatch('close-modal');
    }

    public function resizePrivateEvent($id, $startTime, $endTime): void
    {
        $startTime = substr((string) $startTime, 0, 5);
        $endTime = substr((string) $endTime, 0, 5);

        if (! preg_match('/^\d{2}:\d{2}$/', $startTime) || ! preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            return;
        }

        if ($endTime <= $startTime) {
            return;
        }

        if ($this->isFamily) {
            DB::table('ferienwohnung_laravel.calendar_events')
                ->where('id', $id)
                ->whereIn('type', $this->familyTypes())
                ->update([
                    'start_time' => $startTime.':00',
                    'end_time' => $endTime.':00',
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('ferienwohnung_laravel.guest_private_events')
                ->where('id', $id)
                ->update([
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'updated_at' => now(),
                ]);
        }

        // Karte ist schon per Alpine verschoben — Re-Render würde den ersten Save optisch zurücksetzen
        $this->skipRender();
    }

    public function createFromShiftTemplate($id, $date = null): void
    {
        if (! $this->isFamily) {
            return;
        }

        $tpl = DB::table('ferienwohnung_laravel.shift_templates')->where('id', $id)->first();
        if (! $tpl) {
            return;
        }

        $now = now();
        $startTime = Carbon::parse($tpl->start_time)->format('H:i:s');
        $endTime = Carbon::parse($tpl->end_time)->format('H:i:s');
        $overnight = substr($endTime, 0, 5) < substr($startTime, 0, 5);

        foreach ($this->datesToCreate($date) as $targetDate) {
            $endDate = $overnight
                ? Carbon::parse($targetDate)->addDay()->format('Y-m-d')
                : $targetDate;

            DB::table('ferienwohnung_laravel.calendar_events')->insert([
                'title' => $tpl->name,
                'start_date' => $targetDate,
                'end_date' => $endDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'type' => 'schicht',
                'color' => $tpl->color ?: '#2563eb',
                'location' => null,
                'url' => null,
                'show_dot' => 0,
                'show_month_info' => 0,
                'show_booking_detail' => 0,
                'show_guest_app' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->weekEpoch++;
        $this->dispatch('close-modal');
    }

    public function editShiftTemplate($id): void
    {
        if (! $this->isFamily) {
            return;
        }

        $tpl = DB::table('ferienwohnung_laravel.shift_templates')->where('id', $id)->first();
        if (! $tpl) {
            return;
        }

        $this->tplEditId = $tpl->id;
        $this->tplName = $tpl->name;
        $this->tplStartTime = Carbon::parse($tpl->start_time)->format('H:i');
        $this->tplEndTime = Carbon::parse($tpl->end_time)->format('H:i');
        $this->tplColor = $tpl->color ?: '#2563eb';
    }

    public function resetTemplateForm(): void
    {
        if (! $this->isFamily) {
            return;
        }

        $this->reset(['tplEditId', 'tplName']);
        $this->tplStartTime = '07:00';
        $this->tplEndTime = '16:00';
        $this->tplColor = '#2563eb';
    }

    public function saveShiftTemplate(): void
    {
        if (! $this->isFamily) {
            return;
        }

        $this->validate([
            'tplName' => 'required|string|max:255',
            'tplStartTime' => 'required',
            'tplEndTime' => 'required',
        ]);

        $start = substr((string) $this->tplStartTime, 0, 5);
        $end = substr((string) $this->tplEndTime, 0, 5);

        if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
            $this->addError('tplEndTime', 'Ungültige Uhrzeit.');

            return;
        }

        if ($end === $start) {
            $this->addError('tplEndTime', 'Start und Ende dürfen nicht gleich sein.');

            return;
        }

        $data = [
            'name' => $this->tplName,
            'start_time' => $start,
            'end_time' => $end,
            'color' => $this->tplColor ?: '#2563eb',
            'updated_at' => now(),
        ];

        if ($this->tplEditId) {
            DB::table('ferienwohnung_laravel.shift_templates')->where('id', $this->tplEditId)->update($data);
        } else {
            $data['sort_order'] = ((int) DB::table('ferienwohnung_laravel.shift_templates')->max('sort_order')) + 1;
            $data['created_at'] = now();
            DB::table('ferienwohnung_laravel.shift_templates')->insert($data);
        }

        $this->resetTemplateForm();
    }

    public function deleteShiftTemplate($id): void
    {
        if (! $this->isFamily) {
            return;
        }

        DB::table('ferienwohnung_laravel.shift_templates')->where('id', $id)->delete();
        if ((int) $this->tplEditId === (int) $id) {
            $this->resetTemplateForm();
        }
    }

    public function render()
    {
        $today = Carbon::today()->format('Y-m-d');
        $weeks = [];
        $days = [];
        $monthBand = [];
        $agendaItems = [];
        $headerLabel = '';
        $dayCount = $this->dayWindow;

        if ($this->viewMode === 'month') {
            $monthStart = Carbon::parse($this->startDate)->startOfMonth();
            $headerLabel = $monthStart->locale('de')->translatedFormat('F Y');
            $monthBand = [];
            foreach ([-1, 0, 1] as $offset) {
                $m = $monthStart->copy()->addMonths($offset);
                $monthBand[] = [
                    'offset' => $offset,
                    'label' => $m->locale('de')->translatedFormat('F Y'),
                    'key' => $m->format('Y-m'),
                    'weeks' => $this->buildMonthWeeks($m, $today),
                ];
            }
            $weeks = $monthBand[1]['weeks'];
        } elseif ($this->viewMode === 'agenda') {
            $baseDate = Carbon::parse($this->startDate)->startOfDay();
            $dayCount = $this->dayWindow;
            $headerLabel = 'Agenda';
            $days = $this->buildDayStrip($baseDate, $dayCount, $today, false);
            $agendaItems = $this->buildAgendaItems($days, $today);
        } else {
            $baseDate = Carbon::parse($this->startDate)->startOfDay();
            $dayCount = $this->dayWindow;
            $headerLabel = Carbon::today()->locale('de')->translatedFormat('F Y');
            $days = $this->buildDayStrip($baseDate, $dayCount, $today, true);
        }

        $now = Carbon::now();
        $nowTopPx = null;
        if ($now->hour >= 6 && $now->hour < 24) {
            $nowTopPx = (($now->hour - 6) * 60) + $now->minute + 15;
        }

        $availableLocations = $this->isFamily ? collect() : $this->collectLocationsForStrip($days, $weeks);
        $shiftTemplates = $this->isFamily ? $this->loadShiftTemplates() : collect();

        return view('livewire.events', [
            'days' => $days,
            'weeks' => $weeks,
            'monthBand' => $monthBand,
            'availableLocations' => $availableLocations,
            'shiftTemplates' => $shiftTemplates,
            'nowTopPx' => $nowTopPx,
            'todayDate' => $today,
            'agendaItems' => $agendaItems,
            'agendaShifts' => $this->flattenAgendaColumn($agendaItems, 'shifts'),
            'agendaNotes' => $this->flattenAgendaColumn($agendaItems, 'notes'),
            'agendaBirthdays' => ($this->isFamily && $this->viewMode === 'agenda')
                ? $this->upcomingAgendaBirthdays($today)
                : $this->flattenAgendaColumn($agendaItems, 'birthdays'),
            'dayCount' => $dayCount,
            'headerLabel' => $headerLabel,
        ]);
    }

    private function collectLocationsForStrip(array $days, array $weeks)
    {
        $locs = collect();
        foreach ($days as $day) {
            foreach ($day['all_day_events'] as $ev) {
                if (! empty($ev->location)) {
                    $locs->push($ev->location);
                }
            }
            foreach ($day['timed_events'] as $ev) {
                if (! empty($ev->location)) {
                    $locs->push($ev->location);
                }
            }
        }
        foreach ($weeks as $week) {
            foreach ($week as $day) {
                foreach ($day['events'] as $ev) {
                    if (! empty($ev->location)) {
                        $locs->push($ev->location);
                    }
                }
            }
        }

        return $locs->unique()->sort()->values();
    }

    private function buildDayStrip(Carbon $baseDate, int $dayCount, string $today, bool $withTimedLayout): array
    {
        $rangeStart = $baseDate->format('Y-m-d');
        $rangeEnd = $baseDate->copy()->addDays($dayCount - 1)->format('Y-m-d');
        $allEvents = $this->loadEventsForRange($rangeStart, $rangeEnd);
        $favorites = $this->loadFavorites();

        $days = [];
        for ($i = 0; $i < $dayCount; $i++) {
            $current = $baseDate->copy()->addDays($i);
            $date = $current->format('Y-m-d');
            $allDay = collect();
            $timed = collect();

            foreach ($allEvents as $ev) {
                $startD = Carbon::parse($ev->start_date)->format('Y-m-d');
                $endD = Carbon::parse($ev->end_date)->format('Y-m-d');
                if ($date < $startD || $date > $endD) {
                    continue;
                }

                $isFavOnThisDay = isset($favorites[$ev->id]) && in_array($date, $favorites[$ev->id], true);
                if (! $this->passesFilters($ev, $isFavOnThisDay)) {
                    continue;
                }

                $dayEvent = clone $ev;
                $dayEvent->is_favorite = $isFavOnThisDay;

                if (empty($ev->start_time)) {
                    $allDay->push($dayEvent);
                } elseif ($withTimedLayout) {
                    $layout = $this->timedLayoutForDay($ev, $date);
                    if ($layout === null) {
                        continue;
                    }
                    $dayEvent->top_px = $layout['top_px'];
                    $dayEvent->height_px = $layout['height_px'];
                    $timed->push($dayEvent);
                } else {
                    $timed->push($dayEvent);
                }
            }

            $days[] = [
                'date' => $date,
                'day_name' => mb_strtoupper(rtrim($current->locale('de')->isoFormat('dd'), '.')).' '.$current->format('d.m.'),
                'day_label' => $current->locale('de')->translatedFormat('l, d. F'),
                'is_today' => $date === $today,
                'all_day_events' => $allDay,
                'timed_events' => $withTimedLayout ? $this->calculateSmartWidths($timed) : $timed,
            ];
        }

        return $days;
    }

    private function buildMonthWeeks(Carbon $monthStart, string $today): array
    {
        $monthStart = $monthStart->copy()->startOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $allEvents = $this->loadEventsForRange($gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d'));
        $favorites = $this->loadFavorites();

        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $cursor->format('Y-m-d');
                $events = [];
                foreach ($allEvents as $ev) {
                    $startD = Carbon::parse($ev->start_date)->format('Y-m-d');
                    $endD = Carbon::parse($ev->end_date)->format('Y-m-d');
                    if ($date < $startD || $date > $endD) {
                        continue;
                    }
                    if ($this->isOvernightFollowDay($ev, $date)) {
                        continue;
                    }
                    $isFav = isset($favorites[$ev->id]) && in_array($date, $favorites[$ev->id], true);
                    if (! $this->passesFilters($ev, $isFav)) {
                        continue;
                    }
                    $label = $ev->display_title ?? $ev->title;
                    $short = $label;
                    $st = '';
                    if ($ev->start_time && $date === $startD) {
                        $st = Carbon::parse($ev->start_time)->format('H:i');
                    } elseif ($ev->end_time && $date === $endD && $endD > $startD) {
                        $st = Carbon::parse($ev->end_time)->format('H:i');
                    }
                    $events[] = (object) [
                        'id' => $ev->id,
                        'title' => $ev->display_title ?? $ev->title,
                        'short' => $short,
                        'color' => $ev->color ?: 'var(--accent)',
                        'is_private' => (bool) ($ev->is_private ?? false),
                        'is_outline' => (bool) ($ev->is_outline ?? false),
                        'title_icon' => $ev->title_icon ?? $this->iconFromTitle($ev->title ?? ''),
                        'is_favorite' => $isFav,
                        'location' => $ev->location ?? null,
                        'time_label' => $st,
                        'age' => $ev->age ?? null,
                    ];
                }
                $week[] = [
                    'date' => $date,
                    'day_num' => $cursor->day,
                    'is_today' => $date === $today,
                    'in_month' => $cursor->month === $monthStart->month,
                    'events' => $events,
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    private function buildAgendaItems(array $days, string $today): array
    {
        $now = Carbon::now('Europe/Vienna');
        $agendaItems = [];
        foreach ($days as $day) {
            $dayEntries = [];
            foreach ($day['all_day_events'] as $ev) {
                if ($this->agendaOccurrenceEnded($ev, $day['date'], $now)) {
                    continue;
                }
                $dayEntries[] = [
                    'sort' => '00:00',
                    'time_label' => 'Ganztägig',
                    'event' => $ev,
                    'date' => $day['date'],
                    'all_day' => true,
                ];
            }
            foreach ($day['timed_events'] as $ev) {
                if ($this->isOvernightFollowDay($ev, $day['date'])) {
                    continue;
                }
                if ($this->agendaOccurrenceEnded($ev, $day['date'], $now)) {
                    continue;
                }
                $meta = $this->agendaTimeLabel($ev, $day['date']);
                $dayEntries[] = [
                    'sort' => $meta['sort'],
                    'time_label' => $meta['time_label'],
                    'event' => $ev,
                    'date' => $day['date'],
                    'all_day' => $meta['all_day'],
                ];
            }
            usort($dayEntries, fn ($a, $b) => strcmp($a['sort'], $b['sort']));
            if ($dayEntries !== []) {
                $short = $this->agendaDateShort($day['date']);
                $isToday = $day['date'] === $today;
                $shifts = [];
                $notes = [];
                $birthdays = [];
                foreach ($dayEntries as $i => $entry) {
                    $dayEntries[$i]['date_short'] = $short;
                    $dayEntries[$i]['is_today'] = $isToday;
                    $familyType = $this->familyEventType($entry['event']);
                    if ($familyType === 'schicht') {
                        $shifts[] = $dayEntries[$i];
                    } elseif ($familyType === 'geburtstag') {
                        $birthdays[] = $dayEntries[$i];
                    } else {
                        $notes[] = $dayEntries[$i];
                    }
                }
                $agendaItems[] = [
                    'date' => $day['date'],
                    'label' => $short,
                    'is_today' => $isToday,
                    'entries' => $dayEntries,
                    'shifts' => $shifts,
                    'notes' => $notes,
                    'birthdays' => $birthdays,
                ];
            }
        }

        return $agendaItems;
    }

    private function agendaDateShort(string $date): string
    {
        $d = Carbon::parse($date)->locale('de');
        if ($d->isSameDay(Carbon::today('Europe/Vienna'))) {
            return 'Heute';
        }
        $day = mb_substr(rtrim($d->translatedFormat('D'), '.'), 0, 2);

        return $day.' '.$d->format('d.m.');
    }

    private function eventSpanDays($ev): int
    {
        $start = Carbon::parse($ev->start_date)->startOfDay();
        $end = Carbon::parse($ev->end_date)->startOfDay();

        return (int) $start->diffInDays($end, true);
    }

    private function isOneNightShift($ev): bool
    {
        return $this->familyEventType($ev) === 'schicht'
            && $this->isOvernightEvent($ev)
            && $this->eventSpanDays($ev) <= 1;
    }

    private function agendaTimeLabel($ev, string $date): array
    {
        $startD = Carbon::parse($ev->start_date)->format('Y-m-d');
        $endD = Carbon::parse($ev->end_date)->format('Y-m-d');
        $start = $ev->start_time ? substr((string) $ev->start_time, 0, 5) : '';
        $end = $ev->end_time ? substr((string) $ev->end_time, 0, 5) : '';

        if ($endD > $startD && ! $this->isOneNightShift($ev)) {
            if ($date === $startD) {
                $label = $start !== '' ? 'ab '.$start : 'Ganztägig';
            } elseif ($date === $endD) {
                $label = $end !== '' ? 'bis '.$end : 'Ganztägig';
            } else {
                $label = 'Ganztägig';
            }

            return [
                'sort' => $start !== '' ? $start : '00:00',
                'time_label' => $label,
                'all_day' => $start === '' || ($date !== $startD && $date !== $endD),
            ];
        }

        if ($start === '') {
            return ['sort' => '00:00', 'time_label' => 'Ganztägig', 'all_day' => true];
        }

        return [
            'sort' => $start,
            'time_label' => $end !== '' ? "{$start} – {$end}" : $start,
            'all_day' => false,
        ];
    }

    private function agendaOccurrenceEnded($ev, string $date, Carbon $now): bool
    {
        return $now->gte($this->agendaOccurrenceEndAt($ev, $date));
    }

    private function agendaOccurrenceEndAt($ev, string $date): Carbon
    {
        $tz = 'Europe/Vienna';
        $startD = Carbon::parse($ev->start_date)->format('Y-m-d');
        $endD = Carbon::parse($ev->end_date)->format('Y-m-d');
        $start = $ev->start_time ? substr((string) $ev->start_time, 0, 5) : '';
        $end = $ev->end_time ? substr((string) $ev->end_time, 0, 5) : '';

        if ($start === '') {
            return Carbon::parse($date, $tz)->addDay()->startOfDay();
        }

        if ($endD > $startD && ! $this->isOneNightShift($ev)) {
            if ($end === '') {
                return Carbon::parse($endD, $tz)->addDay()->startOfDay();
            }

            return Carbon::parse($endD.' '.$end, $tz);
        }

        if ($end !== '' && $end < $start) {
            return Carbon::parse($startD.' '.$end, $tz)->addDay();
        }

        if ($end !== '') {
            return Carbon::parse($date.' '.$end, $tz);
        }

        return Carbon::parse($date.' '.$start, $tz)->addHour();
    }

    private function flattenAgendaColumn(array $groups, string $key): array
    {
        $out = [];
        foreach ($groups as $group) {
            foreach ($group[$key] ?? [] as $entry) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    private function upcomingAgendaBirthdays(string $today, int $horizon = 366): array
    {
        $end = Carbon::parse($today)->addDays($horizon - 1)->format('Y-m-d');
        $out = [];
        foreach ($this->loadEventsForRange($today, $end) as $ev) {
            if ($this->familyEventType($ev) !== 'geburtstag') {
                continue;
            }
            $date = Carbon::parse($ev->start_date)->format('Y-m-d');
            if ($date < $today || $date > $end) {
                continue;
            }
            $out[] = [
                'sort' => '00:00',
                'time_label' => 'Ganztägig',
                'event' => $ev,
                'date' => $date,
                'all_day' => true,
                'date_short' => $this->agendaDateShort($date),
                'is_today' => $date === $today,
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $out;
    }

    private function normalizeDates($dates, $fallback = null): array
    {
        $raw = [];
        if (is_array($dates)) {
            $raw = $dates;
        } elseif (is_string($dates) && $dates !== '') {
            $raw = preg_split('/[,\s]+/', $dates) ?: [];
        }

        if ($raw === [] && $fallback) {
            $raw = [$fallback];
        }

        $out = [];
        foreach ($raw as $d) {
            try {
                $out[] = Carbon::parse((string) $d)->format('Y-m-d');
            } catch (\Exception $e) {
            }
        }

        $out = array_values(array_unique($out));
        sort($out);

        if ($out === []) {
            $out[] = Carbon::today()->format('Y-m-d');
        }

        return $out;
    }

    private function isOvernightEvent($ev): bool
    {
        if (empty($ev->start_time) || empty($ev->end_time)) {
            return false;
        }

        return substr((string) $ev->end_time, 0, 5) < substr((string) $ev->start_time, 0, 5);
    }

    private function isOvernightFollowDay($ev, string $date): bool
    {
        if (($ev->type ?? '') !== 'schicht') {
            return false;
        }
        $startD = Carbon::parse($ev->start_date)->format('Y-m-d');

        return $date > $startD && $this->isOneNightShift($ev);
    }

    private function familyTypes(): array
    {
        return ['schicht', 'privat', 'geburtstag'];
    }

    private function isBirthdayTitle(string $title): bool
    {
        return (bool) preg_match('/\bgeburtstag\b/ui', $title);
    }

    private function familyEventType($ev): string
    {
        $type = (string) ($ev->type ?? 'privat');
        if ($type === 'schicht') {
            return 'schicht';
        }
        if ($type === 'geburtstag' || $this->isBirthdayTitle((string) ($ev->title ?? '')) || ! empty($ev->birth_year)) {
            return 'geburtstag';
        }

        return 'privat';
    }

    private function endDateForTimes(string $startDate, ?string $startTime, ?string $endTime): string
    {
        if ($startTime && $endTime && substr((string) $endTime, 0, 5) < substr((string) $startTime, 0, 5)) {
            return Carbon::parse($startDate)->addDay()->format('Y-m-d');
        }

        return $startDate;
    }

    /** Wochenraster 6–24 Uhr; Nachtdienst: Abend bis 24, Folgetag ab 6 bis Ende. */
    private function timedLayoutForDay($ev, string $date): ?array
    {
        $startD = Carbon::parse($ev->start_date)->format('Y-m-d');
        $endD = Carbon::parse($ev->end_date)->format('Y-m-d');
        $startHour = Carbon::parse($ev->start_time)->hour;
        $startMin = Carbon::parse($ev->start_time)->minute;
        if (empty($ev->end_time)) {
            $endHour = $startHour + 1;
            $endMin = $startMin;
        } else {
            $endHour = Carbon::parse($ev->end_time)->hour;
            $endMin = Carbon::parse($ev->end_time)->minute;
        }

        $multiDay = $endD > $startD;
        $overnight = $multiDay
            || ($endHour < $startHour)
            || ($endHour === $startHour && $endMin <= $startMin);

        if ($multiDay) {
            if ($date === $startD) {
                $endHour = 24;
                $endMin = 0;
            } elseif ($date === $endD) {
                $startHour = 6;
                $startMin = 0;
            } else {
                $startHour = 6;
                $startMin = 0;
                $endHour = 24;
                $endMin = 0;
            }
        } elseif ($overnight && $date > $startD) {
            $startHour = 6;
            $startMin = 0;
        } elseif ($overnight && $date === $startD) {
            $endHour = 24;
            $endMin = 0;
        }

        if ($startHour < 6) {
            $startHour = 6;
            $startMin = 0;
        }
        if ($endHour > 24 || ($endHour === 24 && $endMin > 0)) {
            $endHour = 24;
            $endMin = 0;
        }

        $height = (($endHour * 60) + $endMin) - (($startHour * 60) + $startMin);
        if ($height < 30) {
            if ($overnight && $date > $startD && $endHour <= 6) {
                $height = 30;
            } elseif ($height <= 0) {
                return null;
            } else {
                $height = 30;
            }
        }

        return [
            'top_px' => (($startHour - 6) * 60) + $startMin + 15,
            'height_px' => $height,
        ];
    }

    private function presetForTitle(string $title): ?array
    {
        if ($this->isBirthdayTitle($title)) {
            return ['color' => '#fde68a', 'icon' => 'crown'];
        }
        $t = mb_strtolower($title);
        if (str_contains($t, 'urlaub')) {
            return ['color' => '#5eead4', 'icon' => null];
        }

        return null;
    }

    private function applyTitlePreset(string $title): void
    {
        $preset = $this->presetForTitle($title);
        if ($preset && ($preset['icon'] ?? null) === 'crown') {
            $this->modalRepeatYearly = true;
            $this->modalIsBirthday = true;
        } elseif (! filled($this->modalBirthYear)) {
            $this->modalIsBirthday = false;
        }
        if ($this->modalColorTouched) {
            return;
        }
        if ($this->isFamily && $this->modalEventType === 'schicht') {
            return;
        }
        if ($preset) {
            $this->modalColor = $preset['color'];
        }
    }

    private function iconFromTitle(string $title): ?string
    {
        $preset = $this->presetForTitle($title);

        return $preset['icon'] ?? null;
    }

    private function applyLoadedDates($start, $end, $startTime = null, $endTime = null): void
    {
        $startD = Carbon::parse((string) $start)->format('Y-m-d');
        $endD = Carbon::parse((string) $end)->format('Y-m-d');
        if ($endD < $startD) {
            $endD = $startD;
        }
        $this->modalDate = $startD;
        $this->modalEndDate = $endD;
        $this->modalDates = $this->datesBetweenInclusive($startD, $endD);
    }

    private function datesBetweenInclusive(string $from, string $to): array
    {
        $a = Carbon::parse($from);
        $b = Carbon::parse($to);
        if ($b->lt($a)) {
            [$a, $b] = [$b, $a];
        }
        $out = [];
        for ($d = $a->copy(); $d->lte($b); $d->addDay()) {
            $out[] = $d->format('Y-m-d');
        }

        return $out;
    }

    private function shouldCreateAsSeries(): bool
    {
        if ($this->isFamily && $this->modalEventType === 'schicht') {
            return true;
        }

        return $this->modalAsSeries || count($this->datesToCreate()) <= 1;
    }

    private function spanStartDate(): string
    {
        $dates = $this->datesToCreate();

        return $dates[0] ?? ($this->modalDate ?: Carbon::today()->format('Y-m-d'));
    }

    private function spanEndDate(?string $startTime, ?string $endTime): string
    {
        $start = $this->spanStartDate();
        $end = $this->modalEndDate ?: $start;
        if ($end < $start) {
            $end = $start;
        }
        if ($end > $start) {
            return $end;
        }

        return $this->endDateForTimes($start, $startTime, $endTime);
    }

    private function syncModalRangeFromFields(): void
    {
        if (! $this->modalDate) {
            return;
        }
        try {
            $this->modalDate = Carbon::parse((string) $this->modalDate)->format('Y-m-d');
        } catch (\Exception $e) {
            return;
        }
        if (! $this->modalEndDate || $this->modalEndDate < $this->modalDate) {
            $this->modalEndDate = $this->modalDate;
        } else {
            try {
                $this->modalEndDate = Carbon::parse((string) $this->modalEndDate)->format('Y-m-d');
            } catch (\Exception $e) {
                $this->modalEndDate = $this->modalDate;
            }
        }
        $this->modalDates = $this->datesBetweenInclusive($this->modalDate, $this->modalEndDate);
    }

    private function datesToCreate($fallback = null): array
    {
        if ($this->modalDate && $this->modalEndDate && $this->modalEndDate !== $this->modalDate) {
            return $this->datesBetweenInclusive($this->modalDate, $this->modalEndDate);
        }
        if (count($this->modalDates) > 1) {
            return $this->normalizeDates($this->modalDates);
        }

        return $this->normalizeDates(null, $this->modalDate ?: $fallback);
    }

    private function passesFilters($ev, bool $isFavOnThisDay): bool
    {
        if ($this->isFamily) {
            return in_array($this->familyEventType($ev), $this->activeTypes, true);
        }

        $titleLower = mb_strtolower((string) ($ev->title ?? ''));
        if (($ev->type ?? '') === 'no_delivery' || str_contains($titleLower, 'keine lieferung')) {
            return false;
        }

        $typeOk = in_array($ev->type, $this->activeTypes, true)
            || ($ev->is_private ?? false)
            || ($ev->type ?? '') === 'holiday';
        $locOk = ! in_array($ev->location, $this->inactiveLocations, true);
        $favOk = ! $this->showFavsOnly || $isFavOnThisDay || ($ev->is_private ?? false);

        return $typeOk && $locOk && $favOk;
    }

    private function loadFavorites(): array
    {
        if ($this->isFamily) {
            return [];
        }

        try {
            return DB::table('ferienwohnung_laravel.guest_favorites')
                ->get()
                ->groupBy('event_id')
                ->map(fn ($items) => $items->pluck('selected_date')->toArray())
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function loadEventsForRange(string $rangeStart, string $rangeEnd)
    {
        $this->ensureRepeatYearlyColumn();
        $this->ensureBirthYearColumn();
        $this->ensurePeopleColumns();

        if ($this->isFamily) {
            try {
                return $this->expandYearlyOccurrences(
                    DB::table('ferienwohnung_laravel.calendar_events')
                        ->whereIn('type', $this->familyTypes())
                        ->where(function ($q) use ($rangeStart, $rangeEnd) {
                            $q->where(function ($inner) use ($rangeStart, $rangeEnd) {
                                $inner->where('end_date', '>=', $rangeStart)
                                    ->where('start_date', '<=', $rangeEnd);
                            })->orWhere('repeat_yearly', 1);
                        })
                        ->get(),
                    $rangeStart,
                    $rangeEnd
                )->map(fn ($ev) => $this->decorateFamilyEvent($ev));
            } catch (\Exception $e) {
                return collect();
            }
        }

        $publicEvents = collect();
        try {
            $publicEvents = $this->expandYearlyOccurrences(
                DB::table('ferienwohnung_laravel.calendar_events')
                ->where('show_guest_app', 1)
                ->where(function ($q) use ($rangeStart, $rangeEnd) {
                    $q->where(function ($inner) use ($rangeStart, $rangeEnd) {
                        $inner->where('end_date', '>=', $rangeStart)
                            ->where('start_date', '<=', $rangeEnd);
                    })->orWhere('repeat_yearly', 1);
                })
                ->get()
                ->filter(function ($ev) {
                    if (($ev->type ?? '') === 'no_delivery') {
                        return false;
                    }
                    $title = mb_strtolower((string) ($ev->title ?? ''));

                    return ! str_contains($title, 'keine lieferung');
                })
                ->map(function ($ev) {
                    $ev->is_private = false;
                    $ev->is_outline = false;
                    $ev->title_icon = $this->iconFromTitle($ev->title ?? '');

                    return $ev;
                }),
                $rangeStart,
                $rangeEnd
            )->values();
        } catch (\Exception $e) {
        }

        $privateEvents = collect();
        try {
            $privateEvents = $this->expandYearlyOccurrences(
                DB::table('ferienwohnung_laravel.guest_private_events')
                ->where(function ($q) use ($rangeStart, $rangeEnd) {
                    $q->where(function ($inner) use ($rangeStart, $rangeEnd) {
                        $inner->where('end_date', '>=', $rangeStart)
                            ->where('start_date', '<=', $rangeEnd);
                    })->orWhere('repeat_yearly', 1);
                })
                ->get()
                ->map(function ($ev) {
                    $ev->is_private = true;
                    $ev->is_outline = true;
                    $ev->type = 'private';
                    $ev->title_icon = $this->iconFromTitle($ev->title ?? '');

                    return $ev;
                }),
                $rangeStart,
                $rangeEnd
            );
        } catch (\Exception $e) {
        }

        $years = [Carbon::parse($rangeStart)->year, Carbon::parse($rangeEnd)->year];
        foreach (array_unique($years) as $year) {
            foreach ($this->getAustrianHolidays($year) as $date => $name) {
                if ($date >= $rangeStart && $date <= $rangeEnd) {
                    $publicEvents->push((object) [
                        'id' => 'h_'.$date,
                        'title' => $name,
                        'type' => 'holiday',
                        'start_date' => $date,
                        'end_date' => $date,
                        'start_time' => null,
                        'end_time' => null,
                        'location' => null,
                        'is_private' => false,
                        'color' => 'var(--color-red-border)',
                    ]);
                }
            }
        }

        return $publicEvents->merge($privateEvents);
    }

    private function decorateFamilyEvent($ev)
    {
        $ev->type = $this->familyEventType($ev);
        $ev->is_private = true;
        $ev->is_outline = ($ev->type ?? '') !== 'schicht';
        $ev->owner = $this->personCode($ev->owner ?? null);
        $ev->who = in_array($ev->type, ['schicht', 'geburtstag'], true) ? '' : $this->peopleFromEvent($ev);
        if ($ev->type === 'geburtstag') {
            $ev->display_title = $this->birthdayDisplayName((string) ($ev->title ?? ''));
            $ev->title_icon = 'crown';
            $ev->age = $this->ageOnDate($ev->birth_year ?? null, (string) $ev->start_date);
        } else {
            $ev->display_title = (string) ($ev->title ?? '');
            $ev->title_icon = $this->iconFromTitle((string) ($ev->title ?? ''));
            $ev->age = null;
        }

        return $ev;
    }

    private function birthdayDisplayName(string $title): string
    {
        $name = trim(preg_replace('/^\s*geburtstag\s*[:.\-]?\s*/ui', '', $title) ?? $title);

        return $name !== '' ? $name : $title;
    }

    private function ageOnDate($birthYear, string $date): ?int
    {
        $year = (int) $birthYear;
        if ($year < 1900) {
            return null;
        }
        try {
            $age = (int) Carbon::parse($date)->year - $year;
        } catch (\Exception $e) {
            return null;
        }
        if ($age < 0 || $age > 130) {
            return null;
        }

        return $age;
    }

    private function normalizedBirthYear(): ?int
    {
        if ($this->modalBirthYear === null || $this->modalBirthYear === '') {
            return null;
        }
        $year = (int) $this->modalBirthYear;
        $max = (int) Carbon::now('Europe/Vienna')->year;
        if ($year < 1900 || $year > $max) {
            return null;
        }

        return $year;
    }

    public function setActor(string $who): void
    {
        $who = strtoupper($who);
        if (! in_array($who, ['L', 'M'], true)) {
            return;
        }
        $this->actor = $who;
    }

    public function toggleModalPerson(string $who): void
    {
        if ($who === 'M') {
            $this->modalHasM = ! $this->modalHasM;
        } elseif ($who === 'L') {
            $this->modalHasL = ! $this->modalHasL;
        }
    }

    private function modalWhoCode(): ?string
    {
        $code = ($this->modalHasM ? 'M' : '').($this->modalHasL ? 'L' : '');

        return $code !== '' ? $code : null;
    }

    private function peopleFromEvent($ev): string
    {
        $stored = strtoupper(preg_replace('/[^ML]/', '', (string) ($ev->who ?? '')) ?? '');
        if ($stored !== '') {
            return (str_contains($stored, 'M') ? 'M' : '').(str_contains($stored, 'L') ? 'L' : '');
        }
        $owner = $this->personCode($ev->owner ?? null);
        if ($owner === null) {
            return '';
        }
        $hasM = $owner === 'M' || (! empty($ev->with_partner) && $owner === 'L');
        $hasL = $owner === 'L' || (! empty($ev->with_partner) && $owner === 'M');

        return ($hasM ? 'M' : '').($hasL ? 'L' : '');
    }

    private function actorCode(): ?string
    {
        return $this->personCode($this->actor);
    }

    private function personCode($value): ?string
    {
        $code = strtoupper(trim((string) $value));

        return in_array($code, ['L', 'M'], true) ? $code : null;
    }

    private function ensurePeopleColumns(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        try {
            foreach (['owner' => 'VARCHAR(1) NULL DEFAULT NULL', 'with_partner' => 'TINYINT(1) NOT NULL DEFAULT 0', 'who' => 'VARCHAR(2) NULL DEFAULT NULL'] as $column => $def) {
                $has = DB::selectOne(
                    'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    ['ferienwohnung_laravel', 'calendar_events', $column]
                );
                if ((int) ($has->c ?? 0) === 0) {
                    DB::statement("ALTER TABLE ferienwohnung_laravel.calendar_events ADD COLUMN {$column} {$def}");
                }
            }
        } catch (\Exception $e) {
        }
    }

    public function revealModalLocation(): void
    {
        $this->modalShowLocation = true;
    }

    public function clearModalLocation(): void
    {
        $this->modalShowLocation = false;
        $this->modalLocation = '';
    }

    public function revealModalUrl(): void
    {
        $this->modalShowUrl = true;
    }

    public function clearModalUrl(): void
    {
        $this->modalShowUrl = false;
        $this->modalUrl = '';
        $this->resetErrorBag('modalUrl');
    }

    private function normalizedEventLocation(): ?string
    {
        if (! $this->modalShowLocation) {
            return null;
        }
        $location = trim((string) $this->modalLocation);

        return $location !== '' ? mb_substr($location, 0, 255) : null;
    }

    private function normalizedEventUrl(): ?string
    {
        if (! $this->modalShowUrl) {
            return null;
        }
        $url = trim((string) $this->modalUrl);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            $url = 'https://'.$url;
        }

        return mb_substr($url, 0, 500);
    }

    private function eventUrlError(): ?string
    {
        if (! $this->modalShowUrl || trim((string) $this->modalUrl) === '') {
            return null;
        }
        $url = $this->normalizedEventUrl();
        if ($url === null || ! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            return 'Bitte einen gültigen Link eingeben.';
        }

        return null;
    }

    private function ensureGuestEventUrlColumn(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        try {
            $has = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['ferienwohnung_laravel', 'guest_private_events', 'url']
            );
            if ((int) ($has->c ?? 0) === 0) {
                DB::statement('ALTER TABLE ferienwohnung_laravel.guest_private_events ADD COLUMN url VARCHAR(500) NULL DEFAULT NULL');
            }
        } catch (\Exception $e) {
        }
    }

    private function ensureBirthYearColumn(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        try {
            $has = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['ferienwohnung_laravel', 'calendar_events', 'birth_year']
            );
            if ((int) ($has->c ?? 0) === 0) {
                DB::statement('ALTER TABLE ferienwohnung_laravel.calendar_events ADD COLUMN birth_year SMALLINT UNSIGNED NULL DEFAULT NULL');
            }
        } catch (\Exception $e) {
        }
    }

    private function ensureRepeatYearlyColumn(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        foreach (['calendar_events', 'guest_private_events'] as $table) {
            try {
                $has = DB::selectOne(
                    'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    ['ferienwohnung_laravel', $table, 'repeat_yearly']
                );
                if ((int) ($has->c ?? 0) === 0) {
                    DB::statement("ALTER TABLE ferienwohnung_laravel.{$table} ADD COLUMN repeat_yearly TINYINT(1) NOT NULL DEFAULT 0");
                }
            } catch (\Exception $e) {
            }
        }
    }

    private function expandYearlyOccurrences($events, string $rangeStart, string $rangeEnd)
    {
        $from = Carbon::parse($rangeStart);
        $to = Carbon::parse($rangeEnd);
        $out = collect();

        foreach ($events as $ev) {
            if (empty($ev->repeat_yearly)) {
                $out->push($ev);

                continue;
            }

            $origStart = Carbon::parse($ev->start_date);
            $origEnd = Carbon::parse($ev->end_date);
            $spanDays = $origStart->diffInDays($origEnd);

            for ($year = $from->year - 1; $year <= $to->year + 1; $year++) {
                $occStart = $this->anniversaryInYear($origStart, $year);
                $occEnd = $occStart->copy()->addDays($spanDays);
                if ($occEnd->format('Y-m-d') < $rangeStart || $occStart->format('Y-m-d') > $rangeEnd) {
                    continue;
                }
                $clone = clone $ev;
                $clone->start_date = $occStart->format('Y-m-d');
                $clone->end_date = $occEnd->format('Y-m-d');
                $out->push($clone);
            }
        }

        return $out;
    }

    private function anniversaryInYear(Carbon $orig, int $year): Carbon
    {
        $month = (int) $orig->month;
        $day = (int) $orig->day;
        if ($month === 2 && $day === 29 && ! Carbon::create($year, 1, 1)->isLeapYear()) {
            return Carbon::create($year, 2, 28);
        }

        return Carbon::create($year, $month, $day);
    }

    private function getAustrianHolidays($year)
    {
        $h = [
            $year.'-01-01' => 'Neujahr', $year.'-01-06' => 'Hl. 3 Könige',
            $year.'-05-01' => 'Staatsfeiertag', $year.'-08-15' => 'Mariä Himmelfahrt',
            $year.'-10-26' => 'Nationalfeiertag', $year.'-11-01' => 'Allerheiligen',
            $year.'-12-08' => 'Mariä Empfängnis', $year.'-12-25' => 'Christtag', $year.'-12-26' => 'Stefanitag',
        ];
        $base = new \DateTime("$year-03-21");
        $days = easter_days($year);
        $base->add(new \DateInterval("P{$days}D"));
        $easter = $base->format('Y-m-d');
        $h[date('Y-m-d', strtotime($easter.' +1 day'))] = 'Ostermontag';
        $h[date('Y-m-d', strtotime($easter.' +39 days'))] = 'Christi Himmelfahrt';
        $h[date('Y-m-d', strtotime($easter.' +50 days'))] = 'Pfingstmontag';
        $h[date('Y-m-d', strtotime($easter.' +60 days'))] = 'Fronleichnam';

        return $h;
    }

    private function calculateSmartWidths($events)
    {
        if ($events->isEmpty()) {
            return collect();
        }

        $sorted = $events->sortBy('top_px')->values()->all();
        $columns = [];

        foreach ($sorted as $ev) {
            $placed = false;
            foreach ($columns as $colIndex => &$col) {
                $lastEvent = end($col);
                $lastEventBottom = $lastEvent->top_px + $lastEvent->height_px;
                if ($ev->top_px >= $lastEventBottom) {
                    $col[] = $ev;
                    $ev->colIndex = $colIndex;
                    $placed = true;
                    break;
                }
            }
            unset($col);
            if (! $placed) {
                $ev->colIndex = count($columns);
                $columns[] = [$ev];
            }
        }

        $numCols = count($columns);

        foreach ($sorted as $ev) {
            $colSpan = 1;
            $evBottom = $ev->top_px + $ev->height_px;
            for ($i = $ev->colIndex + 1; $i < $numCols; $i++) {
                $hasCollision = false;
                foreach ($columns[$i] as $other) {
                    $otherBottom = $other->top_px + $other->height_px;
                    if ($ev->top_px < $otherBottom && $evBottom > $other->top_px) {
                        $hasCollision = true;
                        break;
                    }
                }
                if (! $hasCollision) {
                    $colSpan++;
                } else {
                    break;
                }
            }
            $ev->left_pct = ($ev->colIndex / $numCols) * 100;
            $ev->width_pct = ($colSpan / $numCols) * 100;
        }

        return collect($sorted);
    }

    private function loadShiftTemplates()
    {
        $this->ensureShiftTemplatesTable();

        try {
            return DB::table('ferienwohnung_laravel.shift_templates')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(function ($t) {
                    $t->start_label = Carbon::parse($t->start_time)->format('H:i');
                    $t->end_label = Carbon::parse($t->end_time)->format('H:i');

                    return $t;
                });
        } catch (\Exception $e) {
            return collect();
        }
    }

    private function ensureShiftTemplatesTable(): void
    {
        try {
            DB::table('ferienwohnung_laravel.shift_templates')->limit(1)->get();
        } catch (\Exception $e) {
            try {
                DB::statement('
                    CREATE TABLE IF NOT EXISTS ferienwohnung_laravel.shift_templates (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        start_time TIME NOT NULL,
                        end_time TIME NOT NULL,
                        color VARCHAR(50) NOT NULL DEFAULT \'#2563eb\',
                        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                        created_at TIMESTAMP NULL,
                        updated_at TIMESTAMP NULL
                    )
                ');
            } catch (\Exception $ignored) {
                return;
            }
        }

        try {
            if (DB::table('ferienwohnung_laravel.shift_templates')->count() > 0) {
                return;
            }
            $now = now();
            DB::table('ferienwohnung_laravel.shift_templates')->insert([
                ['name' => 'Frühschicht', 'start_time' => '07:00:00', 'end_time' => '16:00:00', 'color' => '#2563eb', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Spätschicht', 'start_time' => '14:00:00', 'end_time' => '23:00:00', 'color' => '#c2410c', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Tagdienst', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'color' => '#15803d', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ]);
        } catch (\Exception $e) {
        }
    }
}
