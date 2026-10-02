<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_workspaces', function (Blueprint $table) {
            $table->index(['user_id', 'expires_at'], 'practice_workspaces_user_expiry_index');
            $table->dropUnique(['user_id']);
        });
    }

    public function down(): void
    {
        DB::table('practice_workspaces')
            ->whereNotIn('id', DB::table('practice_workspaces')->selectRaw('MAX(id)')->groupBy('user_id'))
            ->delete();

        Schema::table('practice_workspaces', function (Blueprint $table) {
            $table->dropIndex('practice_workspaces_user_expiry_index');
            $table->unique('user_id');
        });
    }
};
