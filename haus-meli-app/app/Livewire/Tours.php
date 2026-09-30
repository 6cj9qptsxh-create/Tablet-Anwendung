<?php

namespace App\Livewire;

use App\Models\TourNode;
use App\Models\TourSegment;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class Tours extends Component
{
    public function render()
    {
        $nodes = collect();
        $segments = collect();

        if (Schema::hasTable('tour_nodes') && Schema::hasTable('tour_segments')) {
            $nodes = TourNode::query()
                ->with('images')
                ->orderBy('name')
                ->get()
                ->map(fn (TourNode $n) => $n->toGuestArray())
                ->values();

            $segments = TourSegment::query()
                ->with(['modeProfiles', 'images', 'fromNode', 'toNode'])
                ->orderBy('name')
                ->get()
                ->filter(fn (TourSegment $s) => count($s->coordinates()) >= 2)
                ->map(fn (TourSegment $s) => $s->toGuestArray())
                ->values();
        }

        return view('livewire.tours', [
            'graph' => [
                'nodes' => $nodes,
                'segments' => $segments,
                'profile_modes' => config('tours.profile_modes', [
                    'hike' => 'Wandern',
                    'bike' => 'Rad',
                    'ebike' => 'E-Bike',
                ]),
                'start_kinds' => TourNode::START_KINDS,
            ],
        ]);
    }
}
