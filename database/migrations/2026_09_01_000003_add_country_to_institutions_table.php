<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Same reasoning as 2026_09_01_000001_add_country_to_churches_table — see that file. */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('country')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
