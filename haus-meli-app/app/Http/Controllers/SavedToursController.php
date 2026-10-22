<?php

namespace App\Http\Controllers;

use App\Services\SavedToursStore;
use App\Support\ClientNetwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedToursController extends Controller
{
    public function index(SavedToursStore $store): JsonResponse
    {
        if (ClientNetwork::isDemo()) {
            return response()->json(['ok' => true, 'routes' => $this->demoRoutes()]);
        }

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

        if (ClientNetwork::isDemo()) {
            $routes = SavedToursStore::merge($this->demoRoutes(), $data['routes']);
            session(['demo.saved_tours' => $routes]);

            return response()->json(['ok' => true, 'routes' => $routes]);
        }

        return response()->json(['ok' => true, 'routes' => $store->upsert($data['routes'])]);
    }

    public function destroy(string $id, SavedToursStore $store): JsonResponse
    {
        if (ClientNetwork::isDemo()) {
            $routes = SavedToursStore::without($this->demoRoutes(), $id);
            session(['demo.saved_tours' => $routes]);

            return response()->json(['ok' => true, 'routes' => $routes]);
        }

        return response()->json(['ok' => true, 'routes' => $store->remove($id)]);
    }

    /** @return list<array<string, mixed>> */
    private function demoRoutes(): array
    {
        $routes = session('demo.saved_tours', []);

        return is_array($routes) ? array_values(array_filter($routes, 'is_array')) : [];
    }
}
