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
        Schema::create('church_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_social_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_at');
            $table->unsignedBigInteger('subscribers_count')->nullable();
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('following_count')->nullable();
            $table->unsignedBigInteger('views_count')->nullable();
            $table->unsignedInteger('videos_count')->nullable();
            $table->unsignedInteger('posts_count')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['church_social_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('church_stats');
    }
};
