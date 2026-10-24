<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sprache für den laufenden Aufenthalt.
 * Die Homepage schreibt sie an die Buchung oder an diesen Aufenthalt.
 * Eine Wahl in der App gilt nur darüber und nur für diesen Aufenthalt.
 * Fehlt beides, bleibt die Sprache des Geräts.
 */
class GuestLang
{
    /** @var list<string> */
    private const BOOKING_COLUMNS = [
        'language', 'lang', 'locale', 'sprache',
        'guest_language', 'guest_lang', 'homepage_lang',
    ];

    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace('_', '-', $value);
        if ($value === '') {
            return '';
        }

        $word = match (true) {
            in_array($value, ['de', 'deu', 'ger', 'german', 'deutsch'], true) => 'de',
            in_array($value, ['en', 'eng', 'english', 'englisch'], true) => 'en',
            default => '',
        };
        if ($word !== '') {
            return $word;
        }

        $primary = explode('-', $value)[0];

        return $primary === 'de' || $primary === 'en' ? $primary : '';
    }

    /**
     * App-Wahl schlägt die Homepage. Die Spalte an der Buchung ist die Homepage,
     * wenn sie dort gespeichert wurde.
     *
     * @return array{lang: string, source: string}
     */
    public static function choose(?string $app, ?string $homepage, ?string $booking): array
    {
        $appLang = self::normalize((string) $app);
        if ($appLang !== '') {
            return ['lang' => $appLang, 'source' => 'app'];
        }

        $homeLang = self::normalize((string) $homepage);
        if ($homeLang !== '') {
            return ['lang' => $homeLang, 'source' => 'homepage'];
        }

        $bookingLang = self::normalize((string) $booking);
        if ($bookingLang !== '') {
            return ['lang' => $bookingLang, 'source' => 'booking'];
        }

        return ['lang' => '', 'source' => ''];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromBooking(array $row): string
    {
        foreach (self::BOOKING_COLUMNS as $column) {
            if (! array_key_exists($column, $row)) {
                continue;
            }
            $lang = self::normalize((string) $row[$column]);
            if ($lang !== '') {
                return $lang;
            }
        }

        return '';
    }

    /**
     * @param  array{key: string, from: string, until: string}|null  $stay
     * @return array{active: bool, lang: ?string, source: ?string, from: ?string, until: ?string, writable: bool}
     */
    public static function payloadFromStay(?array $stay, string $lang, string $source, bool $writable): array
    {
        if ($stay === null || ($stay['key'] ?? '') === '') {
            return self::emptyPayload();
        }

        $lang = self::normalize($lang);
        $source = $lang === '' ? '' : $source;

        return [
            'active' => true,
            'lang' => $lang !== '' ? $lang : null,
            'source' => $source !== '' ? $source : null,
            'from' => $stay['from'],
            'until' => $stay['until'],
            'writable' => $writable,
        ];
    }

    /** @return array{active: bool, lang: ?string, source: ?string, from: ?string, until: ?string, writable: bool} */
    public static function clientPayload(): array
    {
        try {
            if (! self::forGuests() || ClientNetwork::isDemo()) {
                return self::emptyPayload();
            }
            $stay = GuestAccent::openStay();
            if ($stay === null || $stay['key'] === '') {
                return self::emptyPayload();
            }
            $stored = self::stored($stay['key']);
            $chosen = self::choose(
                $stored['app'] ?? '',
                $stored['homepage'] ?? '',
                self::fromBooking($stay['row'] ?? [])
            );

            return self::payloadFromStay($stay, $chosen['lang'], $chosen['source'], true);
        } catch (Throwable $e) {
            return self::emptyPayload();
        }
    }

    public static function captureHomepage(?string $lang): bool
    {
        return self::remember((string) $lang, 'homepage');
    }

    public static function remember(string $lang, string $source = 'app'): bool
    {
        $lang = self::normalize($lang);
        if ($lang === '' || ! self::forGuests() || ClientNetwork::isDemo()) {
            return false;
        }

        $column = $source === 'homepage' ? 'homepage_lang' : 'app_lang';

        try {
            $stay = GuestAccent::openStay();
            if ($stay === null || $stay['key'] === '') {
                return false;
            }
            self::ensureTable();
            DB::table('ferienwohnung_laravel.guest_stay_languages')->updateOrInsert(
                ['person_key' => $stay['key']],
                [$column => $lang, 'updated_at' => now()]
            );

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array{active: bool, lang: null, source: null, from: null, until: null, writable: false} */
    private static function emptyPayload(): array
    {
        return [
            'active' => false,
            'lang' => null,
            'source' => null,
            'from' => null,
            'until' => null,
            'writable' => false,
        ];
    }

    private static function forGuests(): bool
    {
        return ! ClientNetwork::isFamily() || ClientNetwork::guestPreview();
    }

    /** @return array{app: string, homepage: string} */
    private static function stored(string $key): array
    {
        self::ensureTable();
        $row = DB::table('ferienwohnung_laravel.guest_stay_languages')
            ->where('person_key', $key)
            ->first();

        return [
            'app' => self::normalize((string) ($row->app_lang ?? '')),
            'homepage' => self::normalize((string) ($row->homepage_lang ?? '')),
        ];
    }

    private static function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        DB::statement(
            'CREATE TABLE IF NOT EXISTS ferienwohnung_laravel.guest_stay_languages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                person_key VARCHAR(191) NOT NULL,
                homepage_lang CHAR(2) NULL DEFAULT NULL,
                app_lang CHAR(2) NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY guest_stay_languages_person_key (person_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $ready = true;
    }
}
