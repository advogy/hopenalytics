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
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            // nullOnDelete, not cascade — a deleted user's login history stays visible in the
            // log (same "keep the audit trail even if the subject is gone" reasoning as
            // AuditLog's own actor_name snapshot column).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            // Null means either still logged in, or the session simply expired/was abandoned
            // without an explicit "Sign Out" click — Laravel's Logout event (the only thing
            // this can key off) never fires for that case, so it's left null rather than
            // guessed at.
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
