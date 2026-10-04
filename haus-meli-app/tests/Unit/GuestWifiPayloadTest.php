<?php

namespace Tests\Unit;

use App\Support\GuestWifi;
use PHPUnit\Framework\TestCase;

class GuestWifiPayloadTest extends TestCase
{
    public function test_payload_uses_wpa_and_keeps_plain_values(): void
    {
        $this->assertSame(
            'WIFI:T:WPA;S:Haus Meli;P:geheim;;',
            GuestWifi::payload('Haus Meli', 'geheim')
        );
    }

    public function test_payload_escapes_wifi_specials(): void
    {
        $this->assertSame(
            'WIFI:T:WPA;S:Netz\\;werk;P:a\\\\b\\:c;;',
            GuestWifi::payload('Netz;werk', 'a\\b:c')
        );
    }
}
