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
            $table->decimal('latitude', 10, 7)->nullable()->after('whatsapp_group_link');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('conferences', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('is_active');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unions', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('conferences', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
