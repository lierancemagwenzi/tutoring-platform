<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Published FAQs visible to the logged in user's role — tagged for
     * their specific role, or tagged "both".
     */
    public function index(Request $request): JsonResponse
    {
        $faqs = Faq::query()
            ->where('is_published', true)
            ->where(fn ($query) => $query->where('audience', $request->user()->role->value)->orWhere('audience', 'both'))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json([
            'faqs' => FaqResource::collection($faqs),
        ]);
    }
}
