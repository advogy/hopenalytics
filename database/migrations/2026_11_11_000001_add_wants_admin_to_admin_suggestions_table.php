<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Typing a new church name no longer means "make me its admin" by itself: the member now
     * ticks "I'm this church's admin" for that. false = only add the church and link the member
     * to it as a regular member. Existing rows default to true, since every suggestion filed
     * before this was an admin request.
     */
    public function up(): void
    {
        Schema::table('admin_suggestions', function (Blueprint $table) {
            $table->boolean('wants_admin')->default(true)->after('church_name');
        });
    }

    public function down(): void
    {
        Schema::table('admin_suggestions', function (Blueprint $table) {
            $table->dropColumn('wants_admin');
        });
    }
};
