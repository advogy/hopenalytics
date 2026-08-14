<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the single cs_whatsapp_group_link (app_settings) / whatsapp_group_link (unions)
     * columns with a real one-to-many table — a Union (or the global settings, via a null
     * union_id) can now have several chat groups at once (WhatsApp AND Messenger simultaneously,
     * per the user's explicit call), not just one WhatsApp-only link. Existing links are carried
     * over as WhatsApp entries before their old columns are dropped, so nothing already
     * configured is lost.
     */
    public function up(): void
    {
        Schema::create('coordinator_groups', function (Blueprint $table) {
            $table->id();
            // Null = the global (Pengaturan → Koordinator Global) entry, same "null means
            // unscoped" shape already used elsewhere (e.g. Institution's union_id).
            $table->foreignId('union_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('url', 2048);
            $table->timestamps();
        });

        $globalLink = DB::table('app_settings')->where('id', 1)->value('cs_whatsapp_group_link');

        if ($globalLink) {
            DB::table('coordinator_groups')->insert([
                'union_id' => null,
                'platform' => 'whatsapp',
                'url' => $globalLink,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('unions')->whereNotNull('whatsapp_group_link')->get(['id', 'whatsapp_group_link'])
            ->each(fn ($union) => DB::table('coordinator_groups')->insert([
                'union_id' => $union->id,
                'platform' => 'whatsapp',
                'url' => $union->whatsapp_group_link,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('cs_whatsapp_group_link');
        });

        Schema::table('unions', function (Blueprint $table) {
            $table->dropColumn('whatsapp_group_link');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('cs_whatsapp_group_link')->nullable()->after('cs_whatsapp_number');
        });

        Schema::table('unions', function (Blueprint $table) {
            $table->string('whatsapp_group_link')->nullable()->after('coordinator_whatsapp_number');
        });

        $globalGroup = DB::table('coordinator_groups')->whereNull('union_id')->where('platform', 'whatsapp')->first();

        if ($globalGroup) {
            DB::table('app_settings')->where('id', 1)->update(['cs_whatsapp_group_link' => $globalGroup->url]);
        }

        DB::table('coordinator_groups')->whereNotNull('union_id')->where('platform', 'whatsapp')->get(['union_id', 'url'])
            ->each(fn ($group) => DB::table('unions')->where('id', $group->union_id)->update(['whatsapp_group_link' => $group->url]));

        Schema::dropIfExists('coordinator_groups');
    }
};
