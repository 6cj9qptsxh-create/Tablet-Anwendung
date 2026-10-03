<?php

namespace Tests\Unit;

use App\Models\TourSegment;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TourSegmentGuestArrayTest extends TestCase
{
    public function test_guest_array_can_omit_line_coordinates(): void
    {
        $segment = new TourSegment([
            'name' => 'Testgrat',
            'from_node_id' => 1,
            'to_node_id' => 2,
            'distance_km' => 1.2,
            'elevation_m' => 80,
            'geojson' => [
                'type' => 'LineString',
                'coordinates' => [[10.0, 47.0], [10.1, 47.1]],
            ],
        ]);
        $segment->setRelation('modeProfiles', new Collection());
        $segment->setRelation('images', new Collection());
        $segment->setRelation('fromNode', null);
        $segment->setRelation('toNode', null);

        $thin = $segment->toGuestArray(false);
        $full = $segment->toGuestArray(true);

        $this->assertSame([], $thin['geojson']['coordinates']);
        $this->assertSame('LineString', $thin['geojson']['type']);
        $this->assertCount(2, $full['geojson']['coordinates']);
    }
}
