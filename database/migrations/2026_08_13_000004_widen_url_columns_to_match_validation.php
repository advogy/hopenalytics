<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * churches.logo_url and church_socials.profile_url were both plain string() columns
     * (VARCHAR(255)) while every controller validating them already allows up to 2048
     * characters ('url', 'max:2048') — real-world URLs (Instagram CDN profile pictures
     * especially, with long signed query-string tokens) routinely exceed 255 characters,
     * so a value that passed validation could still fail at the DB with "Data too long for
     * column" (confirmed in production logs for logo_url specifically). Widened to match
     * what validation already promises is acceptable. Raw SQL rather than Schema::change()
     * since doctrine/dbal (required for column modification via the schema builder) isn't
     * installed in this project.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE churches MODIFY logo_url VARCHAR(2048) NULL');
        DB::statement('ALTER TABLE church_socials MODIFY profile_url VARCHAR(2048) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE churches MODIFY logo_url VARCHAR(255) NULL');
        DB::statement('ALTER TABLE church_socials MODIFY profile_url VARCHAR(255) NULL');
    }
};
