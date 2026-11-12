<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the newest post in a fetch's sample of recent posts was published. Lets the "posted
     * during the period" engagement measure see posting on Facebook and Threads, whose automatic
     * snapshots have no post total to compare (see MemberEngagement::postedInPeriod()).
     */
    public function up(): void
    {
        Schema::table('church_stats', function (Blueprint $table) {
            $table->timestamp('latest_post_at')->nullable()->after('recent_posts_comments');
            $table->index('latest_post_at');
        });
    }

    public function down(): void
    {
        Schema::table('church_stats', function (Blueprint $table) {
            $table->dropIndex(['latest_post_at']);
            $table->dropColumn('latest_post_at');
        });
    }
};
