<?php

namespace Tests\Feature\Learning;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** L13. The queries these surfaces run at scale must be backed by real indexes. */
class PerformanceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_progress_has_a_composite_index_on_lesson_id_and_completed_at(): void
    {
        $columns = collect(Schema::getIndexes('lesson_progress'))
            ->first(fn (array $index) => $index['columns'] === ['lesson_id', 'completed_at']);

        $this->assertNotNull($columns, 'Expected a composite index on (lesson_id, completed_at).');
    }

    public function test_enrollments_has_an_index_on_status(): void
    {
        $hasStatusIndex = collect(Schema::getIndexes('enrollments'))
            ->contains(fn (array $index) => $index['columns'] === ['status']);

        $this->assertTrue($hasStatusIndex, 'Expected an index on enrollments.status.');
    }
}
