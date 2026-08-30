<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ImportH5pContentRequest;
use App\Http\Requests\Tutor\StoreH5pContentRequest;
use App\Http\Requests\Tutor\UpdateH5pContentRequest;
use App\Http\Resources\H5pContentResource;
use App\Models\H5pContentClassification;
use App\Services\H5p\H5PService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * H5P content is stored locally via the official H5P PHP libraries (see
 * App\Services\H5p\H5PService/H5PKernel) — this controller is a thin HTTP
 * layer over that, plus the ownership boundary H5P's own storage doesn't
 * have: every piece of content belongs to exactly one tutor (see
 * App\Models\H5pContentClassification), enforced here via authorizeOwner()
 * rather than in H5PService itself, since H5PService's core methods are
 * also used by student-facing playback (SessionContentController,
 * SelfPacedAssessmentController) where ownership doesn't apply. See
 * App\Services\LessonBlocks\H5pBlockHandler for the matching check when a
 * specific piece of content gets attached to a lesson block.
 */
class H5pContentController extends Controller
{
    public function __construct(protected H5PService $h5p) {}

    public function index(Request $request): JsonResponse
    {
        $contents = $this->h5p->listContent(
            $request->user()->tutorProfile->id,
            $request->only(['subject_id', 'grade_id', 'curriculum_id']),
        );

        return response()->json([
            'contents' => H5pContentResource::collection(collect($contents)),
        ]);
    }

    public function newEditorModel(): JsonResponse
    {
        return response()->json($this->h5p->newEditorModel());
    }

    public function editorModel(Request $request, string $contentId): JsonResponse
    {
        $this->authorizeOwner($request, $contentId);

        $model = $this->h5p->editorModel($contentId);
        $model['classification'] = $this->classificationPayload($contentId);

        return response()->json($model);
    }

    public function playerModel(Request $request, string $contentId): JsonResponse
    {
        $this->authorizeOwner($request, $contentId);

        return response()->json($this->h5p->playerModel($contentId));
    }

    public function store(StoreH5pContentRequest $request): JsonResponse
    {
        $result = $this->h5p->save(null, $this->toH5pPayload($request));

        H5pContentClassification::create([
            'h5p_content_id' => $result['id'],
            'tutor_profile_id' => $request->user()->tutorProfile->id,
            'grade_id' => $request->validated('grade_id'),
            'subject_id' => $request->validated('subject_id'),
            'curriculum_id' => $request->validated('curriculum_id'),
        ]);

        return response()->json($result, 201);
    }

    public function update(UpdateH5pContentRequest $request, string $contentId): JsonResponse
    {
        $this->authorizeOwner($request, $contentId);

        $result = $this->h5p->save($contentId, $this->toH5pPayload($request));

        H5pContentClassification::where('h5p_content_id', $contentId)->update([
            'grade_id' => $request->validated('grade_id'),
            'subject_id' => $request->validated('subject_id'),
            'curriculum_id' => $request->validated('curriculum_id'),
        ]);

        return response()->json($result);
    }

    public function destroy(Request $request, string $contentId): JsonResponse
    {
        $this->authorizeOwner($request, $contentId);

        $this->h5p->delete($contentId);

        return response()->json(['message' => 'H5P content deleted.']);
    }

    public function export(Request $request, string $contentId): StreamedResponse
    {
        $this->authorizeOwner($request, $contentId);

        return $this->h5p->export($contentId);
    }

    public function import(ImportH5pContentRequest $request): JsonResponse
    {
        $result = $this->h5p->importPackage($request->file('file'));

        H5pContentClassification::create([
            'h5p_content_id' => $result['id'],
            'tutor_profile_id' => $request->user()->tutorProfile->id,
            'grade_id' => $request->validated('grade_id'),
            'subject_id' => $request->validated('subject_id'),
            'curriculum_id' => $request->validated('curriculum_id'),
        ]);

        return response()->json($result, 201);
    }

    /**
     * H5P content has no owner at the storage layer — this is the local
     * ownership check every tutor-authoring action (besides create, where
     * the classification doesn't exist yet) must pass, matching the
     * abort_unless() idiom used elsewhere (see TutorSubjectController::destroy()).
     */
    protected function authorizeOwner(Request $request, string $contentId): void
    {
        $owned = H5pContentClassification::query()
            ->where('h5p_content_id', $contentId)
            ->where('tutor_profile_id', $request->user()->tutorProfile?->id)
            ->exists();

        abort_unless($owned, 403);
    }

    /**
     * @return array{grade_id: int, subject_id: int, curriculum_id: int}|null
     */
    protected function classificationPayload(string $contentId): ?array
    {
        $classification = H5pContentClassification::query()->where('h5p_content_id', $contentId)->first();

        return $classification ? [
            'grade_id' => $classification->grade_id,
            'subject_id' => $classification->subject_id,
            'curriculum_id' => $classification->curriculum_id,
        ] : null;
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
