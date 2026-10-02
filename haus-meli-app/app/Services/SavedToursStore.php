<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Gespeicherte Touren, geräteübergreifend. Die Planung läuft am Tablet oder PC,
 * das Handy zeigt die Touren nur an.
 */
class SavedToursStore
{
    public const MAX = 40;

    private const PATH = 'tours/saved-routes.json';

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::PATH)) {
            return [];
        }
        $list = json_decode((string) $disk->get(self::PATH), true);

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    public function upsert(array $incoming): array
    {
        return $this->locked(fn (array $current) => self::merge($current, $incoming));
    }

    /** @return list<array<string, mixed>> */
    public function remove(string $id): array
    {
        return $this->locked(fn (array $current) => self::without($current, $id));
    }

    /**
     * Neue Einträge ersetzen gleiche IDs, die neuesten stehen vorn, höchstens MAX.
     *
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    public static function merge(array $existing, array $incoming): array
    {
        $byId = [];
        foreach ([$existing, $incoming] as $source) {
            foreach ($source as $item) {
                if (is_array($item) && isset($item['id']) && $item['id'] !== '') {
                    $byId[(string) $item['id']] = $item;
                }
            }
        }

        $list = array_values($byId);
        usort($list, static fn (array $a, array $b): int => strcmp((string) ($b['savedAt'] ?? ''), (string) ($a['savedAt'] ?? '')));

        return array_slice($list, 0, self::MAX);
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @return list<array<string, mixed>>
     */
    public static function without(array $existing, string $id): array
    {
        return array_values(array_filter(
            $existing,
            static fn ($item): bool => is_array($item) && (string) ($item['id'] ?? '') !== $id
        ));
    }

    /**
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     * @return list<array<string, mixed>>
     */
    private function locked(callable $change): array
    {
        return Cache::lock('saved-tours.write', 10)->block(5, function () use ($change) {
            $next = $change($this->all());
            Storage::disk('local')->put(self::PATH, json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $next;
        });
    }
}
