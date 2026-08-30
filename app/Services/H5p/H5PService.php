<?php

namespace App\Services\H5p;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * In-process replacement for the old HTTP client to the Node h5p-server
 * (see the previous version of this file / h5p-server/ in git history) —
 * same public method signatures, so every caller (H5pContentController,
 * SelfPacedH5pContentController, SessionContentController,
 * SelfPacedAssessmentController, H5pBlockHandler) needed no changes.
 *
 * listContent()/get()/save()/delete() are built directly on H5PCore and are
 * solidly tested (see the tinker smoke test referenced in the migration
 * plan). editorModel()/playerModel() assemble the classic H5PIntegration
 * object the vendored core/editor client JS expects (window.H5PIntegration
 * + H5P.newRunnable()/H5PEditor.Editor()) — the general shape follows
 * documented H5P conventions, but exact fidelity (asset ordering, l10n
 * completeness, editor semantics panels) can only be confirmed by loading
 * the real editor/player in a browser, which is Phase 4's job.
 */
class H5PService
{
    public function __construct(protected H5PKernel $kernel) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listContent(): array
    {
        return $this->contentSummaryQuery()->get()->map($this->toSummary(...))->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $contentId): ?array
    {
        $row = $this->contentSummaryQuery()->where('c.id', $contentId)->first();

        return $row ? $this->toSummary($row) : null;
    }

    protected function contentSummaryQuery(): Builder
    {
        // No portable CONCAT across MySQL (prod) and SQLite (tests) via
        // selectRaw — build mainLibrary in PHP instead, see toSummary().
        return DB::table('h5p_content as c')
            ->join('h5p_libraries as l', 'l.id', '=', 'c.library_id')
            ->select('c.id', 'c.metadata_title as title', 'l.machine_name', 'l.major_version', 'l.minor_version', 'c.metadata_default_language as language');
    }

