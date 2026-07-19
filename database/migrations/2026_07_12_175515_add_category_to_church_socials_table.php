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
            // MySQL uses the (church_id, platform) unique index to back the church_id
            // foreign key, so an explicit index is needed before that unique can be dropped.
            $table->index('church_id');
        });

        Schema::table('church_socials', function (Blueprint $table) {
            $table->dropUnique(['church_id', 'platform']);
            $table->string('category')->default('gereja')->after('platform');
            $table->unique(['church_id', 'platform', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('church_socials', function (Blueprint $table) {
            $table->dropUnique(['church_id', 'platform', 'category']);
            $table->dropColumn('category');
            $table->unique(['church_id', 'platform']);
        });

        Schema::table('church_socials', function (Blueprint $table) {
            $table->dropIndex(['church_id']);
        });
    }
};
