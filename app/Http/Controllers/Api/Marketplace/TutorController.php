<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\IndexTutorsRequest;
use App\Http\Resources\Marketplace\TutorCardResource;
use App\Http\Resources\Marketplace\TutorProfileResource;
use App\Models\TutorProfile;
use Illuminate\Http\JsonResponse;

class TutorController extends Controller
{
    /**
     * Search, filter, sort, and paginate tutors who have at least one published service.
     */
    public function index(IndexTutorsRequest $request): JsonResponse
    {
        $query = TutorProfile::query()
            ->whereHas('publishedServices')
            ->with(['publishedServices.subject', 'publishedServices.grade'])
            ->withMin('publishedServices', 'price');

        if ($search = $request->validated('search')) {
            $query->where(function ($outer) use ($search) {
                $outer->where('display_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('publishedServices', function ($serviceQuery) use ($search) {
                        $serviceQuery->where('title', 'like', "%{$search}%")
                            ->orWhereHas('subject', fn ($subjectQuery) => $subjectQuery->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        if ($subjectId = $request->validated('subject_id')) {
            $query->whereHas('publishedServices', fn ($q) => $q->where('subject_id', $subjectId));
        }

        if ($gradeId = $request->validated('grade_id')) {
            $query->whereHas('publishedServices', fn ($q) => $q->where('grade_id', $gradeId));
        }

        if ($curriculumId = $request->validated('curriculum_id')) {
            $query->whereHas(
                'publishedServices',
                fn ($q) => $q->whereHas('curricula', fn ($q2) => $q2->where('id', $curriculumId)),
            );
        }

        if ($categoryId = $request->validated('service_category_id')) {
            $query->whereHas('publishedServices', fn ($q) => $q->where('service_category_id', $categoryId));
        }

        if ($formatId = $request->validated('session_format_id')) {
            $query->whereHas('publishedServices', fn ($q) => $q->where('session_format_id', $formatId));
        }

        if (($priceMin = $request->validated('price_min')) !== null) {
            $query->whereHas('publishedServices', fn ($q) => $q->where('price', '>=', $priceMin));
        }

        if (($priceMax = $request->validated('price_max')) !== null) {
            $query->whereHas('publishedServices', fn ($q) => $q->where('price', '<=', $priceMax));
        }

        if ($language = $request->validated('language')) {
            $query->whereJsonContains('languages', $language);
        }

        if (($yearsMin = $request->validated('years_experience_min')) !== null) {
            $query->where('years_experience', '>=', $yearsMin);
        }

        match ($request->validated('sort')) {
            'lowest_price' => $query->orderBy('published_services_min_price'),
            'highest_price' => $query->orderByDesc('published_services_min_price'),
            'most_experienced' => $query->orderByDesc('years_experience'),
            'alphabetical' => $query->orderBy('display_name'),
            default => $query->orderByDesc('created_at'),
        };

        $tutors = $query->paginate($request->validated('per_page') ?? 12);

        return response()->json([
            'tutors' => TutorCardResource::collection($tutors->items()),
            'meta' => [
                'current_page' => $tutors->currentPage(),
                'last_page' => $tutors->lastPage(),
                'per_page' => $tutors->perPage(),
                'total' => $tutors->total(),
            ],
        ]);
    }

    /**
     * Show a single tutor's public profile and published services.
     */
    public function show(TutorProfile $tutor): JsonResponse
    {
        $tutor->load([
            'user',
            'qualifications',
            'publishedServices.subject',
            'publishedServices.category',
            'publishedServices.sessionFormat',
            'publishedServices.learningResources',
            'publishedServices.assessmentTypes',
            'publishedServices.curricula',
        ]);

        return response()->json([
            'tutor' => new TutorProfileResource($tutor),
        ]);
    }
}
