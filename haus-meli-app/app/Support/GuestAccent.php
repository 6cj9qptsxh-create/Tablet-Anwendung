<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Oberflächenfarbe für den laufenden Aufenthalt.
 * Gespeicherte Personen behalten ihre Farbe über den nächsten Besuch.
 * Gäste ohne Personen-Eintrag behalten sie nur bis zur Abreise.
 * Danach gilt für Gäste #d3e2ff. Die Farbe liegt in guest_surface_colors.
 */
class GuestAccent
{
    public const GUEST_STANDARD = '#d3e2ff';

    /** @var list<string> */
    private const ID_COLUMNS = [
        'guest_id', 'person_id', 'customer_id', 'vip_id',
        'gast_id', 'kunde_id', 'kunden_id',
    ];

    /** @var list<string> */
    private const EMAIL_COLUMNS = ['email', 'guest_email', 'gast_email', 'mail', 'e_mail', 'e-mail'];

    /** @var list<array{0: string, 1: string}> */
    private const NAME_PAIRS = [
        ['vorname', 'nachname'],
        ['first_name', 'last_name'],
        ['guest_first_name', 'guest_last_name'],
        ['firstname', 'lastname'],
    ];

    /** @var list<string> */
    private const NAME_COLUMNS = ['guest_name', 'gastname', 'gast_name', 'full_name', 'name'];

    public static function normalizeHex(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : '';
    }

