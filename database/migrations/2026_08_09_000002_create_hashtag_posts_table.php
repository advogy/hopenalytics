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
        Schema::create('hashtag_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hashtag_id')->constrained()->cascadeOnDelete();
            // instagram/tiktok/youtube only — see SocialPlatform; Facebook has no reliable
            // hashtag-search actor available via Apify, so it's never written here.
            $table->string('platform');
            $table->string('external_post_id');
            $table->string('post_url');
            $table->string('author_handle')->nullable();
            $table->text('caption')->nullable();
            $table->unsignedBigInteger('likes_count')->nullable();
            $table->unsignedBigInteger('comments_count')->nullable();
            $table->unsignedBigInteger('views_count')->nullable();
            $table->timestamp('posted_at')->nullable();
            // Bumped on every refresh that still finds this post — lets a future pass tell
            // "still up" apart from "hasn't matched since a stale run", without deleting rows.
            // useCurrent() satisfies MySQL strict mode's "no implicit default for a second
            // NOT NULL timestamp column" rule; the real fetch logic always sets this explicitly.
            $table->timestamp('last_seen_at')->useCurrent();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            // Re-running a fetch upserts by this key (refreshes counts/caption, bumps
            // last_seen_at) rather than duplicating rows — same pattern as church_stats'
            // (church_social_id, recorded_at) unique pair.
            $table->unique(['hashtag_id', 'platform', 'external_post_id']);
            $table->index(['hashtag_id', 'platform']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hashtag_posts');
    }
};
