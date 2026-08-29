<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ImportH5pContentRequest;
use App\Http\Requests\Tutor\StoreH5pContentRequest;
use App\Http\Requests\Tutor\UpdateH5pContentRequest;
use App\Http\Resources\H5pContentResource;
use App\Services\H5p\H5PService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * H5P content itself lives entirely on the dedicated H5P server (see
 * h5p-server/) — Laravel only brokers access to it. Every action here is
 * gated by the 'tutor' middleware; the H5P server has no concept of
 * per-tutor ownership, so (matching how content-type libraries are shared
 * platform-wide) H5P content is a library shared across all tutors, the
 * same way a duplicated H5P lesson block references rather than clones its
 * content. See App\Services\LessonBlocks\H5pBlockHandler for how a specific
 * piece of content gets attached to a lesson block.
 */
class H5pContentController extends Controller
{
    public function __construct(protected H5PService $h5p) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'contents' => H5pContentResource::collection(collect($this->h5p->listContent())),
        ]);
    }

    public function newEditorModel(): JsonResponse
    {
        return response()->json($this->h5p->newEditorModel());
    }

    public function editorModel(string $contentId): JsonResponse
    {
        return response()->json($this->h5p->editorModel($contentId));
    }

    public function playerModel(string $contentId): JsonResponse
    {
        return response()->json($this->h5p->playerModel($contentId));
    }

    public function store(StoreH5pContentRequest $request): JsonResponse
    {
        $result = $this->h5p->save(null, $this->toH5pPayload($request));

        return response()->json($result, 201);
    }

    public function update(UpdateH5pContentRequest $request, string $contentId): JsonResponse
    {
        return response()->json($this->h5p->save($contentId, $this->toH5pPayload($request)));
    }

    public function destroy(string $contentId): JsonResponse
    {
        $this->h5p->delete($contentId);

        return response()->json(['message' => 'H5P content deleted.']);
    }

    public function export(string $contentId): Response
    {
        $upstream = $this->h5p->export($contentId);

        return response($upstream->body(), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$contentId}.h5p\"",
        ]);
    }

    public function import(ImportH5pContentRequest $request): JsonResponse
    {
        $result = $this->h5p->importPackage($request->file('file'));

        return response()->json($result, 201);
    }

    /**
     * Translates <h5p-editor>'s save() payload shape ({ library, params:
     * { params, metadata } }) into the h5p-server REST API's shape
     * ({ mainLibraryUbername, parameters, metadata }).
     *
     * @return array<string, mixed>
     */
    protected function toH5pPayload(StoreH5pContentRequest|UpdateH5pContentRequest $request): array
    {
        return [
            'mainLibraryUbername' => $request->validated('library'),
            'parameters' => $request->validated('params.params'),
            'metadata' => $request->validated('params.metadata'),
        ];
    }
}
