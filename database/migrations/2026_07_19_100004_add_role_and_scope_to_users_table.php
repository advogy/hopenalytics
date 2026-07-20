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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('id');

            // restrictOnDelete (not nullOnDelete): if an org unit is deleted, a null-fallback
            // would silently promote its admins to nasional-level visibility.
            $table->foreignId('union_id')->nullable()->after('role')->constrained()->restrictOnDelete();
            $table->foreignId('conference_id')->nullable()->after('union_id')->constrained()->restrictOnDelete();
            $table->foreignId('church_id')->nullable()->after('conference_id')->constrained()->restrictOnDelete();

            $table->string('otp_code')->nullable()->after('remember_token');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('union_id');
            $table->dropConstrainedForeignId('conference_id');
            $table->dropConstrainedForeignId('church_id');
            $table->dropColumn(['role', 'otp_code', 'otp_expires_at']);
        });
    }
};
