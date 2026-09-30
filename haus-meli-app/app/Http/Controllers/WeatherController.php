<?php

namespace App\Http\Controllers;

use App\Services\MeteoblueForecast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function show(MeteoblueForecast $weather): JsonResponse
    {
        return response()->json($weather->header());
    }

    public function terminal(Request $request, MeteoblueForecast $weather): JsonResponse
    {
        $expected = (string) env('E1001_AGENDA_TOKEN', '');
        $got = (string) ($request->bearerToken() ?: $request->query('token', ''));
        if ($expected !== '' && ! hash_equals($expected, $got)) {
            abort(403, 'Forbidden');
        }

        return response()->json($weather->terminal());
    }
}
