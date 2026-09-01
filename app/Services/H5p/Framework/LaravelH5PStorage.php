<?php

namespace App\Services\H5p\Framework;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Extends the h5p/h5p-core package's H5PDefaultStorage (plain local
 * filesystem) with best-effort mirroring to config('h5p.storage_disk'), so
 * installed content-type libraries, tutor-created content, and exports
 * survive Laravel Cloud's ephemeral per-instance local disk. Local disk
 * stays the source of truth for every operation — parent:: runs first,
 * mirroring happens after and never throws, only logs, so a mirror hiccup
 * never breaks authoring. See H5PKernel::fileStorage() for wiring and the
 * plan doc referenced in that class for the overall design.
 */
class LaravelH5PStorage extends \H5PDefaultStorage
{
    protected string $path;

    protected ?string $alteditorpath;

    public function __construct($path, $alteditorpath = null)
    {
        parent::__construct($path, $alteditorpath);

        $this->path = $path;
        $this->alteditorpath = $alteditorpath;
    }

    public function saveLibrary($library)
    {
        parent::saveLibrary($library);

        $this->mirrorPutTree($this->path.'/libraries/'.\H5PCore::libraryToFolderName($library));
    }

    public function saveContent($source, $content)
    {
        parent::saveContent($source, $content);

        $this->mirrorPutTree("{$this->path}/content/{$content['id']}");
    }

    public function deleteContent($content)
    {
        parent::deleteContent($content);

        $this->mirrorDeleteTree("{$this->path}/content/{$content['id']}");
    }

    public function cloneContent($id, $newId)
    {
        parent::cloneContent($id, $newId);

        $this->mirrorPutTree($this->path.'/content/'.$newId);
    }

    public function saveExport($source, $filename)
    {
        parent::saveExport($source, $filename);

        $this->mirrorPutFile("{$this->path}/exports/{$filename}");
    }

    public function deleteExport($filename)
    {
        parent::deleteExport($filename);

        $this->mirrorDeleteFile("{$this->path}/exports/{$filename}");
    }

    public function cacheAssets(&$files, $key)
    {
        parent::cacheAssets($files, $key);

        $this->mirrorPutFile("{$this->path}/cachedassets/{$key}.js");
        $this->mirrorPutFile("{$this->path}/cachedassets/{$key}.css");
    }

    public function deleteCachedAssets($keys)
    {
        parent::deleteCachedAssets($keys);

        foreach ($keys as $hash) {
            $this->mirrorDeleteFile("{$this->path}/cachedassets/{$hash}.js");
            $this->mirrorDeleteFile("{$this->path}/cachedassets/{$hash}.css");
        }
    }

    public function saveFile($file, $contentId)
    {
        $saved = parent::saveFile($file, $contentId);

        $base = empty($contentId) ? $this->editorPath() : "{$this->path}/content/{$contentId}";
        $this->mirrorPutFile("{$base}/{$file->getType()}s/{$file->getName()}");

        return $saved;
    }

    public function cloneContentFile($file, $fromId, $toId)
    {
        parent::cloneContentFile($file, $fromId, $toId);

        $filename = basename($file);
        $filedir = str_replace($filename, '', $file);
        $this->mirrorPutFile("{$this->path}/content/{$toId}/{$filedir}{$filename}");
    }

    public function moveContentDirectory($source, $contentId = null)
    {
        parent::moveContentDirectory($source, $contentId);

        $target = ($contentId === null || $contentId == 0)
            ? $this->editorPath()
            : "{$this->path}/content/{$contentId}";
        $this->mirrorPutTree($target);
    }

    public function removeContentFile($file, $contentId)
    {
        parent::removeContentFile($file, $contentId);

        $this->mirrorDeleteFile("{$this->path}/content/{$contentId}/{$file}");
    }

    public function saveFileFromZip($path, $file, $stream)
    {
        $result = parent::saveFileFromZip($path, $file, $stream);

        $this->mirrorPutFile("{$path}/{$file}");

        return $result;
    }

    protected function editorPath(): string
    {
        return $this->alteditorpath !== null ? $this->alteditorpath : "{$this->path}/editor";
    }

    protected function mirrorDisk(): ?Filesystem
    {
        $disk = config('h5p.storage_disk');
        if (! $disk) {
            return null;
        }

        try {
            return Storage::disk($disk);
        } catch (\Throwable $e) {
            Log::warning('H5P mirror disk unavailable: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Translates an absolute local path under the H5P storage root into the
     * object key used on the mirror disk (prefixed "h5p/" so the bucket can
     * be shared with other app storage). Returns null for paths outside
     * that root, or under the server-side-only temp scratch dir.
     */
    protected function mirrorKey(string $absolutePath): ?string
    {
        $root = rtrim(str_replace('\\', '/', $this->path), '/').'/';
        $absolutePath = str_replace('\\', '/', $absolutePath);

        if (! str_starts_with($absolutePath, $root)) {
            return null;
        }

        $relative = substr($absolutePath, strlen($root));

        if (str_starts_with($relative, 'temp/')) {
            return null;
        }

        return 'h5p/'.$relative;
    }

    protected function mirrorPutFile(string $absolutePath): void
    {
        $disk = $this->mirrorDisk();
        $key = $disk ? $this->mirrorKey($absolutePath) : null;
        if (! $disk || ! $key || ! is_file($absolutePath)) {
            return;
        }

        try {
            $stream = fopen($absolutePath, 'r');
            $disk->put($key, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Throwable $e) {
            Log::warning("H5P mirror upload failed for {$key}: ".$e->getMessage());
        }
    }

    protected function mirrorPutTree(string $absoluteDir): void
    {
        if (! $this->mirrorDisk() || ! is_dir($absoluteDir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($items as $item) {
            if ($item->isFile()) {
                $this->mirrorPutFile($item->getPathname());
            }
        }
    }

    protected function mirrorDeleteFile(string $absolutePath): void
    {
        $disk = $this->mirrorDisk();
        $key = $disk ? $this->mirrorKey($absolutePath) : null;
        if (! $disk || ! $key) {
            return;
        }

        try {
            $disk->delete($key);
        } catch (\Throwable $e) {
            Log::warning("H5P mirror delete failed for {$key}: ".$e->getMessage());
        }
    }

    protected function mirrorDeleteTree(string $absoluteDir): void
    {
        $disk = $this->mirrorDisk();
        $key = $disk ? $this->mirrorKey($absoluteDir) : null;
        if (! $disk || ! $key) {
            return;
        }

        try {
            $disk->deleteDirectory($key);
        } catch (\Throwable $e) {
            Log::warning("H5P mirror delete failed for {$key}: ".$e->getMessage());
        }
    }
}
