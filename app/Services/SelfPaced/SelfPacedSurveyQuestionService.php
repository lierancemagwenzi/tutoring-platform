<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedSurveyContent;
use App\Models\SelfPacedSurveyQuestion;

class SelfPacedSurveyQuestionService
{
    /**
     * @param  array{type: string, text: string, points: int, choices?: array<int, string>, correct_answer?: mixed}  $data
     */
    public function create(SelfPacedSurveyContent $surveyContent, array $data): SelfPacedSurveyQuestion
    {
        return $surveyContent->questions()->create([
            'position' => $surveyContent->questions()->count(),
            'type' => $data['type'],
            'definition' => $this->buildDefinition($data),
            'points' => $data['points'],
        ]);
    }

    /**
     * @param  array{type: string, text: string, points: int, choices?: array<int, string>, correct_answer?: mixed}  $data
     */
    public function update(SelfPacedSurveyQuestion $question, array $data): SelfPacedSurveyQuestion
    {
        $question->update([
            'type' => $data['type'],
            'definition' => $this->buildDefinition($data),
            'points' => $data['points'],
        ]);

        return $question->fresh();
    }

    public function delete(SelfPacedSurveyQuestion $question): void
    {
        $question->delete();
    }

    /**
     * @param  list<int>  $questionIds
     */
    public function reorder(array $questionIds): void
    {
        foreach ($questionIds as $position => $questionId) {
            SelfPacedSurveyQuestion::whereKey($questionId)->update(['position' => $position]);
        }
    }

    /**
     * Assemble the SurveyJS-compatible question definition from the
     * flattened request fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildDefinition(array $data): array
    {
        return array_filter([
            'title' => $data['text'] ?? null,
            'choices' => $data['choices'] ?? null,
            'correctAnswer' => $data['correct_answer'] ?? null,
        ], fn ($value) => $value !== null);
    }
}
