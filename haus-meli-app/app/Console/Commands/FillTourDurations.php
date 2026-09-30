<?php

namespace App\Console\Commands;

use App\Models\TourSegment;
use App\Models\TourSegmentMode;
use App\Services\TourDurationEstimator;
use Illuminate\Console\Command;

class FillTourDurations extends Command
{
    protected $signature = 'tours:fill-durations {--force : Auch vorhandene duration_min überschreiben}';

    protected $description = 'Schätzt duration_min für Segment-Mode-Profile aus km/Hm/Sportart';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $updated = 0;
        $skipped = 0;

        TourSegment::query()->with('modeProfiles')->orderBy('id')->chunkById(50, function ($segments) use ($force, &$updated, &$skipped) {
            foreach ($segments as $segment) {
                $km = (float) ($segment->distance_km ?? 0);
                $hm = (int) ($segment->elevation_m ?? 0);
                $isCable = (bool) ($segment->is_cable_car ?? false)
                    || in_array('seilbahn', $segment->tags ?? [], true)
                    || in_array('cable_car', $segment->tags ?? [], true);

                foreach ($segment->modeProfiles as $profile) {
                    /** @var TourSegmentMode $profile */
                    if (! $force && $profile->duration_min !== null && (int) $profile->duration_min > 0) {
                        $skipped++;
                        continue;
                    }
                    $minutes = TourDurationEstimator::estimateMinutes(
                        (string) $profile->mode,
                        $km,
                        $hm,
                        $isCable
                    );
                    $profile->duration_min = $minutes;
                    $profile->save();
                    $updated++;
                }

                if ($force || $segment->duration_min === null || (int) $segment->duration_min <= 0) {
                    $mode = (string) ($segment->mode ?: 'hike');
                    $segment->duration_min = TourDurationEstimator::estimateMinutes($mode, $km, $hm, $isCable);
                    $segment->save();
                }
            }
        });

        $this->info("Aktualisiert: {$updated} Mode-Profile, übersprungen: {$skipped}.");

        return self::SUCCESS;
    }
}
