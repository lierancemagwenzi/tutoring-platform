<?php

namespace App\Contracts;

use App\Models\SelfPacedAssessmentAttempt;

/**
 * The self-paced side's equivalent of AttemptResultHandler, scoped to
 * SelfPacedAssessmentAttempt instead of the Tutor-Led Attempt model — see
 * that interface's docblock for the shape rationale, which this mirrors
 * exactly.
 */
interface SelfPacedAttemptResultHandler
{
    /**
     * Parse a provider's raw completion payload into the normalized scoring
     * fields the attempt engine stores. Pass/fail is deliberately not
     * returned here — it's derived centrally by SelfPacedAssessmentAttemptService
     * from the Assessment's own passing_score.
     *
     * @param  array<string, mixed>  $rawResult
     * @return array{raw_score: float|null, max_score: float|null, percentage: float|null, provider_metadata: array<string, mixed>}
     */
    public function parseResult(SelfPacedAssessmentAttempt $attempt, array $rawResult): array;
}
