<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CleanupFiles extends Command
{
    protected $signature = 'partshelf:files:cleanup';

    protected $description = 'Remove expired temporary files and unreferenced image candidates after a 24h grace period';

    public function handle(): int
    {
        $failed = false;
        DB::table('file_transfers')->where('expires_at', '<', now()->subDay())->orderBy('id')->chunkById(100, function ($rows) use (&$failed) {
            foreach ($rows as $row) {
                try {
                    DB::transaction(function () use ($row) {
                        $file = DB::table('file_transfers')->where('id', $row->id)->lockForUpdate()->first();
                        if (! $file) {
                            return;
                        }
                        Storage::disk('r2')->delete($file->object_key);
                        if ($file->final_key && ! DB::table('products')->where('image_path', $file->final_key)->exists()) {
                            Storage::disk('r2')->delete($file->final_key);
                        }
                        // Keep image ledger for later replacement cleanup; no row needed for temporary-only files.
                        if (! $file->final_key || ! DB::table('products')->where('image_path', $file->final_key)->exists()) {
                            DB::table('file_transfers')->where('id', $file->id)->delete();
                        }
                    });
                } catch (\Throwable $exception) {
                    report($exception);
                    $failed = true;
                }
            }
        }, 'id');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
