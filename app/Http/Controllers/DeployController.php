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

        $zip->extractTo(base_path());
        $zip->close();
        unlink($zipPath);

        Artisan::call('optimize:clear');
        Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = Artisan::output();

        return response()->json([
            'status' => 'ok',
            'migrate_output' => $migrateOutput,
        ]);
    }
}
