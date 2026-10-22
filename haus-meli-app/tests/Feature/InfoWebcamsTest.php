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
            'https://www.galtuer.com/var/webcams/*' => Http::response('', 200, [
                'Last-Modified' => 'Thu, 22 Oct 2026 10:26:21 GMT',
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
        $info = Livewire::test(Info::class)
            ->assertDontSee('Webcams')
            ->assertDontSee('Livebilder')
            ->assertDontSee('Diasbahn')
            ->call('setWeatherChoice', 'kappl', 'tal');

        $html = $info->html();
        $report = strpos($html, 'info-weather-loading');
        $cam = strpos($html, 'class="info-cam"');
        $this->assertNotFalse($report);
        $this->assertNotFalse($cam);
        $this->assertLessThan($cam, $report);

        $info
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
            ->call('setWeatherChoice', 'see', 'tal')
            ->assertSee('Valley')
            ->assertSee('1.100 m')
            ->assertSee('104131847.jpg', false)
            ->assertDontSee('Nordseite')
            ->call('setWeatherChoice', 'see', 'berg')
            ->assertSee('Nordseite')
            ->assertSee('2.160 m')
            ->assertSee('104131843.jpg', false)
            ->assertDontSee('104131857', false)
            ->assertDontSee('Valley')
            ->call('setWeatherChoice', 'ischgl', 'tal')
            ->assertSee('Ischgl Dorf')
            ->assertSee('1.400 m')
            ->assertSee('10:00 Uhr')
            ->assertSee('124696581.jpg', false)
            ->assertDontSee('Idalp')
            ->call('setWeatherChoice', 'ischgl', 'berg')
            ->assertSee('Idalp')
            ->assertSee('100178345.jpg', false)
            ->assertDontSee('Ischgl Dorf')
            ->call('setWeatherChoice', 'galtuer', 'tal')
            ->assertSee('Galtür')
            ->assertSee('Webcam Dorf, Galtür', false)
            ->assertSee('1.600 m')
            ->assertSee('1584 m')
            ->assertSee('12:26 Uhr')
            ->assertSee('104131863.jpg', false)
            ->assertDontSee('Breitspitzbahn')
            ->call('setWeatherChoice', 'galtuer', 'berg')
            ->assertSee('Breitspitzbahn')
            ->assertSee('2.120 m')
            ->assertSee('2295 m')
            ->assertSee('104131861.jpg', false)
            ->assertDontSee('Webcam Dorf, Galtür', false)
            ->call('setWeatherChoice', 'lauterach', 'home')
            ->assertDontSee('Idalp')
            ->assertDontSee('Nordseite')
            ->assertDontSee('Breitspitzbahn');
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
