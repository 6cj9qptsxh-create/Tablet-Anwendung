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
            'https://www.kappl-see.com/var/webcams/*' => Http::response('2026:10:22 07:40:00', 200, [
                'Last-Modified' => 'Thu, 22 Oct 2026 10:26:21 GMT',
            ]),
            'https://www.ischgl.com/de/webcam-archive*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $archive = (string) ($query['archive'] ?? '');
                if (str_contains($archive, 'idalp')) {
                    $images = [
                        '18:00' => '/var/webcams/archive/99396-idalp/2026-10/bild-22-18-00.jpg',
                        '19:00' => '/var/webcams/archive/99396-idalp/2026-10/bild-22-19-00.jpg',
                        '20:00' => '/var/webcams/archive/99396-idalp/2026-10/bild-22-20-00.jpg',
                    ];
                } else {
                    $images = [
                        '19:00' => '/var/webcams/archive/124696581-paznaun/2026-10/bild-22-19-00.jpg',
                        '20:00' => '/var/webcams/archive/124696581-paznaun/2026-10/bild-22-20-00.jpg',
                    ];
                }

                return Http::response(['success' => true, 'images' => $images], 200);
            },
            'https://www.ischgl.com/var/webcams/archive/*' => function ($request) {
                $url = $request->url();
                $older = str_contains($url, '18-00') || (str_contains($url, 'paznaun') && str_contains($url, '19-00'));

                return Http::response($older ? 'older-frame' : 'current-frame', 200);
            },
            'https://www.ischgl.com/var/webcams/*' => Http::response('current-frame', 200, [
                'Last-Modified' => 'Thu, 22 Oct 2026 08:00:00 GMT',
            ]),
            'https://panodata1.panomax.com/*' => Http::response('clip', 206, [
                'Last-Modified' => 'Thu, 22 Oct 2026 11:45:00 GMT',
            ]),
            'https://www.galtuer.com/var/webcams/*' => Http::response('', 200, [
                'Last-Modified' => 'Thu, 22 Oct 2026 10:26:21 GMT',
            ]),
            'https://webtv.feratel.com/*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $cam = str_pad((string) ($query['cam'] ?? '0'), 5, '0', STR_PAD_LEFT);
                $body = '<meta property="og:video" content="https://sts001.feratel.co.at/streams/stsstore002/1/'.$cam.'_testVid.mp4">';

                return Http::response($body, 200);
            },
            'https://sts001.feratel.co.at/*' => function ($request) {
                $url = $request->url();
                $utc = '2026-10-22 09:15:00';
                if (str_contains($url, '05585')) {
                    $utc = '2026-10-22 14:40:00';
                } elseif (str_contains($url, '05586')) {
                    $utc = '2026-10-22 12:10:00';
                } elseif (str_contains($url, '05548')) {
                    $utc = '2026-10-22 08:30:00';
                } elseif (str_contains($url, '05547')) {
                    $utc = '2026-10-22 15:05:00';
                } elseif (str_contains($url, '05558')) {
                    $utc = '2026-10-22 13:20:00';
                } elseif (str_contains($url, '05557')) {
                    $utc = '2026-10-22 11:05:00';
                }

                return Http::response('{"date": "'.$utc.'"}', 206);
            },
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
            ->assertDontSee('Diasbahn');

        $menu = $info->html();
        $pos = -1;
        foreach (['Lauterach', 'See', 'Kappl', 'Ischgl', 'Galtür'] as $label) {
            $at = strpos($menu, $label);
            $this->assertNotFalse($at, $label);
            $this->assertGreaterThan($pos, $at, $label);
            $pos = $at;
        }

        $info->call('setWeatherChoice', 'kappl', 'tal');

        $html = $info->html();
        $report = strpos($html, 'info-weather-loading');
        $cam = strpos($html, 'class="info-cam"');
        $this->assertNotFalse($report);
        $this->assertNotFalse($cam);
        $this->assertLessThan($cam, $report);

        $info
            ->assertSee('pauseCam()', false)
            ->assertSee('Diasbahn Talstation')
            ->assertSee('11:15 Uhr')
            ->assertDontSee('12:26 Uhr')
            ->assertSee('1.180 m')
            ->assertSee('104131839.jpg', false)
            ->assertSee('05589_testVid.mp4', false)
            ->assertDontSee('Diasbahn Bergstation')
            ->assertSee('Dias ·')
            ->assertSee('1830 m')
            ->assertSee('Alblittkopf ·')
            ->assertSee('2690 m')
            ->assertDontSee('Berg ·')
            ->assertDontSee('1900 m')
            ->assertDontSee('Medrig')
            ->call('setWeatherChoice', 'kappl', 'berg')
            ->assertSet('weatherZone', 'dias')
            ->assertSee('Diasbahn Bergstation')
            ->assertSee('16:40 Uhr')
            ->assertDontSee('11:15 Uhr')
            ->assertSee('1.830 m')
            ->assertSee('1830 m')
            ->assertSee('05585_testVid.mp4', false)
            ->assertDontSee('Diasbahn Talstation')
            ->assertDontSee('2.690 m')
            ->assertDontSee('05586_testVid.mp4', false)
            ->call('setWeatherChoice', 'kappl', 'alblittkopf')
            ->assertSee('Alblittkopf')
            ->assertSee('14:10 Uhr')
            ->assertSee('2.690 m')
            ->assertSee('2690 m')
            ->assertSee('104131833.jpg', false)
            ->assertSee('05586_testVid.mp4', false)
            ->assertDontSee('Diasbahn')
            ->call('setWeatherChoice', 'see', 'tal')
            ->assertSee('See Paznaun Tal')
            ->assertSee('Tal ·')
            ->assertSee('1056 m')
            ->assertSee('Medrig ·')
            ->assertSee('1802 m')
            ->assertSee('Furglerblick ·')
            ->assertSee('2570 m')
            ->assertSee('07:40 Uhr')
            ->assertDontSee('12:26 Uhr')
            ->assertSee('1.100 m')
            ->assertSee('104131847.jpg', false)
            ->assertDontSee('Mittelstation See')
            ->assertDontSee('testVid.mp4', false)
            ->assertDontSee('Nordseite')
            ->call('setWeatherChoice', 'see', 'berg')
            ->assertSet('weatherZone', 'medrig')
            ->assertSee('Mittelstation See')
            ->assertSee('15:20 Uhr')
            ->assertSee('1.810 m')
            ->assertSee('1802 m')
            ->assertSee('104131857.jpg', false)
            ->assertSee('05558_testVid.mp4', false)
            ->assertDontSee('See Paznaun Tal')
            ->assertDontSee('2.570 m')
            ->assertDontSee('Nordseite')
            ->call('setWeatherChoice', 'see', 'furglerblick')
            ->assertSee('Furglerblick')
            ->assertSee('13:05 Uhr')
            ->assertSee('2.570 m')
            ->assertSee('2570 m')
            ->assertSee('104131855.jpg', false)
            ->assertSee('05557_testVid.mp4', false)
            ->assertDontSee('Mittelstation See')
            ->assertDontSee('See Paznaun Tal')
            ->call('setWeatherChoice', 'ischgl', 'tal')
            ->assertSee('Ischgl Dorf')
            ->assertSee('1.400 m')
            ->assertSee('20:00 Uhr')
            ->assertSee('124696581.jpg', false)
            ->assertSee('Idalp ·')
            ->assertSee('2319 m')
            ->assertSee('Palinkopf ·')
            ->assertSee('2864 m')
            ->assertDontSee('Berg ·')
            ->assertDontSee('2.320 m')
            ->call('setWeatherChoice', 'ischgl', 'berg')
            ->assertSet('weatherZone', 'idalp')
            ->assertSee('Idalp')
            ->assertSee('19:00 Uhr')
            ->assertDontSee('20:00 Uhr')
            ->assertSee('2.320 m')
            ->assertSee('100178345.jpg', false)
            ->assertDontSee('Ischgl Dorf')
            ->assertDontSee('101_fullHD.mp4', false)
            ->call('setWeatherChoice', 'ischgl', 'palinkopf')
            ->assertSee('Palinkopf')
            ->assertSee('13:45 Uhr')
            ->assertSee('2.864 m')
            ->assertSee('2864 m')
            ->assertSee('100178319.jpg', false)
            ->assertSee('101_fullHD.mp4', false)
            ->assertDontSee('Ischgl Dorf')
            ->assertDontSee('100178345.jpg', false)
            ->call('setWeatherChoice', 'galtuer', 'tal')
            ->assertSee('Galtür')
            ->assertSee('Dorf auf galtuer.com', false)
            ->assertSee('1.600 m')
            ->assertSee('1584 m')
            ->assertSee('10:30 Uhr')
            ->assertDontSee('12:26 Uhr')
            ->assertSee('104131863.jpg', false)
            ->assertSee('05548_testVid.mp4', false)
            ->assertDontSee('Breitspitzbahn')
            ->call('setWeatherChoice', 'galtuer', 'berg')
            ->assertSee('Breitspitzbahn')
            ->assertSee('17:05 Uhr')
            ->assertDontSee('10:30 Uhr')
            ->assertSee('2.120 m')
            ->assertSee('2120 m')
            ->assertDontSee('2295 m')
            ->assertSee('104131861.jpg', false)
            ->assertSee('05547_testVid.mp4', false)
            ->assertDontSee('Dorf auf galtuer.com', false)
            ->call('setWeatherChoice', 'lauterach', 'home')
            ->assertDontSee('Idalp')
            ->assertDontSee('Nordseite')
            ->assertDontSee('Breitspitzbahn');
    }

    public function test_webcam_stamp_endpoint_returns_the_update_time(): void
    {
        $this->getJson('/info/webcam-stand?ort=kappl&zone=tal')
            ->assertOk()
            ->assertJson([
                'time' => '11:15 Uhr',
                'video' => 'https://sts001.feratel.co.at/streams/stsstore002/1/05589_testVid.mp4?dcsdesign=feratel4',
            ]);

        $this->getJson('/info/webcam-stand?ort=see&zone=tal')
            ->assertOk()
            ->assertJson([
                'time' => '07:40 Uhr',
                'video' => null,
            ]);

        $this->getJson('/info/webcam-stand?ort=see&zone=medrig')
            ->assertOk()
            ->assertJson([
                'time' => '15:20 Uhr',
                'video' => 'https://sts001.feratel.co.at/streams/stsstore002/1/05558_testVid.mp4?dcsdesign=feratel4',
            ]);

        $this->getJson('/info/webcam-stand?ort=see&zone=furglerblick')
            ->assertOk()
            ->assertJson([
                'time' => '13:05 Uhr',
                'video' => 'https://sts001.feratel.co.at/streams/stsstore002/1/05557_testVid.mp4?dcsdesign=feratel4',
            ]);

        $palinkopf = $this->getJson('/info/webcam-stand?ort=ischgl&zone=palinkopf');
        $palinkopf->assertOk()->assertJsonPath('time', '13:45 Uhr');
        $this->assertStringContainsString('101_fullHD.mp4?v=', (string) $palinkopf->json('video'));

        $this->getJson('/info/webcam-stand?ort=lauterach&zone=home')
            ->assertOk()
            ->assertJson([
                'time' => null,
                'video' => null,
            ]);
    }

    public function test_the_shell_pauses_the_webcam_outside_the_info_tab(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $info = file_get_contents(resource_path('views/livewire/info.blade.php'));

        $this->assertIsString($layout);
        $this->assertIsString($info);
        $this->assertStringContainsString("if (tab === 'info') return;", $layout);
        $this->assertStringContainsString(".info-cam-shot video", $layout);
        $this->assertStringContainsString("if (name !== 'wetter') this.pauseCam();", $info);
        $this->assertStringContainsString("window.__hausMeliTab !== 'info'", $info);
    }
}
