<?php

namespace Tests\Feature;

use App\Livewire\Info;
use Livewire\Livewire;
use Tests\TestCase;

class InfoWebcamsTest extends TestCase
{
    public function test_info_tab_embeds_surrounding_webcam_stills(): void
    {
        Livewire::test(Info::class)
            ->assertSee('Webcams')
            ->assertSee('Diasbahn Talstation')
            ->assertSee('Diasbahn Bergstation')
            ->assertSee('Medrig Alm')
            ->assertSee('Idalp')
            ->assertSee('1.180 m')
            ->assertSee('2.320 m')
            ->assertSee('104131839.jpg', false)
            ->assertSee('100178345.jpg', false)
            ->assertSee('webcam-diasbahn-talstation_webcam_104131839', false)
            ->assertSee('idalp_webcam_100178345', false)
            ->assertDontSee('webtv.feratel.com', false);
    }
}
