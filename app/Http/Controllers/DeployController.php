<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use ZipArchive;

/**
 * CI's deploy trigger: Hostinger shared hosting has no SSH, so instead of a full file-by-file
 * FTP sync (thousands of vendor/ files reliably blow past Hostinger's 1-hour FTP session cap),
 * CI uploads one deploy-payload.zip and hits this endpoint (guarded by VerifyDeployToken) to
 * extract it in place and run migrations — the one thing a plain FTP upload can never do without
 * a shell.
 */
class DeployController extends Controller
{
    public function run()
    {
        $zipPath = base_path('deploy-payload.zip');

        if (! file_exists($zipPath)) {
            return response()->json(['status' => 'error', 'message' => 'deploy-payload.zip not found'], 404);
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            return response()->json(['status' => 'error', 'message' => 'Failed to open deploy-payload.zip'], 500);
        }

        // Previously unchecked — extractTo() returning false (e.g. one locked/permission-denied
        // file inside the archive) used to fall straight through to "status: ok" regardless,
        // silently leaving whichever files it couldn't write at their PRE-deploy content. CI's
        // own curl -f (see deploy.yml) turns this 500 into a visibly failed Action run instead.
        if (! $zip->extractTo(base_path())) {
            $zip->close();

            return response()->json(['status' => 'error', 'message' => 'ZipArchive::extractTo() failed — deploy payload was only partially extracted.'], 500);
        }

        $zip->close();
        unlink($zipPath);

        Artisan::call('optimize:clear');

        // optimize:clear above only clears Laravel's OWN caches (config/route/view/event) — it
        // never touches PHP's own OPcache (compiled bytecode), a completely separate, lower-
        // level cache. On shared hosting like Hostinger, a PHP-FPM worker can keep executing a
        // file's PRE-deploy compiled bytecode for a long time after the file itself has already
        // been overwritten on disk (confirmed live: a controller fix that changed nothing but
        // its own query logic kept returning its old, pre-fix numbers well after a "successful"
        // deploy). function_exists() guard since opcache isn't guaranteed to be loaded/enabled
        // in every environment (e.g. `php artisan serve` locally).
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = Artisan::output();

        return response()->json([
            'status' => 'ok',
            'migrate_output' => $migrateOutput,
        ]);
    }
}
