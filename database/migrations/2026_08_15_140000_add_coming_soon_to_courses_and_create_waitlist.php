<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puts the whole catalogue behind "coming soon", and gives the people who
 * wanted it somewhere to leave their name.
 *
 * Deliberately a per-course flag rather than one global switch. A switch would
 * have to be found and understood before the first course could ever be sold,
 * and it makes launching one course impossible without launching all 21. A
 * column lets the catalogue open one at a time, which is how it will actually
 * happen.
 *
 * It defaults to TRUE, which is the safe direction: a course imported or
 * created later is not accidentally on sale before anybody has looked at it.
 *
 * The waitlist is worth more than the block. A visitor who wanted to buy and
 * could not is the strongest lead this site produces, and until now that
 * person left no trace at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('is_coming_soon')->default(true)->after('is_published');
            // The catalogue filters on it on every page load.
            $table->index(['is_published', 'is_coming_soon']);
        });

        // Every existing course, as asked. Nothing is on sale until it is
        // turned on one at a time from the admin.
        DB::table('courses')->update(['is_coming_soon' => true]);

        Schema::create('course_notify_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // Nullable: the point of this form is that it works for a stranger.
            // Making an account first is the wall this exists to get around.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 120);
            $table->string('whatsapp', 32);
            $table->string('email', 150);

            // Set when they have actually been told, so a second launch email
            // does not go to the same people twice.
            $table->timestamp('notified_at')->nullable();

            // Kept for the same reason the analytics tables keep it: to tell a
            // hundred real people from one script filling the form all night.
            $table->string('ip', 45)->nullable();
            $table->string('source_path', 191)->nullable();

            $table->timestamps();

            // One person, one course, once. Asking twice is not more interest,
            // it is a double-tap, and it would inflate the only number here
            // anybody would act on.
            $table->unique(['course_id', 'email']);
            $table->index('created_at');
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_notify_requests');

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['is_published', 'is_coming_soon']);
            $table->dropColumn('is_coming_soon');
        });
    }
};
