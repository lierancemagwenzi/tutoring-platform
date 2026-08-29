<?php

namespace App\Http\Controllers\Api\H5p;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the static files H5PCore/H5peditor point the browser at directly:
 * installed library assets (JS/CSS/semantics/images shipped inside each
 * content type) and per-content files (images/audio/video/etc uploaded into
 * a piece of content). Both live under storage_path('app/h5p/...') — see
 * H5PKernel::storagePath() and H5PDefaultStorage's folder conventions
 * (H5PCore::libraryToFolderName() for the library folder name).
 */
class H5pAssetController extends Controller
{
    public function library(string $library, string $file): StreamedResponse|Response
    {
        return $this->stream($this->root().'/libraries/'.$library.'/'.$file);
    }

    public function content(string $contentId, string $file): StreamedResponse|Response
    {
        return $this->stream($this->root().'/content/'.$contentId.'/'.$file);
    }

    protected function stream(string $path): StreamedResponse|Response
    {
        $real = realpath($path);

        if ($real === false || ! str_starts_with($real, $this->root()) || ! is_file($real)) {
            return response('Not found', 404);
        }

        return response()->stream(function () use ($real) {
            readfile($real);
        }, 200, [
            'Content-Type' => mime_content_type($real) ?: 'application/octet-stream',
            'Content-Length' => filesize($real),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    protected function root(): string
    {
        return realpath(storage_path('app/h5p')) ?: storage_path('app/h5p');
    }
}
