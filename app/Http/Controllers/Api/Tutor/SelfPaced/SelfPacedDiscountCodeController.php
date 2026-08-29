<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\ManageSelfPacedDiscountCodeRequest;
use App\Http\Requests\Tutor\SelfPaced\PreviewSelfPacedDiscountCodeRequest;
use App\Http\Requests\Tutor\SelfPaced\StoreSelfPacedDiscountCodeRequest;
use App\Http\Requests\Tutor\SelfPaced\UpdateSelfPacedDiscountCodeRequest;
use App\Http\Resources\SelfPacedDiscountCodeResource;
use App\Models\SelfPacedCourse;
use App\Models\SelfPacedDiscountCode;
use App\Services\SelfPaced\SelfPacedDiscountCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfPacedDiscountCodeController extends Controller
{
    public function __construct(protected SelfPacedDiscountCodeService $discountCodes) {}

    /**
     * List a course's discount codes.
     */
    public function index(Request $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        abort_unless($selfPacedCourse->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'discount_codes' => SelfPacedDiscountCodeResource::collection($selfPacedCourse->discountCodes()->latest()->get()),
        ]);
    }

    /**
     * Create a new discount code for a course.
     */
    public function store(StoreSelfPacedDiscountCodeRequest $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        $discountCode = $this->discountCodes->create($selfPacedCourse, $request->validated());

        return response()->json(['discount_code' => new SelfPacedDiscountCodeResource($discountCode)], 201);
    }

    /**
     * Update a discount code.
     */
    public function update(UpdateSelfPacedDiscountCodeRequest $request, SelfPacedDiscountCode $selfPacedDiscountCode): JsonResponse
    {
        $discountCode = $this->discountCodes->update($selfPacedDiscountCode, $request->validated());

        return response()->json(['discount_code' => new SelfPacedDiscountCodeResource($discountCode)]);
    }

    /**
     * Delete a discount code.
     */
    public function destroy(ManageSelfPacedDiscountCodeRequest $request, SelfPacedDiscountCode $selfPacedDiscountCode): JsonResponse
    {
        $this->discountCodes->delete($selfPacedDiscountCode);

        return response()->json(['message' => 'Discount code deleted.']);
    }

    /**
     * Preview what a code would do against the course's current price —
     * authoring-time only, no redemption is recorded.
     */
    public function preview(PreviewSelfPacedDiscountCodeRequest $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        return response()->json($this->discountCodes->preview($selfPacedCourse, $request->validated('code')), 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
