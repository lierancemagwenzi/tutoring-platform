<?php

namespace App\Services\Admin;

use App\Enums\SubjectStatus;
use App\Enums\TutorSubjectStatus;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Platform subject taxonomy management. Subjects are never hard-deleted —
 * archiving is the terminal state, keeping historical relationships
 * (services, courses, tutor subject requests) intact.
 */
class SubjectManagementService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    /**
     * @param  array{search?: string, status?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Subject::query()
            ->withCount([
                'tutorSubjects as approved_tutors_count' => fn ($q) => $q->where('status', TutorSubjectStatus::Approved),
                'tutorSubjects as pending_requests_count' => fn ($q) => $q->where('status', TutorSubjectStatus::Pending),
                'services',
                'selfPacedCourses',
            ]);

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(Subject $subject): array
    {
        $subject->loadCount([
            'tutorSubjects as approved_tutors_count' => fn ($q) => $q->where('status', TutorSubjectStatus::Approved),
            'tutorSubjects as pending_requests_count' => fn ($q) => $q->where('status', TutorSubjectStatus::Pending),
            'services',
            'selfPacedCourses',
        ]);

        $activeOfferingsCount = $subject->services()->where('visibility', 'published')->count()
            + $subject->selfPacedCourses()->where('status', 'published')->count();

        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'slug' => $subject->slug,
            'status' => $subject->status->value,
            'approved_tutors_count' => $subject->approved_tutors_count,
            'pending_requests_count' => $subject->pending_requests_count,
            'services_count' => $subject->services_count,
            'self_paced_courses_count' => $subject->self_paced_courses_count,
            'active_offerings_count' => $activeOfferingsCount,
            'approved_tutors' => $subject->tutorSubjects()
                ->where('status', TutorSubjectStatus::Approved)
                ->with('tutorProfile.user')
                ->get()
                ->map(fn ($ts) => [
                    'tutor_profile_id' => $ts->tutor_profile_id,
                    'display_name' => $ts->tutorProfile->display_name,
                    'email' => $ts->tutorProfile->user->email,
                ])->values(),
        ];
    }

    /**
     * @param  array{name: string, slug?: string, description?: string}  $data
     */
    public function create(array $data): Subject
    {
        return Subject::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => SubjectStatus::Active,
        ]);
    }

    /**
     * @param  array{name?: string, description?: string}  $data
     */
    public function update(Subject $subject, array $data): Subject
    {
        $subject->update([
            'name' => $data['name'] ?? $subject->name,
            'description' => $data['description'] ?? $subject->description,
        ]);

        return $subject;
    }

    public function setStatus(Subject $subject, SubjectStatus $status, User $actor): Subject
    {
        $from = $subject->status;
        $subject->update(['status' => $status]);

        $this->logger->log(
            $actor,
            'subject.status_changed',
            $subject,
            "Subject \"{$subject->name}\" status changed from {$from->value} to {$status->value}.",
            ['from' => $from->value, 'to' => $status->value],
        );

        return $subject;
    }
}
