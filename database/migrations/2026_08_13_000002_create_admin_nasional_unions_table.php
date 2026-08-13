<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which Union(s) a scoped Admin/Pimpinan Nasional is assigned to — many-to-many
     * since one country can have multiple Unions and one Union can span multiple
     * countries, so there's no single "region" column to reuse the way
     * uni/daerah/gereja/institusi levels do (see UserRole::level()'s doc comment).
     */
    public function up(): void
    {
        Schema::create('admin_nasional_unions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('union_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'union_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_nasional_unions');
    }
};