    public static function dateOnly(mixed $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    public static function coversDate(string $from, string $until, string $today): bool
    {
        return $from !== '' && $until !== '' && $today >= $from && $today <= $until;
    }

    /**
     * Stabiler Schlüssel einer gespeicherten Person. Leer, wenn die Zeile
     * niemanden nennt. Ohne Personen-Spalten ist die Buchung selbst der Schlüssel.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     */
    public static function personKey(array $row, array $columns): string
    {
        $have = [];
        foreach ($columns as $column) {
            $have[strtolower($column)] = true;
        }
        $get = function (string $column) use ($row, $have): string {
            if (! isset($have[$column])) {
                return '';
            }
            foreach ($row as $key => $value) {
                if (strtolower((string) $key) === $column) {
                    return trim((string) $value);
                }
            }

            return '';
        };

        foreach (self::ID_COLUMNS as $column) {
            $value = $get($column);
            if ($value !== '' && $value !== '0') {
                return self::clip($column.':'.$value);
            }
        }

        foreach (self::EMAIL_COLUMNS as $column) {
            $value = strtolower($get($column));
            if (str_contains($value, '@')) {
                return self::clip('email:'.$value);
            }
        }

        foreach (self::NAME_PAIRS as [$first, $last]) {
            $name = self::fold(trim($get($first).' '.$get($last)));
            if ($name !== '') {
                return self::clip('name:'.$name);
            }
        }

        foreach (self::NAME_COLUMNS as $column) {
            $name = self::fold($get($column));
            if ($name !== '') {
                return self::clip('name:'.$name);
            }
        }

        $identity = array_merge(
            self::ID_COLUMNS,
            self::EMAIL_COLUMNS,
            self::NAME_COLUMNS,
            array_merge(...self::NAME_PAIRS)
        );
        foreach ($identity as $column) {
            if (isset($have[$column])) {
                return '';
            }
        }

        $id = $get('id');
        if ($id !== '' && $id !== '0') {
            return self::clip('booking:'.$id);
        }

        return '';
    }

    /**
     * Schlüssel der Farbe für diesen Aufenthalt. Gespeicherte Person, sonst die Buchung.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     */
    public static function stayKey(array $row, array $columns): string
    {
        $person = self::personKey($row, $columns);
        if ($person !== '') {
            return $person;
        }

        $id = '';
        foreach ($row as $key => $value) {
            if (strtolower((string) $key) === 'id') {
                $id = trim((string) $value);
                break;
            }
        }
        if ($id !== '' && $id !== '0') {
            return self::clip('booking:'.$id);
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     * @return array{key: string, from: string, until: string}|null
     */
    public static function pickStay(array $rows, array $columns, string $today): ?array
    {
        $best = null;
        $bestRank = null;

        foreach ($rows as $row) {
            $from = self::dateOnly($row['check_in'] ?? '');
            $until = self::dateOnly($row['check_out'] ?? '');
            if (! self::coversDate($from, $until, $today)) {
                continue;
            }
            $key = self::stayKey($row, $columns);
            if ($key === '') {
                continue;
            }
            $rank = [self::personKey($row, $columns) !== '' ? 1 : 0, $from, (int) ($row['id'] ?? 0)];
            if ($best === null || $rank > $bestRank) {
                $best = ['key' => $key, 'from' => $from, 'until' => $until];
                $bestRank = $rank;
            }
        }

        return $best;
    }

    /**
     * Laufender Aufenthalt inklusive Buchungszeile. Leer, wenn keine Buchung heute trifft.
     *
     * @return array{key: string, from: string, until: string, row: array<string, mixed>}|null
     */
    public static function openStay(): ?array
    {
        try {
            $today = self::today();
            $columns = self::bookingColumns();
            $rows = self::currentRows($today);
            $stay = self::pickStay($rows, $columns, $today);
            if ($stay === null) {
                return null;
            }
            foreach ($rows as $row) {
                if (self::stayKey($row, $columns) !== $stay['key']) {
                    continue;
                }
                if (self::dateOnly($row['check_in'] ?? '') !== $stay['from']) {
                    continue;
                }
                $stay['row'] = $row;

                return $stay;
            }
            $stay['row'] = [];

            return $stay;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param  array{key: string, from: string, until: string}|null  $stay
     * @return array{active: bool, accent: ?string, from: ?string, until: ?string, writable: bool}
     */
    public static function payloadFromStay(?array $stay, ?string $accent, bool $writable): array
    {
        $empty = self::emptyPayload();
        if ($stay === null || ($stay['key'] ?? '') === '') {
            return $empty;
        }
        $hex = self::normalizeHex($accent);

        return [
            'active' => true,
            'accent' => $hex !== '' ? $hex : null,
            'from' => $stay['from'],
            'until' => $stay['until'],
            'writable' => $writable,
        ];
    }

    /** @return array{active: bool, accent: ?string, from: ?string, until: ?string, writable: bool} */
    public static function clientPayload(): array
    {
        $empty = self::emptyPayload();
        try {
            if (! self::guestSurface()) {
                return $empty;
            }
            $today = self::today();
            $stay = self::pickStay(self::currentRows($today), self::bookingColumns(), $today);
            if ($stay === null || $stay['key'] === '') {
                return $empty;
            }
            $accent = null;
            try {
                $accent = self::storedAccent($stay['key']);
            } catch (Throwable $e) {
                $accent = null;
            }

            return self::payloadFromStay($stay, $accent, ! ClientNetwork::isDemo());
        } catch (Throwable $e) {
            return $empty;
        }
    }

    public static function remember(string $hex): bool
    {
        $hex = self::normalizeHex($hex);
        if ($hex === '' || ClientNetwork::isDemo() || ! self::guestSurface()) {
            return false;
        }

        try {
            $today = self::today();
            $stay = self::pickStay(self::currentRows($today), self::bookingColumns(), $today);
            if ($stay === null || $stay['key'] === '') {
                return false;
            }
            self::ensureTable();
            DB::table('ferienwohnung_laravel.guest_surface_colors')->updateOrInsert(
                ['person_key' => $stay['key']],
                ['accent' => $hex, 'updated_at' => now()]
            );

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array{active: bool, accent: null, from: null, until: null, writable: false} */
    private static function emptyPayload(): array
    {
        return [
            'active' => false,
            'accent' => null,
            'from' => null,
            'until' => null,
            'writable' => false,
        ];
    }

    private static function guestSurface(): bool
    {
        return ! ClientNetwork::isFamily() || ClientNetwork::guestPreview();
    }

    private static function today(): string
    {
        return now()->timezone('Europe/Vienna')->toDateString();
    }

    private static function fold(string $value): string
    {
        $value = trim((string) (preg_replace('/\s+/u', ' ', $value) ?? ''));

        return $value === '' ? '' : mb_strtolower($value);
    }

    private static function clip(string $key): string
    {
        return mb_substr($key, 0, 191);
    }

    /** @return list<array<string, mixed>> */
    private static function currentRows(string $today): array
    {
        $rows = DB::table('ferienwohnung_laravel.bookings')
            ->where('check_in', '<=', $today)
            ->where('check_out', '>=', $today)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = self::rowArray($row);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function rowArray(object $row): array
    {
        $out = [];
        foreach ((array) $row as $key => $value) {
            $out[strtolower((string) $key)] = $value;
        }

        return $out;
    }

    /** @return list<string> */
    private static function bookingColumns(): array
    {
        static $columns = null;
        if ($columns !== null) {
            return $columns;
        }

        $rows = DB::select(
            'SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            ['ferienwohnung_laravel', 'bookings']
        );
        $columns = [];
        foreach ($rows as $row) {
            $columns[] = strtolower((string) ($row->c ?? ''));
        }

        return $columns;
    }

    private static function storedAccent(string $key): ?string
    {
        self::ensureTable();
        $row = DB::table('ferienwohnung_laravel.guest_surface_colors')
            ->where('person_key', $key)
            ->first();
        $hex = self::normalizeHex($row->accent ?? '');

        return $hex !== '' ? $hex : null;
    }

    private static function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        DB::statement(
            'CREATE TABLE IF NOT EXISTS ferienwohnung_laravel.guest_surface_colors (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                person_key VARCHAR(191) NOT NULL,
                accent CHAR(7) NOT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY guest_surface_colors_person_key (person_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $ready = true;
    }
}
