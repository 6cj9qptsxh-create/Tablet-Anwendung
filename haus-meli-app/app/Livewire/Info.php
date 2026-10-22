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
            'webcams' => $this->webcams(),
        ]);
    }

    /**
     * @return list<array{name: string, place: string, asl: int|null, page: string, image: string, embed: string, source: string}>
     */
    private function webcams(): array
    {
        $cams = config('webcams', []);
        if (! is_array($cams)) {
            return [];
        }

        $out = [];
        foreach ($cams as $cam) {
            if (! is_array($cam)) {
                continue;
            }
            $name = trim((string) ($cam['name'] ?? ''));
            $page = trim((string) ($cam['page'] ?? ''));
            if ($name === '' || $page === '') {
                continue;
            }
            $host = (string) parse_url($page, PHP_URL_HOST);
            $host = preg_replace('/^www\./', '', $host) ?: '';
            $asl = $cam['asl'] ?? null;
            $out[] = [
                'name' => $name,
                'place' => trim((string) ($cam['place'] ?? '')),
                'asl' => is_numeric($asl) ? (int) $asl : null,
                'page' => $page,
                'image' => trim((string) ($cam['image'] ?? '')),
                'embed' => trim((string) ($cam['embed'] ?? '')),
                'source' => $host,
            ];
        }

        return $out;
    }

    private function applyWeather(string $ort, string $zone): void
    {
        $spot = app(MeteoblueForecast::class)->resolve($ort, $zone);
        $this->weatherOrt = $spot['ort'];
        $this->weatherZone = $spot['zone'];
    }
}
