<?php

namespace App\Services;

use App\Models\TourNode;
use App\Models\TourSegment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class TourGraph
{
    public static function payload(): array
    {
        return Cache::remember('tour_graph_guest_v2', 600, function () {
            return self::buildPayload(false);
        });
    }

    public static function geometry(): array
    {
        return Cache::remember('tour_graph_geometry_v2', 600, function () {
            if (! Schema::hasTable('tour_segments')) {
                return ['segments' => []];
            }

            $segments = TourSegment::query()
                ->orderBy('id')
                ->get(['id', 'geojson'])
                ->filter(fn (TourSegment $s) => count($s->coordinates()) >= 2)
                ->map(fn (TourSegment $s) => [
                    'id' => $s->id,
                    'coordinates' => $s->coordinates(false),
                ])
                ->values();

            return ['segments' => $segments];
        });
    }

    private static function buildPayload(bool $withGeometry): array
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
                ->map(fn (TourSegment $s) => $s->toGuestArray($withGeometry))
                ->values();
        }

        return [
            'nodes' => $nodes,
            'segments' => $segments,
            'geometry' => $withGeometry ? 'inline' : 'separate',
            'profile_modes' => config('tours.profile_modes', [
                'hike' => 'Wandern',
                'bike' => 'Rad',
                'ebike' => 'E-Bike',
            ]),
            'start_kinds' => TourNode::START_KINDS,
        ];
    }
}
