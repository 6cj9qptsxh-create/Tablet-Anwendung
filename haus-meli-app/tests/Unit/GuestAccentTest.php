<?php

namespace Tests\Unit;

use App\Support\GuestAccent;
use PHPUnit\Framework\TestCase;

class GuestAccentTest extends TestCase
{
    public function test_person_id_wins_over_the_name(): void
    {
        $key = GuestAccent::personKey(
            ['guest_id' => '7', 'name' => 'Anna Berger', 'id' => 3],
            ['id', 'guest_id', 'name', 'check_in', 'check_out']
        );

        $this->assertSame('guest_id:7', $key);
    }

    public function test_blank_person_id_falls_through_to_email(): void
    {
        $key = GuestAccent::personKey(
            ['guest_id' => '0', 'email' => 'Anna@Beispiel.at', 'id' => 3],
            ['id', 'guest_id', 'email']
        );

        $this->assertSame('email:anna@beispiel.at', $key);
    }

    public function test_name_pair_is_folded(): void
    {
        $key = GuestAccent::personKey(
            ['vorname' => 'Anna', 'nachname' => '  Berger  '],
            ['vorname', 'nachname']
        );

        $this->assertSame('name:anna berger', $key);
    }

    public function test_plain_name_when_nothing_stronger_exists(): void
    {
        $key = GuestAccent::personKey(
            ['name' => 'Anna   Berger'],
            ['id', 'name', 'check_in', 'check_out']
        );

        $this->assertSame('name:anna berger', $key);
    }

    public function test_booking_id_when_the_table_has_no_person_columns(): void
    {
        $key = GuestAccent::personKey(
            ['id' => 15, 'check_in' => '2026-10-01', 'check_out' => '2026-10-08'],
            ['id', 'check_in', 'check_out']
        );

        $this->assertSame('booking:15', $key);
    }

    public function test_blank_name_is_not_a_stored_person(): void
    {
        $row = ['id' => 15, 'name' => '   ', 'check_in' => '2026-10-19', 'check_out' => '2026-10-22'];
        $columns = ['id', 'name', 'check_in', 'check_out'];

        $this->assertSame('', GuestAccent::personKey($row, $columns));
        $this->assertSame('booking:15', GuestAccent::stayKey($row, $columns));
    }

    public function test_unnamed_guest_keeps_color_only_for_this_booking(): void
    {
        $stay = GuestAccent::pickStay([
            [
                'id' => 15,
                'name' => '',
                'check_in' => '2026-10-19',
                'check_out' => '2026-10-22',
            ],
        ], ['id', 'name', 'check_in', 'check_out'], '2026-10-19');

        $this->assertSame([
            'key' => 'booking:15',
            'from' => '2026-10-19',
            'until' => '2026-10-22',
        ], $stay);
    }

    public function test_stay_is_inclusive_and_prefers_a_named_guest(): void
    {
        $stay = GuestAccent::pickStay([
            [
                'id' => 1,
                'name' => '',
                'check_in' => '2026-10-01',
                'check_out' => '2026-10-20',
            ],
            [
                'id' => 2,
                'name' => 'Anna Berger',
                'check_in' => '2026-10-19 15:00:00',
                'check_out' => '2026-10-22',
            ],
            [
                'id' => 9,
                'name' => 'Zu früh',
                'check_in' => '2026-11-01',
                'check_out' => '2026-11-08',
            ],
        ], ['id', 'name', 'check_in', 'check_out'], '2026-10-19');

        $this->assertSame([
            'key' => 'name:anna berger',
            'from' => '2026-10-19',
            'until' => '2026-10-22',
        ], $stay);
        $this->assertTrue(GuestAccent::coversDate('2026-10-19', '2026-10-22', '2026-10-22'));
        $this->assertFalse(GuestAccent::coversDate('2026-10-19', '2026-10-22', '2026-10-23'));
    }

    public function test_payload_keeps_the_field_empty_until_a_color_is_saved(): void
    {
        $stay = ['key' => 'guest_id:7', 'from' => '2026-10-19', 'until' => '2026-10-22'];

        $this->assertSame([
            'active' => true,
            'accent' => null,
            'from' => '2026-10-19',
            'until' => '2026-10-22',
            'writable' => true,
        ], GuestAccent::payloadFromStay($stay, null, true));

        $this->assertFalse(GuestAccent::payloadFromStay($stay, null, false)['writable']);
        $this->assertSame('#d3e2ff', GuestAccent::payloadFromStay($stay, '#D3E2FF', true)['accent']);
        $this->assertNull(GuestAccent::payloadFromStay($stay, 'rot', true)['accent']);
        $this->assertTrue(GuestAccent::payloadFromStay($stay, 'rot', true)['active']);
        $this->assertFalse(GuestAccent::payloadFromStay(['key' => '', 'from' => '2026-10-19', 'until' => '2026-10-22'], '#112233', true)['active']);
        $this->assertSame('#d3e2ff', GuestAccent::GUEST_STANDARD);
    }

    public function test_hex_must_be_six_digits(): void
    {
        $this->assertSame('#660000', GuestAccent::normalizeHex('#660000'));
        $this->assertSame('#aabbcc', GuestAccent::normalizeHex('  #AABBCC '));
        $this->assertSame('', GuestAccent::normalizeHex('#fff'));
        $this->assertSame('', GuestAccent::normalizeHex('#6600000'));
    }
}
