<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the task table into something a day can be planned from.
 *
 * Three additions, each answering a question the table could not answer:
 *
 *   priority        which two of today's ten actually matter
 *   repeat_every    the habit that has to survive being forgotten
 *   repeats_from_id which template a generated copy came from
 *
 * A repeating task is a task carrying a rule, not a row in a second table. At
 * this size a `recurring_tasks` table would double the code that reads a to-do
 * list and buy nothing: every screen would have to union the two, and every
 * bug would have to be fixed twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            // Three levels, not the four that `projects` uses. A project's
            // priority is a commercial judgement; a to-do's is only ever
            // "before the others", "normal" or "if there is time".
            $table->string('priority', 8)->default('normal')->after('status');

            // null means it happens once, which is almost every task.
            $table->string('repeat_every', 8)->nullable()->after('due_date');
            $table->date('repeat_until')->nullable()->after('repeat_every');

            // Set on generated copies, pointing at the template that made them.
            // This is what makes the generator idempotent: it asks whether a
            // copy already exists for a date rather than counting how many it
            // has produced, so running it twice is a no-op instead of a
            // duplicate. A to-do list you cannot trust is worse than none.
            $table->foreignId('repeats_from_id')->nullable()->after('repeat_until')
                ->constrained('project_tasks')->nullOnDelete();

            // The day view's own query: unfinished work, ordered by date.
            $table->index(['due_date', 'status']);
            $table->index('repeat_every');
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropForeign(['repeats_from_id']);
            $table->dropIndex(['due_date', 'status']);
            $table->dropIndex(['repeat_every']);
            $table->dropColumn(['priority', 'repeat_every', 'repeat_until', 'repeats_from_id']);
        });
    }
};
