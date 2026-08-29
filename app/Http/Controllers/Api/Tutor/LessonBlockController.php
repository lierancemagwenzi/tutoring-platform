<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\LessonBlockStatus;
use App\Enums\LessonBlockType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderLessonBlocksRequest;
use App\Http\Requests\Tutor\StoreLessonBlockRequest;
use App\Http\Requests\Tutor\UpdateLessonBlockRequest;
use App\Http\Resources\LessonBlockResource;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Services\LessonBlocks\LessonBlockHandlerFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonBlockController extends Controller
{
    /**
     * Return the blocks belonging to a lesson, in position order.
     */
    public function index(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'blocks' => LessonBlockResource::collection($lesson->blocks()->with('mediaItems')->get()),
        ]);
    }

    /**
     * Show a single content block belonging to the logged in tutor.
     */
    public function show(Request $request, LessonBlock $lessonBlock): JsonResponse
    {
        abort_unless(
            $lessonBlock->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        return response()->json([
            'block' => new LessonBlockResource($lessonBlock->load('mediaItems')),
        ]);
    }

    /**
     * Create a new content block for a lesson.
     */
    public function store(StoreLessonBlockRequest $request, Lesson $lesson): JsonResponse
    {
        $blockType = LessonBlockType::from($request->validated('block_type'));
        $handler = LessonBlockHandlerFactory::make($blockType);

        $block = $lesson->blocks()->create([
            'block_type' => $blockType,
            'title' => $request->validated('title'),
            'position' => $lesson->blocks()->count(),
            'content' => $handler->buildContent($request, null),
            'settings' => $request->validated('settings') ?? [],
            'status' => $request->validated('status') ?? LessonBlockStatus::Draft->value,
        ]);

        return response()->json([
            'block' => new LessonBlockResource($block->load('mediaItems')),
        ], 201);
    }

    /**
     * Update an existing content block.
     */
    public function update(UpdateLessonBlockRequest $request, LessonBlock $lessonBlock): JsonResponse
    {
        $blockType = LessonBlockType::from($request->validated('block_type'));
        $handler = LessonBlockHandlerFactory::make($blockType);

        $lessonBlock->update([
            'block_type' => $blockType,
            'title' => $request->validated('title'),
            'content' => $handler->buildContent($request, $lessonBlock),
            'settings' => $request->validated('settings') ?? [],
            'status' => $request->validated('status'),
        ]);

        return response()->json([
            'block' => new LessonBlockResource($lessonBlock->load('mediaItems')),
        ]);
    }

    /**
     * Delete a content block.
     */
    public function destroy(Request $request, LessonBlock $lessonBlock): JsonResponse
    {
        abort_unless(
            $lessonBlock->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        LessonBlockHandlerFactory::make($lessonBlock->block_type)->afterDelete($lessonBlock);

        $lessonBlock->delete();

        return response()->json([
            'message' => 'Block deleted.',
        ]);
    }

    /**
     * Duplicate a content block, including any owned sub-resources.
     */
    public function duplicate(Request $request, LessonBlock $lessonBlock): JsonResponse
    {
        abort_unless(
            $lessonBlock->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        $handler = LessonBlockHandlerFactory::make($lessonBlock->block_type);

        $copy = $lessonBlock->lesson->blocks()->create([
            'block_type' => $lessonBlock->block_type,
            'title' => $lessonBlock->title ? "{$lessonBlock->title} (Copy)" : null,
            'position' => $lessonBlock->lesson->blocks()->count(),
            'content' => [],
            'settings' => $lessonBlock->settings,
            'status' => LessonBlockStatus::Draft->value,
        ]);

        $copy->update(['content' => $handler->duplicateContent($lessonBlock, $copy)]);

        return response()->json([
            'block' => new LessonBlockResource($copy->load('mediaItems')),
        ], 201);
    }

    /**
     * Persist the drag-and-drop order of a lesson's blocks.
     */
    public function reorder(ReorderLessonBlocksRequest $request, Lesson $lesson): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('block_ids') as $position => $blockId) {
                LessonBlock::whereKey($blockId)->update(['position' => $position]);
            }
        });

        return response()->json([
            'blocks' => LessonBlockResource::collection($lesson->blocks()->with('mediaItems')->get()),
        ]);
    }
}
