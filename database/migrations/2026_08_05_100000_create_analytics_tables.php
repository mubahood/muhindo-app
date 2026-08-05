<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audience analytics: who comes to the site, where from, and what they do.
 *
 * Four tables in a hierarchy, because the questions asked of them are asked at
 * four different altitudes and a single flat event log answers none of them
 * well:
 *
 *   visitors        a person, across every visit they ever make
 *   visits          one sitting, ended by 30 minutes of silence
 *   page_views      one page, and how much of it they actually read
 *   analytics_events  one thing done, whether or not it was a page
 *
 * The fifth, analytics_daily, is a rollup. Overview screens ask "how many
 * visitors in the last 90 days" on every load, and that question against a
 * growing page_views table gets slower every week for an answer that has not
 * changed since midnight.
 *
 * Acquisition is recorded twice on purpose. A visit carries the source that
 * brought it, and a visitor carries the source that brought them the first
 * time, never overwritten. Otherwise somebody who arrives from a YouTube
 * comment, returns twice by typing the domain and then buys is credited to
 * "direct", and the channel that actually earned them looks worthless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            // The browser's own id, carried in a first-party cookie. Not the
            // primary key: the cookie is client-controlled and the id is not.
            $table->uuid('token')->unique();

            // Set the moment an anonymous browser signs in, which is what turns
            // months of anonymous history into a named person's history.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('identified_at')->nullable();

            // First-touch attribution. Written once, on the first request.
            $table->string('first_landing_path', 255)->nullable();
            $table->string('first_referrer', 255)->nullable();
            $table->string('first_source', 64)->nullable();
            $table->string('first_medium', 32)->nullable();
            $table->string('first_campaign', 64)->nullable();

            // Last-known context, refreshed each visit, for "who is this".
            $table->string('last_country', 2)->nullable();
            $table->string('last_city', 64)->nullable();
            $table->string('last_device', 16)->nullable();
            $table->string('last_browser', 32)->nullable();
            $table->string('last_os', 32)->nullable();
            $table->string('last_ip', 45)->nullable();

            $table->unsignedInteger('visits_count')->default(0);
            $table->unsignedInteger('page_views_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);
            $table->unsignedInteger('engaged_seconds')->default(0);

            // Denormalised so the visitor list can sort and filter on outcome
            // without joining four tables per row.
            $table->timestamp('converted_at')->nullable();
            $table->decimal('revenue', 12, 2)->default(0);

            $table->boolean('is_bot')->default(false);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('last_seen_at');
            $table->index('user_id');
            $table->index(['is_bot', 'last_seen_at']);
            $table->index('converted_at');
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('entry_path', 255)->nullable();
            $table->string('exit_path', 255)->nullable();
            $table->string('referrer', 255)->nullable();
            $table->string('referrer_host', 128)->nullable();

            // Channel is the grouping a person actually thinks in (Search,
            // Social, Referral, Direct, Campaign); source/medium keep the
            // detail underneath it.
            $table->string('channel', 16)->default('direct');
            $table->string('source', 64)->nullable();
            $table->string('medium', 32)->nullable();
            $table->string('campaign', 64)->nullable();

            $table->string('device', 16)->nullable();     // desktop|mobile|tablet|bot
            $table->string('browser', 32)->nullable();
            $table->string('os', 32)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city', 64)->nullable();
            $table->string('language', 8)->nullable();

            $table->unsignedInteger('page_views_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);
            // Time the tab was open AND focused, summed from the beacon. Not
            // last-page-minus-first-page, which counts a forgotten tab as
            // three hours of rapt attention.
            $table->unsignedInteger('engaged_seconds')->default(0);
            $table->boolean('is_bounce')->default(true);

            $table->dateTime('started_at');
            $table->dateTime('last_activity_at');
            $table->timestamps();

            $table->index(['visitor_id', 'started_at']);
            $table->index('started_at');
            $table->index('last_activity_at');
            $table->index(['channel', 'started_at']);
            $table->index('user_id');
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('path', 255);
            $table->string('query', 512)->nullable();
            $table->string('route_name', 96)->nullable();
            $table->string('title', 191)->nullable();

            // What the page was ABOUT, not just its URL. This is what makes
            // "which course is being looked at and never bought" a query
            // rather than a regular expression over paths.
            $table->nullableMorphs('subject');

            $table->unsignedSmallInteger('status')->default(200);
            $table->unsignedInteger('response_ms')->nullable();

            // Filled in later by the beacon, so both stay null for anyone who
            // leaves immediately or blocks scripts.
            $table->unsignedInteger('engaged_seconds')->nullable();
            $table->unsignedTinyInteger('scroll_percent')->nullable();

            $table->dateTime('viewed_at');

            $table->index(['visit_id', 'viewed_at']);
            $table->index(['visitor_id', 'viewed_at']);
            $table->index('viewed_at');
            $table->index(['path', 'viewed_at']);
            $table->index('route_name');
            $table->index('user_id');
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_view_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 48);              // cart.add, enrolled, order.paid
            $table->string('category', 24)->default('interaction');
            $table->string('label', 191)->nullable();
            $table->nullableMorphs('subject');
            $table->string('path', 255)->nullable();

            // Money on the event that earned it, so a channel report can add up
            // revenue instead of counting clicks and calling it performance.
            $table->decimal('value', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->json('meta')->nullable();

            $table->dateTime('occurred_at');

            $table->index(['name', 'occurred_at']);
            $table->index(['category', 'occurred_at']);
            $table->index(['visitor_id', 'occurred_at']);
            $table->index('occurred_at');
            $table->index('user_id');
        });

        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();

            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('new_visitors')->default(0);
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('bounces')->default(0);
            $table->unsignedBigInteger('engaged_seconds')->default(0);

            $table->unsignedInteger('signups')->default(0);
            $table->unsignedInteger('enrollments')->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('inquiries')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);

            // Small enough to keep inline, and it saves a second rollup table
            // per dimension for reports that only ever want the top few.
            $table->json('by_channel')->nullable();
            $table->json('by_country')->nullable();
            $table->json('by_device')->nullable();

            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('visitors');
    }
};
