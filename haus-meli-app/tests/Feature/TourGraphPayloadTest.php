<?php

namespace Tests\Feature;

use Tests\TestCase;

class TourGraphPayloadTest extends TestCase
{
    public function test_segment_list_omits_coordinates(): void
    {
        $response = $this->getJson('/tours/graph');

        $response->assertOk();
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $response->assertJsonPath('geometry', 'separate');

        foreach ($response->json('segments') ?? [] as $segment) {
            $coords = $segment['geojson']['coordinates'] ?? null;
            $this->assertSame([], $coords);
        }
    }

    public function test_geometry_endpoint_is_cached_separately(): void
    {
        $response = $this->getJson('/tours/graph/geometry');

        $response->assertOk();
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertIsArray($response->json('segments'));
    }
}
