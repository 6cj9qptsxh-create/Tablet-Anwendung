<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FamilyAgendaFeed
{
    public function forRange(int $days = 21, int $limit = 14): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $birthdayDays = 366;
        $scanDays = max($days, $birthdayDays);
        $end = Carbon::today()->addDays($scanDays - 1)->format('Y-m-d');
        $events = $this->loadFamilyEvents($today, $end);
        $now = Carbon::now('Europe/Vienna');

        $shifts = [];
        $notes = [];
        $birthdays = [];

        for ($i = 0; $i < $scanDays; $i++) {
            $date = Carbon::today()->addDays($i)->format('Y-m-d');
            $short = $this->dateShort($date);
            $inAgendaWindow = $i < $days;
            foreach ($events as $ev) {
                $startD = Carbon::parse($ev->start_date)->format('Y-m-d');
                $endD = Carbon::parse($ev->end_date)->format('Y-m-d');
                if ($date < $startD || $date > $endD) {
                    continue;
                }
                if ($this->isOvernightFollowDay($ev, $date)) {
                    continue;
                }
                if ($this->occurrenceEnded($ev, $date, $now)) {
                    continue;
                }
                $familyType = $this->familyType($ev);
                if ($familyType !== 'geburtstag' && ! $inAgendaWindow) {
                    continue;
                }
                $time = empty($ev->start_time)
                    ? 'Ganztägig'
                    : $this->timeLabel($ev, $date);
                $displayTitle = $familyType === 'geburtstag'
                    ? $this->birthdayDisplayName((string) ($ev->title ?? ''))
                    : (string) ($ev->title ?? '');
                $shortTitle = $this->shortTitle($displayTitle);
                $age = $familyType === 'geburtstag'
                    ? $this->ageOnDate($ev->birth_year ?? null, $date)
                    : null;
                $lineTitle = $familyType === 'privat' ? $displayTitle : $shortTitle;
                $who = $familyType === 'privat' ? $this->peopleCode($ev) : '';
                $line = [
                    'date' => $date,
                    'date_short' => $short,
                    'time' => $time,
                    'title' => $displayTitle,
                    'age' => $age,
                    'who' => $who,
                    'line' => $familyType === 'geburtstag'
                        ? ($age !== null
                            ? $short.'  '.$lineTitle.'  '.$age.'J'
                            : $short.'  '.$lineTitle)
                        : $short.'  '.$time.'  '.$lineTitle.($who !== '' ? '  '.$who : ''),
                ];
                if ($familyType === 'schicht') {
                    $shifts[] = $line;
                } elseif ($familyType === 'geburtstag') {
                    $birthdays[] = $line;
                } else {
                    $notes[] = $line;
                }
            }
        }

        $shifts = array_slice($shifts, 0, $limit);
        $notes = array_slice($notes, 0, $limit);
        $birthdays = array_slice($birthdays, 0, $limit);

        return [
            'shifts_count' => count($shifts),
            'notes_count' => count($notes),
            'birthdays_count' => count($birthdays),
            'shifts_text' => implode("\n", array_column($shifts, 'line')),
            'notes_text' => implode("\n", array_column($notes, 'line')),
            'birthdays_text' => implode("\n", array_column($birthdays, 'line')),
            'shifts' => $shifts,
            'notes' => $notes,
            'birthdays' => $birthdays,
        ];
    }

    private function loadFamilyEvents(string $rangeStart, string $rangeEnd)
    {
        $this->ensureBirthYearColumn();
        $this->ensureWhoColumn();
        $rows = DB::table('ferienwohnung_laravel.calendar_events')
            ->whereIn('type', ['schicht', 'privat', 'geburtstag'])
            ->where(function ($q) use ($rangeStart, $rangeEnd) {
                $q->where(function ($inner) use ($rangeStart, $rangeEnd) {
                    $inner->where('end_date', '>=', $rangeStart)
                        ->where('start_date', '<=', $rangeEnd);
                })->orWhere('repeat_yearly', 1);
            })
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();

        return $this->expandYearly($rows, $rangeStart, $rangeEnd);
    }

    private function expandYearly($events, string $rangeStart, string $rangeEnd)
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
            $spanDays = (int) $origStart->diffInDays($origEnd, true);

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

    private function familyType($ev): string
    {
        $type = (string) ($ev->type ?? 'privat');
        if ($type === 'schicht') {
            return 'schicht';
        }
        if ($type === 'geburtstag' || preg_match('/\bgeburtstag\b/ui', (string) ($ev->title ?? '')) || ! empty($ev->birth_year)) {
            return 'geburtstag';
        }

        return 'privat';
    }

    private function isOvernight($ev): bool
    {
        if (empty($ev->start_time) || empty($ev->end_time)) {
            return false;
        }

        return substr((string) $ev->end_time, 0, 5) < substr((string) $ev->start_time, 0, 5);
    }

    private function spanDays($ev): int
    {
        return (int) Carbon::parse($ev->start_date)->startOfDay()
            ->diffInDays(Carbon::parse($ev->end_date)->startOfDay(), true);
    }

    private function isOneNightShift($ev): bool
    {
        return $this->familyType($ev) === 'schicht'
            && $this->isOvernight($ev)
            && $this->spanDays($ev) <= 1;
    }

    private function isOvernightFollowDay($ev, string $date): bool
    {
        if (($ev->type ?? '') !== 'schicht') {
            return false;
        }
        $startD = Carbon::parse($ev->start_date)->format('Y-m-d');

        return $date > $startD && $this->isOneNightShift($ev);
    }

    private function occurrenceEnded($ev, string $date, Carbon $now): bool
    {
        return $now->gte($this->occurrenceEndAt($ev, $date));
    }

    private function occurrenceEndAt($ev, string $date): Carbon
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

    private function timeLabel($ev, string $date): string
    {
        $startD = Carbon::parse($ev->start_date)->format('Y-m-d');
        $endD = Carbon::parse($ev->end_date)->format('Y-m-d');
        $start = $ev->start_time ? substr((string) $ev->start_time, 0, 5) : '';
        $end = $ev->end_time ? substr((string) $ev->end_time, 0, 5) : '';

        if ($endD > $startD && ! $this->isOneNightShift($ev)) {
            if ($date === $startD) {
                return $start !== '' ? 'ab '.$start : 'Ganztägig';
            }
            if ($date === $endD) {
                return $end !== '' ? 'bis '.$end : 'Ganztägig';
            }

            return 'Ganztägig';
        }

        if ($start === '') {
            return 'Ganztägig';
        }

        return $end !== '' ? $start.'–'.$end : $start;
    }

    private function dateShort(string $date): string
    {
        $d = Carbon::parse($date)->locale('de');
        if ($d->isSameDay(Carbon::today('Europe/Vienna'))) {
            return 'Heute';
        }
        if ($d->isSameDay(Carbon::tomorrow('Europe/Vienna'))) {
            return 'Morgen';
        }
        $day = mb_strtoupper(mb_substr(rtrim($d->translatedFormat('D'), '.'), 0, 2));

        return $day.' '.$d->format('d.m.');
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

    private function peopleCode($ev): string
    {
        $stored = strtoupper(preg_replace('/[^ML]/', '', (string) ($ev->who ?? '')) ?? '');
        if ($stored !== '') {
            return (str_contains($stored, 'M') ? 'M' : '').(str_contains($stored, 'L') ? 'L' : '');
        }
        $owner = strtoupper(trim((string) ($ev->owner ?? '')));
        if (! in_array($owner, ['M', 'L'], true)) {
            return '';
        }
        $hasM = $owner === 'M' || (! empty($ev->with_partner) && $owner === 'L');
        $hasL = $owner === 'L' || (! empty($ev->with_partner) && $owner === 'M');

        return ($hasM ? 'M' : '').($hasL ? 'L' : '');
    }

    private function ensureWhoColumn(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        try {
            $has = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['ferienwohnung_laravel', 'calendar_events', 'who']
            );
            if ((int) ($has->c ?? 0) === 0) {
                DB::statement('ALTER TABLE ferienwohnung_laravel.calendar_events ADD COLUMN who VARCHAR(2) NULL DEFAULT NULL');
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

    private function shortTitle(string $title): string
    {
        $title = trim($title);
        if (mb_strlen($title) <= 22) {
            return $title;
        }

        return mb_substr($title, 0, 21).'…';
    }
}
