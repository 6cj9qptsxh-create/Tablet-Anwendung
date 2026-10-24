<?php

namespace Tests\Unit;

use App\Support\GuestLang;
use PHPUnit\Framework\TestCase;

class GuestLangTest extends TestCase
{
    public function test_normalize_accepts_homepage_spellings(): void
    {
        $this->assertSame('de', GuestLang::normalize(' DE-AT '));
        $this->assertSame('de', GuestLang::normalize('deutsch'));
        $this->assertSame('en', GuestLang::normalize('en_GB'));
        $this->assertSame('en', GuestLang::normalize('English'));
        $this->assertSame('', GuestLang::normalize('fr'));
        $this->assertSame('', GuestLang::normalize(''));
    }

    public function test_app_choice_overrides_homepage_and_booking(): void
    {
        $this->assertSame(
            ['lang' => 'en', 'source' => 'app'],
            GuestLang::choose('en', 'de', 'de')
        );
    }

    public function test_homepage_store_overrides_the_booking_column(): void
    {
        $this->assertSame(
            ['lang' => 'en', 'source' => 'homepage'],
            GuestLang::choose('', 'en', 'de')
        );
    }

    public function test_booking_column_is_the_homepage_when_nothing_else_is_stored(): void
    {
        $this->assertSame(
            ['lang' => 'en', 'source' => 'booking'],
            GuestLang::choose(null, '', 'en-US')
        );
        $this->assertSame(
            ['lang' => '', 'source' => ''],
            GuestLang::choose('', '', 'fr')
        );
    }

    public function test_booking_row_reads_known_language_columns(): void
    {
        $this->assertSame('en', GuestLang::fromBooking(['locale' => 'de_AT', 'lang' => 'en']));
        $this->assertSame('de', GuestLang::fromBooking(['locale' => 'de_AT']));
        $this->assertSame('en', GuestLang::fromBooking(['sprache' => 'englisch']));
        $this->assertSame('', GuestLang::fromBooking(['name' => 'Anna']));
    }

    public function test_payload_is_inactive_without_a_stay(): void
    {
        $stay = ['key' => 'guest_id:7', 'from' => '2026-10-19', 'until' => '2026-10-22'];

        $this->assertFalse(GuestLang::payloadFromStay(null, 'en', 'app', true)['active']);
        $this->assertSame([
            'active' => true,
            'lang' => 'en',
            'source' => 'homepage',
            'from' => '2026-10-19',
            'until' => '2026-10-22',
            'writable' => true,
        ], GuestLang::payloadFromStay($stay, 'en-GB', 'homepage', true));
        $this->assertNull(GuestLang::payloadFromStay($stay, '', 'app', true)['lang']);
        $this->assertNull(GuestLang::payloadFromStay($stay, '', 'app', true)['source']);
        $this->assertFalse(GuestLang::payloadFromStay($stay, 'de', 'app', false)['writable']);
    }
}
