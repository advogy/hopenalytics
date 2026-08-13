<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Admin Nasional/Pimpinan Nasional used to mean fully unrestricted access — this
     * same deploy repurposes those role values to mean "scoped to an assigned set of
     * Unions" instead (see the new admin_nasional_unions table and every
     * scopeVisibleTo()/scopeManageableBy() touched alongside this). Without this data
     * migration, every existing Admin/Pimpinan Nasional would silently lose all access
     * the moment this ships (scoped, but assigned to zero Unions). Existing rows are
     * promoted to the new Admin/Pimpinan Global role instead, which keeps the exact
     * unrestricted access they already have today — nobody loses anything on deploy.
     * Going forward, new Admin/Pimpinan Nasional assignments use the new scoped meaning.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'admin_nasional')->update(['role' => 'admin_global']);
        DB::table('users')->where('role', 'pimpinan_nasional')->update(['role' => 'pimpinan_global']);
    }

    /**
     * Not a true inverse (any Admin/Pimpinan Nasional promoted after this shipped would
     * incorrectly get pulled back down too), but matches every other role-value
     * migration's best-effort rollback in this codebase — down() is only ever run
     * manually during development, never in production.
     */
    public function down(): void
    {
        DB::table('users')->where('role', 'admin_global')->update(['role' => 'admin_nasional']);
        DB::table('users')->where('role', 'pimpinan_global')->update(['role' => 'pimpinan_nasional']);
    }
};
