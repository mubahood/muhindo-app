<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fields the authored catalogue carries that the schema did not.
 *
 * tagline, outcomes and requirements already existed. These are the rest:
 * a course's place in the catalogue (number, tier, featured), and the two
 * facts a lesson can now hold that came out of link verification, whether a
 * video is somebody else's work, and whether YouTube will let it play inside
 * our player at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // The catalogue's own numbering (01...21), which drives ordering,
            // "next course" suggestions and the learning paths.
            $table->unsignedSmallInteger('course_number')->nullable()->after('slug');
            $table->unsignedTinyInteger('tier')->nullable()->after('level');
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->string('prerequisites_note', 255)->nullable()->after('requirements');
            $table->string('playlist_url', 255)->nullable()->after('cover_alt');

            // Which authored file this course came from, and when it last
            // matched it, so an imported course is never a mystery in admin.
            $table->string('source_file', 120)->nullable()->after('created_by');
            $table->timestamp('synced_at')->nullable()->after('source_file');

            $table->index(['is_published', 'course_number']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            // Somebody else's video (freeCodeCamp, Mosh...). The attribution
            // stays visible in the body; this is what the UI badges.
            $table->boolean('is_external')->default(false)->after('is_free_preview');

            /*
             * Measured during link verification: 67 of Muhindo's own videos are
             * live but have embedding disabled, so an iframe shows a student
             * "Video unavailable" instead of the lesson. Those play out on
             * YouTube, and the player has to say so rather than pretend.
             */
            $table->boolean('is_embeddable')->default(true)->after('video_url');

            // A playlist or fallback link belonging to the lesson or its module.
            $table->string('resource_url', 255)->nullable()->after('captions_url');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['is_published', 'course_number']);
            $table->dropColumn(['course_number', 'tier', 'is_featured', 'prerequisites_note',
                'playlist_url', 'source_file', 'synced_at']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['is_external', 'is_embeddable', 'resource_url']);
        });
    }
};
