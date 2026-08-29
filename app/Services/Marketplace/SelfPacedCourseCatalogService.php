<?php

namespace App\Services\Marketplace;

use App\Enums\SelfPacedCourseStatus;
use App\Enums\SelfPacedCourseVisibility;
use App\Models\SelfPacedCourse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Marketplace's Self-Paced Courses catalogue — search, filter, sort,
 * and single-course lookup. Never exposes Draft/Private/Archived courses,
 * mirroring the existing Marketplace TutorController's "published services
 * only" scoping. Listings only ever show Public visibility; a direct link
 * (findPublished()) also allows Unlisted, matching what "unlisted" means.
 */
class SelfPacedCourseCatalogService
{
    /**
     * @var list<string>
     */
    private const WITH = ['subject', 'grade', 'tutorProfile', 'discountCodes'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        // Unlisted courses are reachable by direct link (findPublished())
        // but must never appear in the browsable catalogue — that's the
        // entire point of "unlisted".
        $query = $this->listableCourses()
            ->where('visibility', SelfPacedCourseVisibility::PublicVisibility)
            ->withCount('modules');

        if ($search = $filters['search'] ?? null) {
            $query->where(function (Builder $outer) use ($search) {
                $outer->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('subject', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('tutorProfile', function (Builder $q) use ($search) {
                        $q->where('display_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $uq) => $uq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%"));
                    });
            });
        }

        if ($subjectId = $filters['subject_id'] ?? null) {
            $query->where('subject_id', $subjectId);
        }

        if ($gradeId = $filters['grade_id'] ?? null) {
            $query->where('grade_id', $gradeId);
        }

        if ($difficulty = $filters['difficulty'] ?? null) {
            $query->where('difficulty', $difficulty);
        }

        if ($language = $filters['language'] ?? null) {
            $query->where('language', $language);
        }

        if ($tutorId = $filters['tutor_id'] ?? null) {
            $query->where('tutor_profile_id', $tutorId);
        }

        if (($price = $filters['price'] ?? null) === 'free') {
            $query->where(fn (Builder $q) => $q->whereNull('price')->orWhere('price', 0));
        } elseif ($price === 'paid') {
            $query->whereNotNull('price')->where('price', '>', 0);
        }

        if (($durationMax = $filters['duration_max'] ?? null) !== null) {
            $query->where('estimated_duration_minutes', '<=', $durationMax);
        }

        $this->applySort($query, $filters['sort'] ?? null);

        return $query->paginate($perPage);
    }

    /**
     * A single published, publicly-viewable course with everything its
     * details page needs — never returns a Draft/Private/Archived course,
     * nor one visibility-scoped away from students.
     */
    public function findPublished(int $id): ?SelfPacedCourse
    {
        return $this->listableCourses()
            ->whereIn('visibility', [SelfPacedCourseVisibility::PublicVisibility, SelfPacedCourseVisibility::Unlisted])
            ->with([
                'tutorProfile.qualifications',
                'tutorProfile.tutorSubjects.subject',
                'modules.activities',
                'modules.assessments',
            ])
            ->find($id);
    }

    /**
     * Distinct filter option values drawn only from courses students can
     * actually browse, so the filter panel never offers an option that
     * would return zero results.
     *
     * @return array{subjects: array<int, array{id: int, name: string}>, grades: array<int, array{id: int, name: string}>, languages: list<string>, tutors: array<int, array{id: int, display_name: string}>}
     */
    public function filterOptions(): array
    {
        $courses = $this->listableCourses()
            ->where('visibility', SelfPacedCourseVisibility::PublicVisibility)
            ->with(['subject', 'grade', 'tutorProfile'])
            ->get();

        return [
            'subjects' => $courses->pluck('subject')->filter()->unique('id')
                ->map(fn ($subject) => ['id' => $subject->id, 'name' => $subject->name])
                ->sortBy('name')->values()->all(),
            'grades' => $courses->pluck('grade')->filter()->unique('id')->sortBy('level')
                ->map(fn ($grade) => ['id' => $grade->id, 'name' => $grade->name])
                ->values()->all(),
            'languages' => $courses->pluck('language')->filter()->unique()->sort()->values()->all(),
            'tutors' => $courses->pluck('tutorProfile')->filter()->unique('id')
                ->map(fn ($tutor) => ['id' => $tutor->id, 'display_name' => $tutor->display_name])
                ->sortBy('display_name')->values()->all(),
        ];
    }

    /**
     * The common "students may see this at all" scope: Published status
     * only. Visibility is deliberately NOT filtered here, since it means
     * different things for a listing (Public only) versus a direct link
     * (Public or Unlisted) — callers apply that part themselves.
     */
    private function listableCourses(): Builder
    {
        return SelfPacedCourse::query()
            ->where('status', SelfPacedCourseStatus::Published)
            ->with(self::WITH);
    }

    private function applySort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_low' => $query->orderByRaw('COALESCE(price, 0) asc'),
            'price_high' => $query->orderByRaw('COALESCE(price, 0) desc'),
            'alphabetical' => $query->orderBy('title'),
            // 'most_popular' and 'highest_rated' are accepted now so the
            // sort dropdown can offer them, but there is no enrollment or
            // rating data yet to sort by — both fall back to newest until
            // that data exists.
            'most_popular', 'highest_rated', 'newest', null => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
    }
}
