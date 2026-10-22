<?php

namespace App\Livewire;

use App\Support\GuestWifi;
use Livewire\Component;

class InfoWifi extends Component
{
    /** Bleibt über Livewire-Updates erhalten, auch wenn der Folge-Request die Demo nicht mehr nennt. */
    public bool $demoMode = false;

    public function mount(): void
    {
        $this->captureDemoMode();
    }

    public function hydrate(): void
    {
        $this->captureDemoMode();
    }

    private function captureDemoMode(): void
    {
        if (\App\Support\ClientNetwork::isDemo()) {
            $this->demoMode = true;
        }
    }

    public function render()
    {
        $wifi = $this->demoMode
            ? [
                'ready' => false,
                'ssid' => '',
                'password' => '',
                'payload' => '',
                'qr' => '',
                'note' => 'In der Demo gibt es kein WLAN.',
            ]
            : GuestWifi::current();

        return view('livewire.info-wifi', [
            'wifi' => $wifi,
        ]);
    }
}
