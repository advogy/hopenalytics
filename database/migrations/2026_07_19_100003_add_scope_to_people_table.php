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
        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('union_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('conference_id')->nullable()->after('union_id')->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->after('conference_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('union_id');
            $table->dropConstrainedForeignId('conference_id');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
