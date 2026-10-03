<?php

namespace App\Http\Controllers;

use App\Services\TourGraph;
use Illuminate\Http\JsonResponse;

class TourGraphController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(TourGraph::payload())
            ->header('Cache-Control', 'private, max-age=300');
    }

    public function geometry(): JsonResponse
    {
        return response()
            ->json(TourGraph::geometry())
            ->header('Cache-Control', 'private, max-age=300');
    }
}
