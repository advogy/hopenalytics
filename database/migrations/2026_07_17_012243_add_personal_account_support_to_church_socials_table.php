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
        Schema::table('church_socials', function (Blueprint $table) {
            $table->dropForeign(['church_id']);
        });

        Schema::table('church_socials', function (Blueprint $table) {
            $table->foreignId('church_id')->nullable()->change();
            $table->string('owner_name')->nullable()->after('church_id');
        });

        Schema::table('church_socials', function (Blueprint $table) {
            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('church_socials', function (Blueprint $table) {
            $table->dropForeign(['church_id']);
            $table->dropColumn('owner_name');
        });

        Schema::table('church_socials', function (Blueprint $table) {
            $table->foreignId('church_id')->nullable(false)->change();
        });

        Schema::table('church_socials', function (Blueprint $table) {
            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
        });
    }
};
