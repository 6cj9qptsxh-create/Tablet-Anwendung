<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Models\Trip;
use Illuminate\Console\Command;

class MigrateToursToTrips extends Command
{
    protected $signature = 'tours:migrate-legacy {--force : Auch bereits migrierte erneut anlegen}';

    protected $description = 'Bestehende tours-Tabelle als Trips ohne Geometrie übernehmen';

    public function handle(): int
    {
        if (! \Schema::hasTable('tours')) {
            $this->warn('Tabelle tours existiert nicht — nichts zu migrieren.');

            return self::SUCCESS;
        }

        $count = 0;
        Tour::query()->orderBy('id')->each(function (Tour $tour) use (&$count) {
            $exists = Trip::where('legacy_tour_id', $tour->id)->exists();
            if ($exists && ! $this->option('force')) {
                return;
            }

            if ($exists && $this->option('force')) {
                Trip::where('legacy_tour_id', $tour->id)->delete();
            }

            $title = $tour->title;
            if (is_string($title)) {
                $title = ['de' => $title, 'en' => $title];
            } elseif (! is_array($title)) {
                $title = ['de' => 'Tour '.$tour->id];
            }

            $description = $tour->description;
            if (is_string($description)) {
                $description = ['de' => $description, 'en' => $description];
            } elseif (! is_array($description)) {
                $description = ['de' => '', 'en' => ''];
            }

            Trip::create([
                'title' => $title,
                'description' => $description,
                'category' => $tour->category ?? 'hiking',
                'season' => $tour->season,
                'dist' => (float) ($tour->dist ?? 0),
                'alt' => (int) ($tour->alt ?? 0),
                'difficulty' => (int) ($tour->difficulty ?? 1),
                'fitness' => (int) ($tour->fitness ?? 1),
                'tags' => $tour->tags ?? [],
                'images' => $tour->images ?? [],
                'video' => $tour->video,
                'is_active' => (bool) ($tour->is_active ?? true),
                'legacy_tour_id' => $tour->id,
            ]);
            $count++;
        });

        $this->info("{$count} Tour(en) als Trip übernommen.");

        return self::SUCCESS;
    }
}
