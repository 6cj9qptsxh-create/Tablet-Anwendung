<?php

namespace App\Livewire; // Bei Livewire 2: namespace App\Http\Livewire;

use App\Services\MeteoblueForecast;
use Livewire\Component;

class Info extends Component
{
    public string $weatherPlace = 'lauterach';

    public function setWeatherPlace(string $id): void
    {
        if (! $this->knownWeatherPlace($id)) {
            return;
        }

        $this->weatherPlace = $id;
    }

    public function updatedWeatherPlace(string $id): void
    {
        if (! $this->knownWeatherPlace($id)) {
            $this->weatherPlace = 'lauterach';
        }
    }

    private function knownWeatherPlace(string $id): bool
    {
        $places = config('weather.places');

        return is_array($places) && isset($places[$id]);
    }

    public function render(MeteoblueForecast $weather)
    {
        $place = $weather->place($this->weatherPlace);
        $this->weatherPlace = $place['id'];

        return view('livewire.info', [
            'forecast' => $weather->forecast($place['id']),
            'weatherPlaces' => $weather->places(),
            'weatherPlace' => $place['id'],
            'weatherAsl' => $place['asl'],
            'infoOwner' => \App\Support\ClientNetwork::isFamily()
                && ! \App\Support\ClientNetwork::guestPreview(),
        ]);
    }
}