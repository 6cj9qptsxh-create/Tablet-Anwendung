<?php

namespace App\Http\Controllers;

use App\Services\SavedToursStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedToursController extends Controller
{
    public function index(SavedToursStore $store): JsonResponse
    {
        return response()->json(['ok' => true, 'routes' => $store->all()]);
    }

    public function store(Request $request, SavedToursStore $store): JsonResponse
    {
        $data = $request->validate([
            'routes' => ['required', 'array', 'min:1', 'max:'.SavedToursStore::MAX],
            'routes.*.id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'routes.*.name' => ['nullable', 'string', 'max:120'],
            'routes.*.savedAt' => ['nullable', 'string', 'max:40'],
            'routes.*.signature' => ['nullable', 'string', 'max:4000'],
            'routes.*.plan' => ['nullable', 'array'],
            'routes.*.meta' => ['nullable', 'array'],
            'routes.*.route' => ['required', 'array'],
            'routes.*.route.startNodeId' => ['nullable', 'integer'],
            'routes.*.route.steps' => ['required', 'array', 'min:1', 'max:400'],
            'routes.*.route.steps.*' => ['array'],
            'routes.*.route.steps.*.segmentId' => ['required', 'integer'],
        ]);

        return response()->json(['ok' => true, 'routes' => $store->upsert($data['routes'])]);
    }

    public function destroy(string $id, SavedToursStore $store): JsonResponse
    {
        return response()->json(['ok' => true, 'routes' => $store->remove($id)]);
    }
}
