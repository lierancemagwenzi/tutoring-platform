<?php

namespace App\Services\Attempts;

use App\Contracts\AttemptResultHandler;
use App\Models\Attempt;
use App\Services\Quizzes\QuizScoringService;

/**
 * Parses a SurveyJS `onComplete` result (a flat {question_<id>: answer} map,
 * as built by the survey-core Model in the student player) against the
 * underlying Quiz's questions — reusing QuizScoringService::calculateScore()
 * rather than duplicating the answer-comparison rules.
 */
class SurveyJsAttemptResultHandler implements AttemptResultHandler
{
    public function __construct(private readonly QuizScoringService $scoringService) {}

    /**
     * @param  array<string, mixed>  $rawResult
     * @return array{raw_score: float|null, max_score: float|null, percentage: float|null, provider_metadata: array<string, mixed>}
     */
    public function parseResult(Attempt $attempt, array $rawResult): array
    {
        $quiz = $attempt->sessionLessonBlock->lessonBlock->quiz();

        if (! $quiz) {
            return ['raw_score' => null, 'max_score' => null, 'percentage' => null, 'provider_metadata' => []];
        }

        $answers = [];
        foreach ($rawResult as $key => $value) {
            if (preg_match('/^question_(\d+)$/', (string) $key, $matches)) {
                $answers[] = ['question_id' => (int) $matches[1], 'answer' => $value];
            }
        }

        $scored = $this->scoringService->calculateScore($quiz->load('questions'), $answers);
        $maxScore = (float) $scored['max_score'];
        $percentage = $maxScore > 0 ? round($scored['score'] / $maxScore * 100, 2) : null;

        return [
            'raw_score' => (float) $scored['score'],
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'provider_metadata' => [
                'question_count' => count($answers),
                'answers' => $scored['answers'],
            ],
        ];
    }
}
