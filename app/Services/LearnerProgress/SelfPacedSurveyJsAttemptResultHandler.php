<?php

namespace App\Services\LearnerProgress;

use App\Contracts\SelfPacedAttemptResultHandler;
use App\Enums\SelfPacedSurveyQuestionType;
use App\Models\SelfPacedAssessmentAttempt;
use App\Models\SelfPacedSurveyContent;

/**
 * Parses a SurveyJS `onComplete` result (a flat {question_<id>: answer} map)
 * against the Assessment's linked SelfPacedSurveyContent question bank.
 * Deliberately a self-contained duplicate of QuizScoringService's
 * answer-comparison rules rather than a shared dependency — SelfPacedSurveyQuestion
 * and Tutor-Led Learning's QuizQuestion happen to share an identical
 * {type, definition, points} shape today, but the two domains are kept
 * independent throughout this codebase on purpose (see SelfPacedCourse's
 * docblock), and scoring rules are cheap enough to duplicate safely.
 */
class SelfPacedSurveyJsAttemptResultHandler implements SelfPacedAttemptResultHandler
{
    /**
     * @param  array<string, mixed>  $rawResult
     * @return array{raw_score: float|null, max_score: float|null, percentage: float|null, provider_metadata: array<string, mixed>}
     */
    public function parseResult(SelfPacedAssessmentAttempt $attempt, array $rawResult): array
    {
        $surveyContentId = $attempt->assessment->provider_config['survey_content_id'] ?? null;
        $surveyContent = $surveyContentId ? SelfPacedSurveyContent::with('questions')->find($surveyContentId) : null;

        if (! $surveyContent) {
            return ['raw_score' => null, 'max_score' => null, 'percentage' => null, 'provider_metadata' => []];
        }

        $answers = [];
        foreach ($rawResult as $key => $value) {
            if (preg_match('/^question_(\d+)$/', (string) $key, $matches)) {
                $answers[(int) $matches[1]] = $value;
            }
        }

        $questions = $surveyContent->questions->keyBy('id');
        $totalAwarded = 0;
        $answerRows = [];

        foreach ($answers as $questionId => $given) {
            $question = $questions->get($questionId);

            if (! $question) {
                continue;
            }

            $isCorrect = null;
            $pointsAwarded = null;

            if ($question->type->isAutoScorable()) {
                $isCorrect = $this->isCorrect($question->type, $question->definition['correctAnswer'] ?? null, $given);
                $pointsAwarded = $isCorrect ? $question->points : 0;
                $totalAwarded += $pointsAwarded;
            }

            $answerRows[] = [
                'question_id' => $questionId,
                'answer' => $given,
                'is_correct' => $isCorrect,
                'points_awarded' => $pointsAwarded,
            ];
        }

        $maxScore = (float) $questions->sum('points');
        $percentage = $maxScore > 0 ? round($totalAwarded / $maxScore * 100, 2) : null;

        return [
            'raw_score' => (float) $totalAwarded,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'provider_metadata' => [
                'question_count' => count($answers),
                'answers' => $answerRows,
            ],
        ];
    }

    /**
     * Compare a submitted answer to the question's correct answer.
     */
    private function isCorrect(SelfPacedSurveyQuestionType $type, mixed $correctAnswer, mixed $given): bool
    {
        if ($type === SelfPacedSurveyQuestionType::MultipleChoice) {
            $expected = (array) $correctAnswer;
            $actual = (array) $given;
            sort($expected);
            sort($actual);

            return $expected === $actual;
        }

        return $this->normalize($correctAnswer) === $this->normalize($given);
    }

    /**
     * Normalize a scalar answer for case/whitespace-insensitive comparison.
     */
    private function normalize(mixed $value): mixed
    {
        return is_string($value) ? trim(mb_strtolower($value)) : $value;
    }
}