    /**
     * @return array<string, mixed>
     */
    protected function toSummary(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'title' => $row->title,
            'mainLibrary' => "{$row->machine_name} {$row->major_version}.{$row->minor_version}",
            'language' => $row->language,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function newEditorModel(): array
    {
        return $this->editorModel(null);
    }

    /**
     * @return array<string, mixed>
     */
    public function editorModel(?string $contentId): array
    {
        $core = $this->kernel->core();
        $content = $contentId !== null ? $core->loadContent($contentId) : null;

        return [
            'integration' => $this->baseIntegration() + [
                'editor' => $this->editorSettings(),
            ],
            'library' => $content ? \H5PCore::libraryToString($content['library']) : null,
            'params' => $content ? [
                'params' => json_decode($content['params']),
                'metadata' => $content['metadata'],
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function playerModel(string $contentId): array
    {
        $core = $this->kernel->core();
        $content = $core->loadContent($contentId);

        if ($content === null) {
            throw new \RuntimeException("H5P content {$contentId} not found.");
        }

        $content['filtered'] = $core->filterParameters($content);
        $files = $core->getDependenciesFiles($core->loadContentDependencies($contentId, 'preloaded'));

        return [
            'integration' => $this->baseIntegration() + [
                'contents' => [
                    "cid-{$contentId}" => [
                        'library' => \H5PCore::libraryToString($content['library']),
                        'jsonContent' => $content['filtered'],
                        'fullScreen' => $content['library']['fullscreen'] ?? false,
                        'exportUrl' => route('h5p.content.file', ['contentId' => $contentId, 'file' => 'export']),
                        'embedCode' => '',
                        'resizeCode' => '',
                        'url' => $core->url."/content/{$contentId}",
                        'contentUserData' => [0 => ['state' => '{}']],
                        'displayOptions' => [
                            'frame' => false,
                            'export' => false,
                            'embed' => false,
                            'copyright' => false,
                            'icon' => false,
                        ],
                        'metadata' => $content['metadata'],
                    ],
                ],
            ],
            // Core first: H5P.init()/H5P.jQuery/etc have to exist before any
            // content-dependency script runs. The player renders directly
            // into the host page (no isolating iframe, unlike the editor),
            // so it can't rely on something else having already loaded
            // these — that was true only by accident whenever a page also
            // happened to load the editor first. H5PCore::$styles are all
            // properly scoped (no bare body/html/* rules), unlike some of
            // the editor package's theme CSS, so this is safe to load
            // directly on the host page.
            'scripts' => [
                ...$this->assetUrls($core->url.'/core/', base_path('vendor/h5p/h5p-core'), \H5PCore::$scripts),
                ...$core->getAssetsUrls($files['scripts']),
            ],
            'styles' => [
                ...$this->assetUrls($core->url.'/core/', base_path('vendor/h5p/h5p-core'), \H5PCore::$styles),
                ...$core->getAssetsUrls($files['styles']),
            ],
        ];
    }

    /**
     * Create (when $contentId is null) or update a piece of H5P content.
     *
     * @param  array<string, mixed>  $payload  Must contain parameters, metadata and mainLibraryUbername.
     * @return array<string, mixed>
     */
    public function save(?string $contentId, array $payload): array
    {
        $core = $this->kernel->core();
        $library = \H5PCore::libraryFromString($payload['mainLibraryUbername']);
        $libraryId = $library
            ? $core->h5pF->getLibraryId($library['machineName'], $library['majorVersion'], $library['minorVersion'])
            : false;

        if (! $library || ! $libraryId) {
            throw new \RuntimeException("H5P library \"{$payload['mainLibraryUbername']}\" is not installed.");
        }

        $library['libraryId'] = $libraryId;

        $content = [
            'id' => $contentId,
            'params' => json_encode($payload['parameters']),
            'library' => $library,
            'metadata' => $payload['metadata'] ?? [],
            // filterParameters()'s generateContentSlug() reads this
            // top-level key, not metadata.title.
            'title' => $payload['metadata']['title'] ?? 'untitled',
            'disable' => 0,
        ];

        if ($contentId !== null) {
            $existing = $core->h5pF->loadContent($contentId);
            $content['slug'] = $existing['slug'] ?? null;
        } else {
            $content['slug'] = null;
        }

        $content['id'] = $core->saveContent($content);
        $core->filterParameters($content);

        return $this->get((string) $content['id']) + ['metadata' => $content['metadata']];
    }

    public function delete(string $contentId): void
    {
        $content = $this->kernel->core()->h5pF->loadContent($contentId);

        if ($content === null) {
            return;
        }

        $this->kernel->core()->h5pF->deleteLibraryUsage($contentId);
        $this->kernel->core()->h5pF->deleteContentData($contentId);
        $this->kernel->core()->fs->deleteContent(['id' => $contentId]);
    }

    public function export(string $contentId): StreamedResponse
    {
        $core = $this->kernel->core();
        $content = $core->loadContent($contentId);

        if ($content === null) {
            throw new \RuntimeException("H5P content {$contentId} not found.");
        }

        // loadContent() already populates 'filtered' from the cached DB
        // column, which makes filterParameters() take its "already filtered"
        // early-return path — but that path skips setting 'dependencies',
        // which createExportFile() below requires. Clear it so
        // filterParameters() re-validates and populates dependencies fresh.
        $content['filtered'] = null;
        $content['filtered'] = $core->filterParameters($content);

        $export = $this->kernel->export();
        if (! $export->createExportFile($content)) {
            throw new \RuntimeException(
                "Failed to create export file for H5P content {$contentId}: ".
                implode('; ', $this->kernel->framework()->getMessages('error'))
            );
        }

        $filename = $content['slug'].'-'.$contentId.'.h5p';
        $path = storage_path('app/h5p/exports/'.$filename);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, $contentId.'.h5p', ['Content-Type' => 'application/octet-stream']);
    }

    /**
     * @return array<string, mixed>
     */
    public function importPackage(UploadedFile $file): array
    {
        $framework = $this->kernel->framework();
        $uploadPath = $framework->getUploadedH5pPath();
        $file->move(dirname($uploadPath), basename($uploadPath));

        $validator = $this->kernel->validator();
        if (! $validator->isValidPackage(false, false)) {
            throw new \RuntimeException('The uploaded package did not contain valid content: '.
                implode('; ', $framework->getMessages('error')));
        }

        $core = $this->kernel->core();
        $mainJson = $core->mainJsonData;
        $metadataFields = ['title', 'authors', 'source', 'license', 'licenseVersion', 'licenseExtras', 'yearFrom', 'yearTo', 'changes', 'authorComments', 'defaultLanguage'];
        $metadata = array_intersect_key($mainJson, array_flip($metadataFields));

        $storage = $this->kernel->storage();
        $storage->savePackage(['metadata' => $metadata]);

        return $this->get((string) $storage->contentId) + ['metadata' => $metadata];
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseIntegration(): array
    {
        $core = $this->kernel->core();

        return [
            'baseUrl' => url('/'),
            'url' => $core->url,
            'siteUrl' => url('/'),
            // Without this, h5peditor-library-selector.js always falls back
            // to ns.SelectorLegacy (a bare <select> of installed libraries)
            // instead of ns.SelectorHub — the actual visual content-type
            // gallery (icons, screenshots, "Recommended" badges) the old
            // Node service showed. The content-type-cache ajax action this
            // needs was already built and tested; this was the one flag
            // missing to actually use it.
            'hubIsEnabled' => true,
            'postUserStatistics' => false,
            'ajax' => [
                'setFinished' => '',
                'contentUserData' => '',
            ],
            'saveFreq' => false,
            'core' => [
                'scripts' => $this->assetUrls($core->url.'/core/', base_path('vendor/h5p/h5p-core'), \H5PCore::$scripts),
                'styles' => $this->assetUrls($core->url.'/core/', base_path('vendor/h5p/h5p-core'), \H5PCore::$styles),
            ],
            // h5p.js's H5P.t() reads these directly (H5PIntegration.l10n.H5P)
            // with no built-in English fallback — the exact key set every
            // H5P.t('key') call site in vendor/h5p/h5p-core/js/*.js needs.
            'l10n' => [
                'H5P' => [
                    'fullscreen' => 'Fullscreen',
                    'disableFullscreen' => 'Disable fullscreen',
                    'download' => 'Download',
                    'copyrightInformation' => 'Rights of use',
                    'contentCopied' => 'Content is copied to the clipboard',
                    'connectionLost' => 'Connection lost. Results will be stored and sent when you regain connection.',
                    'connectionReestablished' => 'Connection reestablished.',
                    'resubmitScores' => 'Attempting to submit stored results.',
                    'offlineDialogHeader' => 'Your connection to the server was lost',
                    'offlineDialogBody' => 'We were unable to send information about your completion of this task. Please check your internet connection.',
                    'offlineDialogRetryMessage' => 'Retrying in :num....',
                    'offlineDialogRetryButtonLabel' => 'Retry now',
                    'offlineSuccessfulSubmit' => 'Successfully submitted results.',
                    'size' => 'Size',
                    'showAdvanced' => 'Show advanced',
                    'hideAdvanced' => 'Hide advanced',
                    'advancedHelp' => 'Include this text if you\'re embedding in a rich text editor',
                    'embed' => 'Embed',
                    'copyrightRestrictions' => 'Copyright restrictions',
                    'clipboardHeader' => 'Copy content',
                    'contentChanged' => 'This content has changed since you last used it.',
                    'startingOver' => 'You\'ll be starting over.',
                    'confirmDialogHeader' => 'Confirm action',
                    'confirmDialogBody' => 'Please confirm that you wish to proceed. This action is not reversible.',
                    'cancelLabel' => 'Cancel',
                    'confirmLabel' => 'Confirm',
                    'licenseU' => 'Undisclosed',
                    'licenseCCBY' => 'Attribution',
                    'licenseCCBYSA' => 'Attribution-ShareAlike',
                    'licenseCCBYND' => 'Attribution-NoDerivs',
                    'licenseCCBYNC' => 'Attribution-NonCommercial',
                    'licenseCCBYNCSA' => 'Attribution-NonCommercial-ShareAlike',
                    'licenseCCBYNCND' => 'Attribution-NonCommercial-NoDerivs',
                    'licenseCC40' => 'International 4.0',
                    'licenseCC30' => 'Unported 3.0',
                    'licenseCC25' => 'Generic 2.5',
                    'licenseCC20' => 'Generic 2.0',
                    'licenseCC10' => 'Generic 1.0',
                    'licenseGPL' => 'General Public License',
                    'licensePD' => 'Public Domain',
                    'licenseCC010' => 'CC0 1.0 Universal (CC0 1.0) Public Domain Dedication',
                    'licensePDM' => 'Public Domain Mark',
                    'licenseC' => 'Copyright',
                    'contentType' => 'Content Type',
                    'licenseExtras' => 'License Extras',
                    'changes' => 'Changelog',
                    'contentCopyrightWarning' => 'Content is copyright protected and cannot be reused.',
                    'contentCopyrightWarningPlural' => 'Some of the content is copyright protected and cannot be reused.',
                    'contentCopyrightUndisclosed' => 'Content copyright is undisclosed.',
                    'connectionLostFrom' => 'Connection lost for :contentType. Results will be stored and sent when you regain connection.',
                    'copyLabel' => 'Copy',
                    'pasteLabel' => 'Paste',
                    'noCopyrightsLabel' => 'No copyright information available for this content.',
                    'downloadDescription' => 'Download this content as a H5P file.',
                    'copyrightsDescription' => 'View copyright information for this content.',
                    'embedDescription' => 'View the embed code for this content.',
                    'h5pDescription' => 'Visit H5P.org to check out more cool content.',
                    'reuseContent' => 'Reuse Content',
                    'reuseDescription' => 'Reuse this content.',
                    'help' => 'Help',
                    'and' => 'and',
                    'feedback' => 'Feedback',
                    'sendFeedback' => 'Send Feedback',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function editorSettings(): array
    {
        $core = $this->kernel->core();

        return [
            'filesPath' => $core->url.'/content/editor',
            'fileIcon' => ['path' => $core->url.'/core/images/binary-file.png', 'width' => 50, 'height' => 50],
            'ajaxPath' => url('/h5p-assets/editor-ajax').'/',
            'libraryUrl' => $core->url.'/editor/',
            // Both come straight from H5PContentValidator — it ships the
            // real, complete versions (proper license-version cross-refs
            // per license type, etc). An earlier version of this method
            // hand-wrote an approximation of metadataSemantics before this
            // was found; that's gone now.
            'copyrightSemantics' => $this->kernel->contentValidator()->getCopyrightSemantics(),
            'metadataSemantics' => $this->kernel->contentValidator()->getMetadataSemantics(),
            // H5PEditor.Editor renders into a fresh <iframe> with its own JS
            // realm (see h5peditor-editor.js's populateIframe(), which writes
            // only H5PEditor.assets.js/.css into that iframe's <head>) — it
            // has no access to the parent page's already-loaded H5P core, so
            // these must include H5P core's own scripts/styles too, not just
            // the editor package's, core first (h5peditor.js depends on it).
            'assets' => [
                'js' => [
                    ...$this->assetUrls($core->url.'/core/', base_path('vendor/h5p/h5p-core'), \H5PCore::$scripts),
                    ...$this->assetUrls($core->url.'/editor/', base_path('vendor/h5p/h5p-editor'), \H5peditor::$scripts),
                    // H5PEditor.t('core', ...) reads H5PEditor.language.core,
                    // populated by this static script (`H5PEditor.language.core
                    // = {...}`) — not shipped as one of H5peditor::$scripts.
                    ...$this->assetUrls($core->url.'/editor/', base_path('vendor/h5p/h5p-editor'), ['language/en.js']),
                    // Defines H5PEditor.getAjaxUrl(), which h5peditor-editor.js's
                    // own iframe 'load' handler calls unconditionally — also
                    // not part of H5peditor::$scripts (platforms are expected
                    // to add it themselves).
                    ...$this->assetUrls($core->url.'/editor/', base_path('vendor/h5p/h5p-editor'), ['scripts/h5peditor-init.js']),
                ],
                'css' => [
                    ...$this->assetUrls($core->url.'/core/', base_path('vendor/h5p/h5p-core'), \H5PCore::$styles),
                    ...$this->assetUrls($core->url.'/editor/', base_path('vendor/h5p/h5p-editor'), \H5peditor::$styles),
                ],
            ],
            'deleteMessage' => 'Are you sure you wish to delete this content?',
            'apiVersion' => \H5PCore::$coreApi,
            'nodeVersionId' => null,
        ];
    }

    /**
     * Appends a filemtime()-based cache-busting query param to each URL —
     * without it, a browser that already cached one of these (e.g. from
     * before a Content-Type fix like the one that motivated adding this)
     * has no reason to ever refetch it: these are served with
     * Cache-Control: immutable, and unlike H5PCore's own library asset URLs
     * (which already carry a library ?ver=... query string), URLs built
     * here never changed when the underlying file did.
     *
     * @param  string[]  $paths
     * @return string[]
     */
    protected function assetUrls(string $base, string $diskRoot, array $paths): array
    {
        return array_map(function ($path) use ($base, $diskRoot) {
            $mtime = @filemtime($diskRoot.'/'.$path) ?: null;

            return $base.$path.($mtime ? '?v='.$mtime : '');
        }, $paths);
    }
}
