<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lead becomes a proposal.
 *
 * The old form took a name, an email and a paragraph — enough to start a
 * conversation, not enough to price anything. These are the fields that turn
 * "I want a system" into something that can be quoted, and they are asked for
 * once, at the point somebody decides to hire, rather than over four emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_inquiries', function (Blueprint $table) {
            $table->string('title')->nullable()->after('organisation');
            $table->string('category')->nullable()->after('project_type');
            $table->decimal('budget_amount', 14, 2)->nullable()->after('budget_range');
            $table->string('budget_currency', 3)->nullable()->after('budget_amount');
            $table->text('who_uses_it')->nullable()->after('description');
            $table->text('success_looks_like')->nullable()->after('who_uses_it');
            $table->timestamp('submitted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('project_inquiries', function (Blueprint $table) {
            $table->dropColumn(['title', 'category', 'budget_amount', 'budget_currency',
                'who_uses_it', 'success_looks_like', 'submitted_at']);
        });
    }
};
