<?php

namespace Tests\Feature;

use App\Livewire\Info;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class InfoWebcamsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', 'Europe/Vienna'));
        Http::fake([
            'https://www.kappl-see.com/var/webcams/*' => Http::response('', 200, [
                'Last-Modified' => 'Thu, 22 Oct 2026 10:26:21 GMT',
            ]),
            'https://www.ischgl.com/var/webcams/*' => Http::response('', 200, [
                'Last-Modified' => 'Thu, 22 Oct 2026 08:00:00 GMT',
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_weather_shows_the_camera_for_the_selected_place_and_height(): void
    {
        Livewire::test(Info::class)
            ->assertDontSee('Webcams')
            ->assertDontSee('Livebilder')
            ->assertDontSee('Diasbahn')
            ->call('setWeatherChoice', 'kappl', 'tal')
            ->assertSee('Diasbahn Talstation')
            ->assertSee('12:26 Uhr')
            ->assertSee('1.180 m')
            ->assertSee('104131839.jpg', false)
            ->assertDontSee('Diasbahn Bergstation')
            ->assertDontSee('Medrig')
            ->call('setWeatherChoice', 'kappl', 'berg')
            ->assertSee('Diasbahn Bergstation')
            ->assertSee('1.830 m')
            ->assertDontSee('Diasbahn Talstation')
            ->call('setWeatherChoice', 'see', 'berg')
            ->assertSee('Nordseite')
            ->assertSee('2.160 m')
            ->assertSee('104131843.jpg', false)
            ->assertDontSee('104131857', false)
            ->call('setWeatherChoice', 'ischgl', 'berg')
            ->assertSee('Idalp')
            ->assertSee('10:00 Uhr')
            ->assertSee('100178345.jpg', false)
            ->call('setWeatherChoice', 'lauterach', 'home')
            ->assertDontSee('Idalp')
            ->assertDontSee('Nordseite');
    }

    public function test_webcam_stamp_endpoint_returns_the_update_time(): void
    {
        $this->getJson('/info/webcam-stand?ort=kappl&zone=tal')
            ->assertOk()
            ->assertJson(['time' => '12:26 Uhr']);

        $this->getJson('/info/webcam-stand?ort=lauterach&zone=home')
            ->assertOk()
            ->assertJson(['time' => null]);
    }
}
