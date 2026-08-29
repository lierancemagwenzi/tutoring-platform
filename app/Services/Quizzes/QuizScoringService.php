<?php

namespace App\Services\Quizzes;

use App\Enums\QuizQuestionType;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;

class QuizScoringService
{
    /**
     * Score a quiz attempt against the given answers and persist the result.
     *
     * @param  array<int, array{question_id: int, answer: mixed}>  $answers
     */
    public function score(QuizAttempt $attempt, array $answers): void
    {
        $result = $this->calculateScore($attempt->quiz, $answers);

        foreach ($result['answers'] as $answerRow) {
            QuizAttemptAnswer::create([
                'quiz_attempt_id' => $attempt->id,
                ...$answerRow,
            ]);
        }

        $attempt->update([
            'score' => $result['score'],
            'max_score' => $result['max_score'],
        ]);
    }

    /**
     * Compare a set of answers against a quiz's questions and compute the
     * resulting score, without persisting anything. Shared by the
     * per-question QuizAttempt flow above and the generic Attempt Engine's
     * SurveyJsAttemptResultHandler, so scoring logic exists in exactly one
     * place regardless of which delivery context a quiz is attempted through.
     *
     * @param  array<int, array{question_id: int, answer: mixed}>  $answers
     * @return array{score: int, max_score: int, answers: list<array<string, mixed>>}
     */
    public function calculateScore(Quiz $quiz, array $answers): array
    {
        $questions = $quiz->questions->keyBy('id');
        $totalAwarded = 0;
        $answerRows = [];

        foreach ($answers as $answerInput) {
            $question = $questions->get($answerInput['question_id']);

            if (! $question) {
                continue;
            }

            $type = QuizQuestionType::from($question->type);
            $isCorrect = null;
            $pointsAwarded = null;

            if ($type->isAutoScorable()) {
                $isCorrect = $this->isCorrect($type, $question->definition['correctAnswer'] ?? null, $answerInput['answer']);
                $pointsAwarded = $isCorrect ? $question->points : 0;
                $totalAwarded += $pointsAwarded;
            }

            $answerRows[] = [
                'quiz_question_id' => $question->id,
                'answer' => $answerInput['answer'],
                'is_correct' => $isCorrect,
                'points_awarded' => $pointsAwarded,
            ];
        }

        return [
            'score' => $totalAwarded,
            'max_score' => $questions->sum('points'),
            'answers' => $answerRows,
        ];
    }

    /**
     * Compare a submitted answer to the question's correct answer.
     */
    private function isCorrect(QuizQuestionType $type, mixed $correctAnswer, mixed $given): bool
    {
        if ($type === QuizQuestionType::MultipleChoice) {
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
