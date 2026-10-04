<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/** Aktuelles Gast-WLAN aus ferienwohnung_laravel.guest_wifi, ohne Cache. */
class GuestWifi
{
    /** @return array{ready: bool, ssid: string, password: string, payload: string, qr: string} */
    public static function current(): array
    {
        try {
            $row = DB::table('ferienwohnung_laravel.guest_wifi')
                ->orderByRaw("COALESCE(updated_at, '1970-01-01 00:00:00') DESC")
                ->orderByDesc('id')
                ->first();
        } catch (Throwable $e) {
            return self::empty();
        }

        if ($row === null) {
            return self::empty();
        }

        $ssid = trim((string) ($row->ssid ?? ''));
        $password = (string) ($row->password ?? '');
        if ($ssid === '' || $password === '') {
            return self::empty();
        }

        $payload = self::payload($ssid, $password);

        return [
            'ready' => true,
            'ssid' => $ssid,
            'password' => $password,
            'payload' => $payload,
            'qr' => 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data='.rawurlencode($payload),
        ];
    }

    public static function payload(string $ssid, string $password): string
    {
        return 'WIFI:T:WPA;S:'.self::escape($ssid).';P:'.self::escape($password).';;';
    }

    /** Sonderzeichen nach der WIFI-QR-Vorgabe schützen. */
    public static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', '"', ':'],
            ['\\\\', '\\;', '\\,', '\\"', '\\:'],
            $value
        );
    }

    /** @return array{ready: bool, ssid: string, password: string, payload: string, qr: string} */
    private static function empty(): array
    {
        return [
            'ready' => false,
            'ssid' => '',
            'password' => '',
            'payload' => '',
            'qr' => '',
        ];
    }
}
