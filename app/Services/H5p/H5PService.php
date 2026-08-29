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
            'scripts' => $core->getAssetsUrls($files['scripts']),
            'styles' => $core->getAssetsUrls($files['styles']),
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
            'postUserStatistics' => false,
            'ajax' => [
                'setFinished' => '',
                'contentUserData' => '',
            ],
            'saveFreq' => false,
            'core' => [
                'scripts' => $this->assetUrls($core->url.'/core/', \H5PCore::$scripts),
                'styles' => $this->assetUrls($core->url.'/core/', \H5PCore::$styles),
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
            'copyrightSemantics' => null,
            'metadataSemantics' => null,
            'assets' => [
                'js' => $this->assetUrls($core->url.'/editor/', \H5peditor::$scripts),
                'css' => $this->assetUrls($core->url.'/editor/', \H5peditor::$styles),
            ],
            'deleteMessage' => 'Are you sure you wish to delete this content?',
            'apiVersion' => \H5PCore::$coreApi,
            'nodeVersionId' => null,
        ];
    }

    /**
     * @param  string[]  $paths
     * @return string[]
     */
    protected function assetUrls(string $base, array $paths): array
    {
        return array_map(fn ($path) => $base.$path, $paths);
    }
}
