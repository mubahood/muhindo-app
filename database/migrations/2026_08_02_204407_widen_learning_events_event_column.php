<?php

use App\Enums\LearningEventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `learning_events.event` was a MySQL ENUM built from LearningEventType at the
 * moment that table was created. The PHP enum then gained a case
 * (assignment.submitted) and the column did not, so submitting an assignment
 * died on "Data truncated for column 'event'" — after the submission row had
 * already been written, leaving the student staring at a 500 having genuinely
 * handed in their work.
 *
 * The ENUM is dropped rather than widened. It was never buying anything:
 * LearningEventRecorder::record() takes a LearningEventType, so the only values
 * that can reach this column are already valid cases, and the model casts the
 * column back to the enum on read. What the ENUM did buy was a production-only
 * failure every time somebody added a case — invisible in the test suite, which
 * runs on SQLite, where ENUM is just TEXT with no constraint at all.
 *
 * A string column plus the PHP enum leaves one source of truth, and adding a
 * case never needs a migration again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_events', function (Blueprint $table) {
            $table->string('event', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('learning_events', function (Blueprint $table) {
            $table->enum('event', array_map(fn ($case) => $case->value, LearningEventType::cases()))->change();
        });
    }
};
