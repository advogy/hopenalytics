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
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_fetch_enabled')->default(true);
            $table->unsignedTinyInteger('auto_fetch_day')->default(0); // 0 = Minggu ... 6 = Sabtu
            $table->string('auto_fetch_time')->default('23:59'); // HH:MM, Asia/Jakarta
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
