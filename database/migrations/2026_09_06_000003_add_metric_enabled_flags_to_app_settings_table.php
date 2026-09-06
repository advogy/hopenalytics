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
        Schema::table('app_settings', function (Blueprint $table) {
            // Default true on every column — an existing installation must keep showing every
            // metric it already shows today until a superadmin explicitly turns one off, same
            // convention as the platform-visibility flags above.
            $table->boolean('metric_posts_enabled')->default(true)->after('threads_enabled');
            $table->boolean('metric_views_enabled')->default(true)->after('metric_posts_enabled');
            $table->boolean('metric_likes_enabled')->default(true)->after('metric_views_enabled');
            $table->boolean('metric_comments_enabled')->default(true)->after('metric_likes_enabled');
            $table->boolean('metric_shares_enabled')->default(true)->after('metric_comments_enabled');
            $table->boolean('metric_reach_enabled')->default(true)->after('metric_shares_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'metric_posts_enabled', 'metric_views_enabled', 'metric_likes_enabled',
                'metric_comments_enabled', 'metric_shares_enabled', 'metric_reach_enabled',
            ]);
        });
    }
};
