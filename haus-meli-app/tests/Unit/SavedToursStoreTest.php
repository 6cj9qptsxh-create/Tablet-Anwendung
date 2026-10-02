<?php

namespace Tests\Unit;

use App\Services\SavedToursStore;
use PHPUnit\Framework\TestCase;

class SavedToursStoreTest extends TestCase
{
    private function tour(string $id, string $savedAt, string $name = 'Tour'): array
    {
        return ['id' => $id, 'name' => $name, 'savedAt' => $savedAt, 'route' => ['steps' => [['segmentId' => 1]]]];
    }

    public function test_new_tours_are_added_and_sorted_newest_first(): void
    {
        $merged = SavedToursStore::merge(
            [$this->tour('a', '2026-10-01T10:00:00Z')],
            [$this->tour('b', '2026-10-02T10:00:00Z')]
        );

        $this->assertSame(['b', 'a'], array_column($merged, 'id'));
    }

    public function test_same_id_is_replaced_by_the_incoming_tour(): void
    {
        $merged = SavedToursStore::merge(
            [$this->tour('a', '2026-10-01T10:00:00Z', 'Alt')],
            [$this->tour('a', '2026-10-01T10:00:00Z', 'Neu')]
        );

        $this->assertSame(1, count($merged));
        $this->assertSame('Neu', $merged[0]['name']);
    }

    public function test_at_most_forty_tours_are_kept(): void
    {
        $incoming = [];
        for ($i = 0; $i < 45; $i++) {
            $incoming[] = $this->tour('t'.$i, sprintf('2026-10-02T10:%02d:00Z', $i));
        }

        $merged = SavedToursStore::merge([], $incoming);

        $this->assertSame(40, count($merged));
        $this->assertSame('t44', $merged[0]['id']);
    }

    public function test_entries_without_id_are_ignored(): void
    {
        $merged = SavedToursStore::merge([], [['name' => 'ohne'], $this->tour('a', '2026-10-02T10:00:00Z')]);

        $this->assertSame(['a'], array_column($merged, 'id'));
    }

    public function test_remove_drops_only_that_tour(): void
    {
        $left = SavedToursStore::without([
            $this->tour('a', '2026-10-01T10:00:00Z'),
            $this->tour('b', '2026-10-02T10:00:00Z'),
        ], 'a');

        $this->assertSame(['b'], array_column($left, 'id'));
    }
}
