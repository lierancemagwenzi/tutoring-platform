<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Http\Requests\Admin\UpdateFaqRequest;
use App\Http\Resources\Admin\FaqResource;
use App\Models\Faq;
use App\Services\Admin\FaqManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function __construct(private readonly FaqManagementService $faqs) {}

    public function index(Request $request): JsonResponse
    {
        $faqs = $this->faqs->list(
            $request->only(['search', 'audience', 'is_published']),
            (int) $request->integer('per_page', 15),
        );

        return response()->json([
            'faqs' => FaqResource::collection($faqs->items()),
            'meta' => [
                'current_page' => $faqs->currentPage(),
                'last_page' => $faqs->lastPage(),
                'per_page' => $faqs->perPage(),
                'total' => $faqs->total(),
            ],
        ]);
    }

    public function store(StoreFaqRequest $request): JsonResponse
    {
        $faq = $this->faqs->create($request->validated());

        return response()->json(['faq' => new FaqResource($faq)], 201);
    }

    public function update(UpdateFaqRequest $request, Faq $faq): JsonResponse
    {
        $faq = $this->faqs->update($faq, $request->validated());

        return response()->json(['faq' => new FaqResource($faq)]);
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $this->faqs->delete($faq);

        return response()->json(['deleted' => true]);
    }
}
