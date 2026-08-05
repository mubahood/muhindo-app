<?php

use App\Enums\CompletionRule;
use App\Enums\ContentFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per-lesson completion rule + content rendering format. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('completion_rule', 16)->default(CompletionRule::Manual->value)->after('duration_minutes');
            $table->unsignedTinyInteger('completion_threshold')->default(80)->after('completion_rule');
            $table->string('content_format', 16)->default(ContentFormat::Plain->value)->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['completion_rule', 'completion_threshold', 'content_format']);
        });
    }
};
