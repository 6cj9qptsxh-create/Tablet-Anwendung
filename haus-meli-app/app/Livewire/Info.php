<?php

namespace App\Livewire; // Bei Livewire 2: namespace App\Http\Livewire;

use App\Services\MeteoblueForecast;
use Livewire\Component;

class Info extends Component
{
    public string $weatherOrt = 'lauterach';

    public string $weatherZone = '';

    public function setWeatherOrt(string $ort): void
    {
        $this->applyWeather($ort, $this->weatherZone);
    }

    public function setWeatherZone(string $zone): void
    {
        $this->applyWeather($this->weatherOrt, $zone);
    }

    public function setWeatherChoice(string $ort, string $zone = ''): void
    {
        $this->applyWeather($ort, $zone);
    }

    public function render(MeteoblueForecast $weather)
    {
        $spot = $weather->resolve($this->weatherOrt, $this->weatherZone);
        $this->weatherOrt = $spot['ort'];
        $this->weatherZone = $spot['zone'];

        return view('livewire.info', [
            'forecast' => $weather->forecast($spot['ort'], $spot['zone']),
            'weatherOrts' => $weather->orts(),
            'weatherOrt' => $spot['ort'],
            'weatherZone' => $spot['zone'],
            'weatherZones' => $spot['zones'],
            'weatherAsl' => $spot['asl'],
            'infoOwner' => \App\Support\ClientNetwork::isFamily()
                && ! \App\Support\ClientNetwork::guestPreview(),
        ]);
    }

    private function applyWeather(string $ort, string $zone): void
    {
        $spot = app(MeteoblueForecast::class)->resolve($ort, $zone);
        $this->weatherOrt = $spot['ort'];
        $this->weatherZone = $spot['zone'];
    }
}
