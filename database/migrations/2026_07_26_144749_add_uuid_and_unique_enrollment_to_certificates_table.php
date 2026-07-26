<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Public, unguessable identifier for the /verify/{uuid} page and the QR
            // code on the PDF — mirrors Invoice::getRouteKeyName(), the existing
            // convention for documents addressed outside the admin/authenticated area.
            $table->uuid()->unique()->after('id');
            $table->unique('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique(['enrollment_id']);
            $table->dropColumn('uuid');
        });
    }
};
