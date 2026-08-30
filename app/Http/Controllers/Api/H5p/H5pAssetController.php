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

    /**
     * The H5P core client JS/CSS/fonts bundled inside the h5p/h5p-core
     * composer package itself (H5PCore::$scripts/$styles reference these as
     * paths relative to that package root) — not installed content, so this
     * reads straight from vendor/, not storage_path('app/h5p').
     */
    public function core(string $file): StreamedResponse|Response
    {
        return $this->stream(base_path('vendor/h5p/h5p-core').'/'.$file, base_path('vendor/h5p/h5p-core'));
    }

    /**
     * Same idea for the editor's own bundled scripts/styles/ckeditor/etc
     * (H5peditor::$scripts/$styles) from the h5p/h5p-editor package.
     */
    public function editor(string $file): StreamedResponse|Response
    {
        return $this->stream(base_path('vendor/h5p/h5p-editor').'/'.$file, base_path('vendor/h5p/h5p-editor'));
    }

    protected function stream(string $path, ?string $root = null): StreamedResponse|Response
    {
        $root ??= $this->root();
        $real = realpath($path);

        if ($real === false || ! str_starts_with($real, $root) || ! is_file($real)) {
            return response('Not found', 404);
        }

        return response()->stream(function () use ($real) {
            readfile($real);
        }, 200, [
            'Content-Type' => $this->mimeType($real),
            'Content-Length' => filesize($real),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * mime_content_type() guesses from file *content* (magic bytes), which
     * can't tell CSS or JS apart from plain text — it was serving both as
     * text/plain, which browsers silently refuse to apply as a stylesheet
     * or execute as a module script. Extension-based lookup is what every
     * static file server actually uses for exactly this reason.
     */
    protected function mimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => 'text/css',
            'js', 'mjs' => 'text/javascript',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            default => mime_content_type($path) ?: 'application/octet-stream',
        };
    }

    protected function root(): string
    {
        return realpath(storage_path('app/h5p')) ?: storage_path('app/h5p');
    }
}
