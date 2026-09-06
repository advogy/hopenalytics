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
        Schema::table('hashtag_posts', function (Blueprint $table) {
            // Nullable like likes_count/views_count above it — a platform whose actor never
            // exposes a share count (Instagram, YouTube; X/Threads unconfirmed) stays null
            // rather than a misleading 0, per the same convention as views_count already does
            // for platforms with no public view count (Facebook/Threads).
            $table->unsignedBigInteger('shares_count')->nullable()->after('views_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hashtag_posts', function (Blueprint $table) {
            $table->dropColumn('shares_count');
        });
    }
};
