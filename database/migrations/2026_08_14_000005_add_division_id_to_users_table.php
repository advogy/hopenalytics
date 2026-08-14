<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Same restrictOnDelete rationale as union_id/conference_id/church_id (see
            // add_role_and_scope_to_users_table): a null-fallback on delete would silently
            // widen a Divisi-scoped admin's visibility.
            $table->foreignId('division_id')->nullable()->after('is_active')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_id');
        });
    }
};
