<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedH5pContent;
use App\Models\TutorProfile;
use Illuminate\Database\Eloquent\Collection;

class SelfPacedH5pContentService
{
    /**
     * Every row here always has a matching h5p_content_classifications row
     * (created atomically alongside it — see H5pContentController), so
     * subject/grade filtering for the Assessment provider picker is a join
     * against that table rather than duplicating taxonomy columns here.
     *
     * @param  array{subject_id?: int, grade_id?: int}  $filters
     * @return Collection<int, SelfPacedH5pContent>
     */
    public function forTutor(TutorProfile $tutor, array $filters = []): Collection
    {
        return $tutor->selfPacedH5pContents()
            ->when(
                isset($filters['subject_id']) || isset($filters['grade_id']),
                fn ($query) => $query->whereIn('h5p_content_id', function ($subQuery) use ($filters) {
                    $subQuery->select('h5p_content_id')
                        ->from('h5p_content_classifications')
                        ->when(isset($filters['subject_id']), fn ($q) => $q->where('subject_id', $filters['subject_id']))
                        ->when(isset($filters['grade_id']), fn ($q) => $q->where('grade_id', $filters['grade_id']));
                }),
            )
            ->latest()
            ->get();
    }

    public function register(TutorProfile $tutor, string $h5pContentId, ?string $title): SelfPacedH5pContent
    {
        return $tutor->selfPacedH5pContents()->updateOrCreate(
            ['h5p_content_id' => $h5pContentId],
            ['title' => $title],
        );
    }
}
