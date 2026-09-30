<?php

namespace App\Http\Controllers;

use App\Models\TripVariant;
use App\Services\GpxService;
use Symfony\Component\HttpFoundation\Response;

class TripGpxController extends Controller
{
    public function __invoke(TripVariant $variant, GpxService $gpx): Response
    {
        $variant->load(['segments', 'trip']);

        if (! $variant->trip || ! $variant->trip->is_active) {
            abort(404);
        }

        if ($variant->segments->isEmpty()) {
            abort(404, 'Keine Segmente für diese Variante.');
        }

        $content = $gpx->combineVariantToGpx($variant);
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $variant->name) ?: 'tour';

        return response($content, 200, [
            'Content-Type' => 'application/gpx+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="trip-'.$variant->trip_id.'-'.$slug.'.gpx"',
        ]);
    }
}
