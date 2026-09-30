<?php

namespace App\Http\Controllers;

use App\Models\TourNode;
use App\Models\TourSegment;
use App\Services\GpxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PlannedRouteShareController extends Controller
{
    private const CACHE_PREFIX = 'planned_route:';

    private const TTL_HOURS = 48;

    public function storeInfo(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'method' => 'POST',
            'path' => '/tours/planned/share',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'startNodeId' => ['nullable', 'integer'],
            'steps' => ['required', 'array', 'min:1', 'max:400'],
            'steps.*.segmentId' => ['required', 'integer'],
            'steps.*.reversed' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array'],
            'meta.title' => ['nullable', 'string', 'max:120'],
            'meta.km' => ['nullable', 'numeric'],
            'meta.hm' => ['nullable', 'numeric'],
            'meta.duration_min' => ['nullable', 'numeric'],
            'meta.stages' => ['nullable', 'array', 'max:80'],
            'meta.stages.*' => ['nullable', 'string', 'max:120'],
        ]);

        $steps = array_map(static function (array $step): array {
            return [
                'segmentId' => (int) $step['segmentId'],
                'reversed' => (bool) ($step['reversed'] ?? false),
            ];
        }, $data['steps']);

        $meta = $data['meta'] ?? [];
        $meta['hm'] = isset($meta['hm']) ? (int) round((float) $meta['hm']) : null;
        $meta['duration_min'] = isset($meta['duration_min'])
            ? (int) round((float) $meta['duration_min'])
            : null;
        $meta['stages'] = array_values(array_filter(
            array_map(static fn ($s) => is_string($s) ? trim($s) : '', $meta['stages'] ?? []),
            static fn ($s) => $s !== ''
        ));
        if ($meta['stages'] === []) {
            $meta['stages'] = $this->buildStages(
                isset($data['startNodeId']) ? (int) $data['startNodeId'] : null,
                $steps
            );
        }
        if (empty($meta['title'])) {
            $meta['title'] = 'Haus Meli Tour';
        }

        $payload = [
            'startNodeId' => isset($data['startNodeId']) ? (int) $data['startNodeId'] : null,
            'steps' => $steps,
            'meta' => $meta,
            'created_at' => now()->toIso8601String(),
        ];

        $token = Str::lower(Str::random(32));
        Cache::store('file')->put(self::CACHE_PREFIX.$token, $payload, now()->addHours(self::TTL_HOURS));

        $base = rtrim($request->getSchemeAndHttpHost(), '/');

        return response()->json([
            'ok' => true,
            'token' => $token,
            'share_url' => $base.'/tours/share/'.$token,
            'gpx_url' => $base.'/tours/share/'.$token.'/gpx',
            'expires_hours' => self::TTL_HOURS,
        ]);
    }

    public function show(string $token, GpxService $gpx): View
    {
        $payload = $this->payloadOrAbort($token);
        $track = [];
        try {
            $track = $gpx->plannedStepsToLatLngs($payload['steps'] ?? []);
        } catch (\Throwable $e) {
            $track = [];
        }

        return view('tours.share', [
            'token' => $token,
            'meta' => $payload['meta'] ?? [],
            'gpxUrl' => route('tours.share.gpx', ['token' => $token]),
            'expiresHours' => self::TTL_HOURS,
            'track' => $track,
        ]);
    }

    public function gpx(string $token, GpxService $gpx): Response
    {
        $payload = $this->payloadOrAbort($token);
        $title = (string) (($payload['meta']['title'] ?? null) ?: 'Haus Meli Tour');
        try {
            $content = $gpx->combinePlannedStepsToGpx($payload['steps'] ?? [], $title);
        } catch (\Throwable $e) {
            abort(422, 'GPX konnte nicht erzeugt werden: '.$e->getMessage());
        }
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $title) ?: 'tour';

        return response($content, 200, [
            'Content-Type' => 'application/gpx+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="haus-meli-'.$slug.'.gpx"',
        ]);
    }

    /** @return array{startNodeId: ?int, steps: list<array>, meta: array} */
    private function payloadOrAbort(string $token): array
    {
        if (! preg_match('/^[a-z0-9]{16,64}$/i', $token)) {
            abort(404);
        }
        $payload = Cache::store('file')->get(self::CACHE_PREFIX.$token);
        if (! is_array($payload) || empty($payload['steps'])) {
            // Fallback: früherer Default-Store
            $payload = Cache::get(self::CACHE_PREFIX.$token);
        }
        if (! is_array($payload) || empty($payload['steps'])) {
            abort(404, 'Dieser Link ist abgelaufen oder ungültig.');
        }

        return $payload;
    }

    /**
     * @param  list<array{segmentId: int, reversed: bool}>  $steps
     * @return list<string>
     */
    private function buildStages(?int $startNodeId, array $steps): array
    {
        $segIds = array_map(static fn (array $s) => $s['segmentId'], $steps);
        $segments = TourSegment::query()->whereIn('id', $segIds)->get()->keyBy('id');
        $nodeIds = [];
        if ($startNodeId) {
            $nodeIds[] = $startNodeId;
        }
        foreach ($steps as $step) {
            $seg = $segments->get($step['segmentId']);
            if (! $seg) {
                continue;
            }
            if ($seg->out_and_back) {
                $a = (int) $seg->from_node_id;
                $b = (int) $seg->to_node_id;
                $entry = $startNodeId && in_array($startNodeId, [$a, $b], true)
                    ? $startNodeId
                    : $a;
                $tip = $entry === $a ? $b : $a;
                $nodeIds[] = $tip;
                $nodeIds[] = $entry;
                $startNodeId = $entry;
                continue;
            }
            $from = (int) $seg->from_node_id;
            $to = (int) $seg->to_node_id;
            $end = ! empty($step['reversed']) ? $from : $to;
            $nodeIds[] = $end;
            $startNodeId = $end;
        }

        $uniqueOrdered = [];
        $seen = [];
        foreach ($nodeIds as $id) {
            $id = (int) $id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $uniqueOrdered[] = $id;
        }

        $nodes = TourNode::query()->whereIn('id', $uniqueOrdered)->get()->keyBy('id');
        $names = [];
        foreach ($uniqueOrdered as $id) {
            $n = $nodes->get($id);
            $names[] = $n ? (string) $n->name : ('Punkt #'.$id);
        }

        return $names;
    }
}
