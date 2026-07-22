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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // nullOnDelete (not restrict): an audit log must survive the actor being deleted
            // later — actor_name is a snapshot of who did it at the time, kept even once
            // actor_id goes null, so the entry stays readable either way.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();

            // e.g. 'union.created', 'user.promoted' — namespaced by entity, not a DB enum, so
            // new action types never need a migration.
            $table->string('action');

            // Same snapshot reasoning as actor_name: subject_label survives the subject itself
            // being permanently deleted, so "Menghapus Uni \"X\"" still reads correctly even
            // though the Uni row (and subject_id) no longer resolves to anything.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();

            $table->text('description');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
