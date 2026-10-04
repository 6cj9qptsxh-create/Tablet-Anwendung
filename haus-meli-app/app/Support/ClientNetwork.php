<?php

namespace App\Support;

class ClientNetwork
{
    public static function isFamily(): bool
    {
        $ips = self::candidateIps();

        foreach ($ips as $ip) {
            if (self::matchesEnvPrefixes($ip, env('GUEST_IP_PREFIX', '192.168.20.'))) {
                return false;
            }
        }

        foreach ($ips as $ip) {
            if (self::matchesEnvPrefixes($ip, env('OWNER_IP_PREFIX', '192.168.1.'))) {
                return true;
            }
            if (self::isTailscale($ip)) {
                return true;
            }
        }

        // Synology + Tailscale: Verbindung kommt intern als 127.0.0.1 an.
        // Familie nur, wenn die URL die Tailscale-Adresse ist — nicht QuickConnect.
        if (self::isTailscaleHost()) {
            return true;
        }

        return false;
    }

    /** Familien-Gerät schaut sich die Gast-Oberfläche an, ohne Gast-Daten. */
    public static function guestPreview(): bool
    {
        if (! self::isFamily()) {
            return false;
        }

        return (bool) session('guest_preview', false);
    }

    /** @return list<string> */
    public static function candidateIps(): array
    {
        $raw = [];
        $raw[] = (string) (request()->ip() ?? '');
        foreach (request()->ips() as $ip) {
            $raw[] = (string) $ip;
        }
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $val = (string) (request()->server($key) ?? '');
            foreach (preg_split('/\s*,\s*/', $val) ?: [] as $part) {
                $raw[] = $part;
            }
        }

        $out = [];
        foreach ($raw as $ip) {
            $ip = self::normalizeIp($ip);
            if ($ip !== '' && ! in_array($ip, $out, true)) {
                $out[] = $ip;
            }
        }

        return $out;
    }

    public static function debug(): array
    {
        return [
            'family' => self::isFamily(),
            'guest_preview' => self::guestPreview(),
            'ips' => self::candidateIps(),
            'laravel_ip' => request()->ip(),
            'host' => request()->getHost(),
        ];
    }

    private static function normalizeIp(string $ip): string
    {
        $ip = strtolower(trim($ip));
        if ($ip === '') {
            return '';
        }
        if (str_starts_with($ip, '::ffff:')) {
            $ip = substr($ip, 7);
        }

        return $ip;
    }

    private static function matchesEnvPrefixes(string $ip, mixed $prefixes): bool
    {
        foreach (array_filter(array_map('trim', explode(',', (string) $prefixes))) as $prefix) {
            if ($prefix !== '' && str_starts_with($ip, strtolower($prefix))) {
                return true;
            }
        }

        return false;
    }

    private static function isTailscaleHost(): bool
    {
        $host = strtolower(trim((string) request()->getHost()));
        if ($host === '') {
            return false;
        }
        if (str_ends_with($host, '.ts.net')) {
            return true;
        }
        $ip = self::normalizeIp($host);

        return $ip !== '' && self::isTailscale($ip);
    }

    private static function isTailscale(string $ip): bool
    {
        if (str_contains($ip, ':')) {
            return self::ipInCidr($ip, 'fd7a:115c:a1e0::/48');
        }

        return self::ipInCidr($ip, '100.64.0.0/10');
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipBin = inet_pton($ip);
        $subBin = inet_pton($subnet);
        if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
            return false;
        }
        $bits = (int) $bits;
        $full = intdiv($bits, 8);
        $rest = $bits % 8;
        if ($full > 0 && substr($ipBin, 0, $full) !== substr($subBin, 0, $full)) {
            return false;
        }
        if ($rest === 0) {
            return true;
        }
        $mask = (~((1 << (8 - $rest)) - 1)) & 0xFF;

        return (ord($ipBin[$full]) & $mask) === (ord($subBin[$full]) & $mask);
    }
}
