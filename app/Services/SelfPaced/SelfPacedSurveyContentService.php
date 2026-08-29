<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedSurveyContent;
use App\Models\TutorProfile;
use Illuminate\Database\Eloquent\Collection;

class SelfPacedSurveyContentService
{
    /**
     * @param  array{grade_id?: int, subject_id?: int, curriculum_id?: int}  $filters
     * @return Collection<int, SelfPacedSurveyContent>
     */
    public function forTutor(TutorProfile $tutor, array $filters = []): Collection
    {
        return $tutor->selfPacedSurveyContents()
            ->withCount('questions')
            ->with(['grade', 'subject', 'curriculum'])
            ->when(isset($filters['grade_id']), fn ($query) => $query->where('grade_id', $filters['grade_id']))
            ->when(isset($filters['subject_id']), fn ($query) => $query->where('subject_id', $filters['subject_id']))
            ->when(isset($filters['curriculum_id']), fn ($query) => $query->where('curriculum_id', $filters['curriculum_id']))
            ->latest()
            ->get();
    }

    public function create(TutorProfile $tutor, array $data): SelfPacedSurveyContent
    {
        return $tutor->selfPacedSurveyContents()->create($data)->fresh();
    }

    public function update(SelfPacedSurveyContent $surveyContent, array $data): SelfPacedSurveyContent
    {
        $surveyContent->update($data);

        return $surveyContent->fresh();
    }

    public function delete(SelfPacedSurveyContent $surveyContent): void
    {
        $surveyContent->delete();
    }
}
