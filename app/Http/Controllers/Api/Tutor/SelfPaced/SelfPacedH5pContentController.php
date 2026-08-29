<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\StoreSelfPacedH5pContentRequest;
use App\Http\Resources\SelfPacedH5pContentResource;
use App\Services\SelfPaced\SelfPacedH5pContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfPacedH5pContentController extends Controller
{
    public function __construct(protected SelfPacedH5pContentService $h5pContents) {}

    /**
     * List the H5P content this tutor has tagged for self-paced course use
     * — never Tutor-Led Learning's H5P content, and never another tutor's.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'contents' => SelfPacedH5pContentResource::collection($this->h5pContents->forTutor($request->user()->tutorProfile)),
        ]);
    }

    /**
     * Tag a piece of H5P content as belonging to this tutor's self-paced
     * courses — called right after creating/editing it via the H5P editor
     * with ?context=self_paced.
     */
    public function store(StoreSelfPacedH5pContentRequest $request): JsonResponse
    {
        $content = $this->h5pContents->register(
            $request->user()->tutorProfile,
            $request->validated('h5p_content_id'),
            $request->validated('title'),
        );

        return response()->json(['content' => new SelfPacedH5pContentResource($content)], 201);
    }
}
