<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ElevationController extends Controller
{
    private const CACHE_TTL_DAYS = 60;

    /**
     * Geländehöhen (OpenTopoData + Cache). Open-Meteo oft 429 → nur Fallback.
     */
    public function lookup(Request $request): JsonResponse
    {
        $lats = $request->input('latitude', []);
        $lngs = $request->input('longitude', []);

        if (! is_array($lats) || ! is_array($lngs) || count($lats) === 0 || count($lats) !== count($lngs)) {
            return response()->json(['message' => 'latitude/longitude Arrays gleicher Länge nötig.'], 422);
        }

        if (count($lats) > 220) {
            return response()->json(['message' => 'Maximal 220 Punkte pro Anfrage.'], 422);
        }

        $elevations = array_fill(0, count($lats), null);
        $missing = [];

        foreach ($lats as $i => $lat) {
            $latF = round((float) $lat, 5);
            $lngF = round((float) $lngs[$i], 5);
            $key = sprintf('elev:%.5f,%.5f', $latF, $lngF);
            $cached = Cache::store('file')->get($key);
            if (is_numeric($cached)) {
                $elevations[$i] = round((float) $cached, 1);
            } else {
                $missing[] = ['i' => $i, 'lat' => $latF, 'lng' => $lngF, 'key' => $key];
            }
        }

        if ($missing !== []) {
            $this->fillFromOpenTopoData($missing, $elevations);
        }

        // Reste: Open-Meteo (kann 429 sein)
        $still = array_values(array_filter($missing, static fn ($m) => $elevations[$m['i']] === null));
        if ($still !== []) {
            $this->fillFromOpenMeteo($still, $elevations);
        }

        return response()->json(['elevation' => $elevations]);
    }

    /**
     * @param  list<array{i:int,lat:float,lng:float,key:string}>  $missing
     * @param  list<?float>  $elevations
     */
    private function fillFromOpenTopoData(array $missing, array &$elevations): void
    {
        $batchSize = 100; // OpenTopoData-Limit
        for ($offset = 0; $offset < count($missing); $offset += $batchSize) {
            $batch = array_slice($missing, $offset, $batchSize);
            $locs = implode('|', array_map(
                static fn ($m) => sprintf('%.5f,%.5f', $m['lat'], $m['lng']),
                $batch
            ));
            $url = 'https://api.opentopodata.org/v1/aster30m?locations='.$locs;

            try {
                $res = Http::timeout(20)
                    ->connectTimeout(5)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get($url);
            } catch (\Throwable $e) {
                continue;
            }

            if (! $res->successful()) {
                continue;
            }

            $data = $res->json();
            if (($data['status'] ?? '') !== 'OK' || ! is_array($data['results'] ?? null)) {
                continue;
            }

            foreach ($data['results'] as $j => $row) {
                if (! isset($batch[$j])) {
                    continue;
                }
                $ele = $row['elevation'] ?? null;
                if ($ele === null || ! is_numeric($ele)) {
                    continue;
                }
                $val = round((float) $ele, 1);
                $elevations[$batch[$j]['i']] = $val;
                Cache::store('file')->put($batch[$j]['key'], $val, now()->addDays(self::CACHE_TTL_DAYS));
            }

            // Free-Tier: max. 1 Request/Sekunde
            if ($offset + $batchSize < count($missing)) {
                usleep(1100000);
            }
        }
    }

    /**
     * @param  list<array{i:int,lat:float,lng:float,key:string}>  $missing
     * @param  list<?float>  $elevations
     */
    private function fillFromOpenMeteo(array $missing, array &$elevations): void
    {
        $batchSize = 40;
        for ($offset = 0; $offset < count($missing); $offset += $batchSize) {
            $batch = array_slice($missing, $offset, $batchSize);
            $latStr = implode(',', array_map(static fn ($m) => sprintf('%.5f', $m['lat']), $batch));
            $lngStr = implode(',', array_map(static fn ($m) => sprintf('%.5f', $m['lng']), $batch));
            $url = 'https://api.open-meteo.com/v1/elevation?latitude='.$latStr.'&longitude='.$lngStr;

            try {
                $res = Http::timeout(10)
                    ->connectTimeout(3)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get($url);
            } catch (\Throwable $e) {
                continue;
            }

            if ($res->status() === 429 || ! $res->successful()) {
                break;
            }

            $data = $res->json();
            $batchElev = is_array($data['elevation'] ?? null) ? $data['elevation'] : [];
            foreach ($batchElev as $j => $ele) {
                if (! isset($batch[$j]) || $ele === null || ! is_numeric($ele)) {
                    continue;
                }
                $val = round((float) $ele, 1);
                $elevations[$batch[$j]['i']] = $val;
                Cache::store('file')->put($batch[$j]['key'], $val, now()->addDays(self::CACHE_TTL_DAYS));
            }

            if ($offset + $batchSize < count($missing)) {
                usleep(200000);
            }
        }
    }
}
