<?php

namespace App\Http\Controllers\Api\H5p;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\H5p\H5PKernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Drives the bundled H5PEditorAjax dispatcher (vendor/h5p/h5p-editor/
 * h5peditor-ajax.class.php) instead of reimplementing its ~9 actions by
 * hand — that class is the same legacy-style ajax handler WordPress/Drupal
 * wire up: it prints JSON itself (via H5PCore::ajaxSuccess/ajaxError) and
 * reads $_POST/$_FILES directly, which works unmodified here since PHP
 * populates those superglobals for this request regardless of Laravel.
 *
 * Two of its code paths call exit() after printing an error response (see
 * libraryUpload()/filter() in that file). That's safe under PHP-FPM (each
 * request is its own process — Laravel Cloud's target here), but it does
 * skip Laravel's normal response/terminate lifecycle for that one request.
 * Forking the vendor package to remove those would defeat the point of
 * depending on the maintained library, so this is accepted as-is, matching
 * how every other H5P PHP integration embeds this same class.
 *
 * Wire-format note: several request field names below (library-install's
 * POST body, get-hub-content's args) are inferred from the PHP-side
 * action() switch positions, not confirmed against a live client — the
 * bundled h5p-editor client JS (vendor/h5p/h5p-editor/scripts/) is what
 * actually drives this once wired up in Phase 4, so treat these as
 * best-effort until verified against a real editor session in a browser.
 */
class H5pAjaxController extends Controller
{
    public function __construct(protected H5PKernel $kernel) {}

    public function action(Request $request, string $action): Response
    {
        // The bundled LIBRARIES action ignores any extra arguments and
        // always returns the full content-type list — but the shipped
        // editor client (h5peditor.js's loadLibrary()) also calls this same
        // action name to fetch one library's semantics, passing
        // machineName/majorVersion/minorVersion query params. The version of
        // h5p-editor installed here has no dedicated route matching that
        // request shape, so detect it here and call getLibraryData()
        // directly instead of going through the dispatcher.
        if ($action === 'libraries' && $request->filled('machineName')) {
            return $this->singleLibrary($request);
        }

        // Installing/uploading a content type pulls new library code onto
        // the server (from the Hub, or from an uploaded .h5p) — restrict to
        // tutors, same as every other authoring action in this app. The rest
        // of this route group only needs a logged-in session (see
        // routes/web.php) since students play H5P content too.
        if (in_array($action, ['library-install', 'library-upload'], true) && $request->user()?->role !== UserRole::Tutor) {
            abort(403, 'This action is only available to tutors.');
        }

        $ajax = $this->kernel->editor()->ajax;

        $args = match ($action) {
            'content-hub-metadata-cache' => [$request->query('lang', 'en')],
            'library-install' => [$request->input('token'), $request->input('id')],
            'library-upload' => $this->libraryUploadArgs($request),
            'files' => [$request->input('token'), $request->input('contentId')],
            'translations' => [$request->query('language', 'en')],
            'filter' => [$request->input('token'), $request->input('libraryParameters')],
            'get-hub-content' => [$request->input('token'), $request->input('id'), $request->input('contentId')],
            default => [],
        };

        ob_start();
        $ajax->action($action, ...$args);
        $body = ob_get_clean();

        return response($body, 200)->header('Content-Type', 'application/json; charset=utf-8');
    }

    protected function singleLibrary(Request $request): Response
    {
        $library = $this->kernel->editor()->getLibraryData(
            $request->query('machineName'),
            $request->query('majorVersion'),
            $request->query('minorVersion'),
            $request->query('language', 'en'),
            '',
            '',
            $request->query('default-language', ''),
        );

        return response()->json($library);
    }

    protected function libraryUploadArgs(Request $request): array
    {
        $token = $request->input('token');
        $contentId = $request->input('contentId');
        $upload = $request->file('h5p');

        if (! $upload) {
            return [$token, null, $contentId];
        }

        $path = $upload->getRealPath();

        return [$token, $path, $contentId];
    }
}
