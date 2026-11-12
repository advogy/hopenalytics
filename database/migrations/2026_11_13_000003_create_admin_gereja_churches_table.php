<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra churches an Admin/Pimpinan Gereja manages besides their main one (users.church_id),
     * for someone who serves at more than one church. See User::assignedChurchIds().
     */
    public function up(): void
    {
        Schema::create('admin_gereja_churches', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'church_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_gereja_churches');
    }
};
