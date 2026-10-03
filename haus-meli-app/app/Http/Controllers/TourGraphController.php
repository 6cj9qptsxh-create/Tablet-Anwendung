<?php

namespace App\Http\Controllers;

use App\Services\TourGraph;
use Illuminate\Http\JsonResponse;

class TourGraphController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(TourGraph::payload());
    }
}
