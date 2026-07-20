<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');

            // restrictOnDelete to match union_id/conference_id/church_id (see the 2026_07_19_100004
            // migration): a null-fallback on institution deletion would silently promote its
            // admins to broader visibility.
            $table->foreignId('institution_id')->nullable()->after('church_id')->constrained()->restrictOnDelete();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn(['is_active', 'deleted_at']);
        });
    }
};
