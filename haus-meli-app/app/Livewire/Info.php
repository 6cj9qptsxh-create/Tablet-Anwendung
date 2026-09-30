<?php

namespace App\Livewire; // Bei Livewire 2: namespace App\Http\Livewire;

use App\Services\MeteoblueForecast;
use Livewire\Component;

class Info extends Component
{
    public function render(MeteoblueForecast $weather)
    {
        return view('livewire.info', [
            'forecast' => $weather->forecast(),
        ]);
    }
}