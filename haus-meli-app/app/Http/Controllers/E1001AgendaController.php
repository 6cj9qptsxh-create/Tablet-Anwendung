<?php

namespace App\Http\Controllers;

use App\Services\FamilyAgendaFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class E1001AgendaController extends Controller
{
    public function __invoke(Request $request, FamilyAgendaFeed $feed): JsonResponse
    {
        $expected = (string) env('E1001_AGENDA_TOKEN', '');
        $got = (string) ($request->bearerToken() ?: $request->query('token', ''));
        if ($expected !== '' && ! hash_equals($expected, $got)) {
            abort(403, 'Forbidden');
        }

        $days = max(1, min(60, (int) $request->query('days', 21)));
        $limit = max(1, min(30, (int) $request->query('limit', 14)));

        return response()->json($feed->forRange($days, $limit));
    }
}
