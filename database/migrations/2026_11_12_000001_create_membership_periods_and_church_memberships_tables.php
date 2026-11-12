<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Member engagement target: in each period (set by a global admin) a share of the recorded
     * membership — 10% by default — should be actively involved.
     *
     * Membership is a figure per church as of a date. A Division admin uploads it in bulk, a
     * Union admin confirms it, and only confirmed figures count. Daerah and Uni totals are the
     * sum of their churches.
     */
    public function up(): void
    {
        Schema::create('membership_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('target_percent', 5, 2)->default(10);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('church_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->date('as_of');
            $table->unsignedInteger('members_count');
            $table->string('status', 20)->default('pending'); // pending | confirmed | rejected
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['church_id', 'as_of']);
            $table->index(['status', 'as_of']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_memberships');
        Schema::dropIfExists('membership_periods');
    }
};
