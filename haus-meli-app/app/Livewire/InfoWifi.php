<?php

namespace App\Livewire;

use App\Support\GuestWifi;
use Livewire\Component;

class InfoWifi extends Component
{
    public function render()
    {
        return view('livewire.info-wifi', [
            'wifi' => GuestWifi::current(),
        ]);
    }
}
