<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfills the mirror disk (see LaravelH5PStorage) with H5P files that
 * already exist on local disk from before config('h5p.storage_disk') was
 * turned on — LaravelH5PStorage only mirrors on new writes/deletes going
 * forward, it doesn't retroactively sync anything already there.
 */
class H5pSyncStorage extends Command
{
    protected $signature = 'h5p:sync-storage
        {--disk= : Target disk (defaults to config(h5p.storage_disk))}
        {--dry-run : List what would be uploaded without uploading}';

    protected $description = 'Mirror local H5P files (libraries, content, exports) to the remote storage disk';

    public function handle(): int
    {
        $diskName = $this->option('disk') ?: config('h5p.storage_disk');
        if (! $diskName) {
            $this->error('No target disk. Pass --disk=s3 or set H5P_STORAGE_DISK.');

            return self::FAILURE;
        }

        $root = storage_path('app/h5p');
        if (! is_dir($root)) {
            $this->error("Local directory not found: {$root}");

            return self::FAILURE;
        }

        $disk = Storage::disk($diskName);
        $uploaded = 0;
        $skipped = 0;
        $failed = 0;

        $this->info('Listing existing remote files...');
        $remote = [];
        foreach ($disk->allFiles('h5p') as $existing) {
            $remote[$existing] = true;
        }
        $this->info(count($remote).' remote files found.');

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $relative = ltrim(substr($item->getPathname(), strlen($root)), '/');

            // Server-side scratch space — not needed remotely.
            if (str_starts_with($relative, 'temp/')) {
                continue;
            }

            $key = 'h5p/'.$relative;

            try {
                if (isset($remote[$key])) {
                    $skipped++;

                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("would upload: {$key}");
                    $uploaded++;

                    continue;
                }

                $stream = fopen($item->getPathname(), 'r');
                $disk->put($key, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $uploaded++;

                if ($uploaded % 500 === 0) {
                    $this->info("{$uploaded} uploaded...");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("failed: {$key} — ".$e->getMessage());
            }
        }

        $this->info("Done. Uploaded: {$uploaded}, already up to date: {$skipped}, failed: {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
