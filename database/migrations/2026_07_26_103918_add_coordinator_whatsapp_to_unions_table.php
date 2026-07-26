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
        Schema::table('unions', function (Blueprint $table) {
            $table->string('coordinator_whatsapp_number')->nullable()->after('is_active');
            $table->string('whatsapp_group_link')->nullable()->after('coordinator_whatsapp_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unions', function (Blueprint $table) {
            $table->dropColumn(['coordinator_whatsapp_number', 'whatsapp_group_link']);
        });
    }
};
