<?php

namespace App\Services\H5p;

use App\Services\H5p\Framework\LaravelH5PEditorAjax;
use App\Services\H5p\Framework\LaravelH5PEditorStorage;
use App\Services\H5p\Framework\LaravelH5PFramework;

/**
 * Constructs the H5PCore/H5peditor/H5PValidator/H5PStorage instances the
 * rest of the H5P integration is built on — the PHP equivalent of what the
 * old Node h5p-server's src/h5p.ts createH5P() did.
 *
 * Storage is local disk for now (H5PDefaultStorage, rooted at
 * storage_path('app/h5p')) — swapping this for S3 on Laravel Cloud only
 * means changing storagePath()/the H5PDefaultStorage construction below,
 * everything else in the integration talks to H5PCore, not the filesystem
 * directly.
 */
class H5PKernel
{
    protected ?\H5PFrameworkInterface $framework = null;

    protected ?\H5PCore $core = null;

    protected ?\H5peditor $editor = null;

    public function framework(): \H5PFrameworkInterface
    {
        return $this->framework ??= new LaravelH5PFramework;
    }

    public function core(): \H5PCore
    {
        // $export stays false: this only controls whether H5PCore
        // auto-(re)generates a cached .h5p export file on every content
        // save (via filterParameters() -> H5PExport::createExportFile()).
        // The old Node service only ever exported on demand
        // (GET /api/content/:id/export), so H5PService::export() calls
        // H5PExport::createExportFile() itself instead of paying that cost
        // on every save.
        return $this->core ??= new \H5PCore(
            $this->framework(),
            $this->storagePath(),
            $this->assetBaseUrl(),
            'en',
            false,
        );
    }

    public function editor(): \H5peditor
    {
        return $this->editor ??= new \H5peditor(
            $this->core(),
            new LaravelH5PEditorStorage,
            new LaravelH5PEditorAjax,
        );
    }

    public function validator(): \H5PValidator
    {
        return new \H5PValidator($this->framework(), $this->core());
    }

    public function storage(): \H5PStorage
    {
        return new \H5PStorage($this->framework(), $this->core());
    }

    public function export(): \H5PExport
    {
        return new \H5PExport($this->framework(), $this->core());
    }

    public function contentValidator(): \H5PContentValidator
    {
        return new \H5PContentValidator($this->framework(), $this->core());
    }

    protected function storagePath(): string
    {
        return storage_path('app/h5p');
    }

    /**
     * Base URL H5PCore embeds into every asset/ajax URL it generates
     * (library files, content files, editor ajax actions) — must match the
     * route prefix Phase 2's ajax/file-serving routes are mounted under.
     */
    protected function assetBaseUrl(): string
    {
        return url('/h5p-assets');
    }
}
