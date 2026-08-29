<?php

namespace App\Services\LearnerProgress;

use App\Contracts\SelfPacedAttemptResultHandler;
use App\Models\SelfPacedAssessmentAttempt;

/**
 * Parses an H5P xAPI completion statement (as emitted by the `xAPI` event
 * on @lumieducation/h5p-webcomponents' <h5p-player>) into normalized
 * scoring fields. Identical parsing logic to H5pAttemptResultHandler,
 * retyped to this domain's attempt model — see that class's docblock.
 */
class SelfPacedH5pAttemptResultHandler implements SelfPacedAttemptResultHandler
{
    /**
     * @param  array<string, mixed>  $rawResult
     * @return array{raw_score: float|null, max_score: float|null, percentage: float|null, provider_metadata: array<string, mixed>}
     */
    public function parseResult(SelfPacedAssessmentAttempt $attempt, array $rawResult): array
    {
        $statement = $rawResult['statement'] ?? $rawResult;
        $result = $statement['result'] ?? [];
        $score = $result['score'] ?? [];

        $rawScore = isset($score['raw']) ? (float) $score['raw'] : null;
        $maxScore = isset($score['max']) ? (float) $score['max'] : null;

        $percentage = match (true) {
            isset($score['scaled']) => round(((float) $score['scaled']) * 100, 2),
            $rawScore !== null && $maxScore > 0 => round($rawScore / $maxScore * 100, 2),
            default => null,
        };

        return [
            'raw_score' => $rawScore,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'provider_metadata' => [
                'verb' => $statement['verb']['id'] ?? null,
                'object_id' => $statement['object']['id'] ?? null,
                'completion' => $result['completion'] ?? null,
                'h5p_success' => $result['success'] ?? null,
            ],
        ];
    }
}
