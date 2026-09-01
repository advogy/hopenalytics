<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free text, not a fixed country list — coverage now spans the whole world (Southeast Asia
     * especially, see GeocodingService's removed Indonesia/Jabodetabek restriction), and a
     * Union/Conference can't be trusted to imply one single country (a Union may itself span
     * several countries — see the Admin Global/Nasional split), so this has to live on the
     * Church itself, entered by whoever actually knows where it is.
     */
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->string('country')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
