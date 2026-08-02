<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Work attached to a topic is part of that topic.
 *
 * `is_required` already did exactly the right thing — it blocks completing the
 * lesson an activity hangs off, and the admin form says so in as many words.
 * Its default was simply the wrong way round: attaching a quiz to a lesson did
 * nothing at all unless the author separately remembered to tick a box, so a
 * student could read a topic and move straight on with its quiz untouched.
 *
 * The flag stays, because an optional practice quiz is a real thing an author
 * may want. Only the default flips, so it now takes a deliberate act to make
 * attached work skippable rather than a deliberate act to make it count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('is_required')->default(true)->change();
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('is_required')->default(true)->change();
        });

        // Existing rows keep what they were given, EXCEPT published work
        // already attached to a lesson: that was authored as part of a topic
        // under a default nobody chose, and is the case this exists to fix.
        DB::table('quizzes')
            ->whereNotNull('lesson_id')->where('is_published', true)
            ->update(['is_required' => true]);

        DB::table('assignments')
            ->whereNotNull('lesson_id')->where('is_published', true)
            ->update(['is_required' => true]);
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->change();
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->change();
        });
    }
};
