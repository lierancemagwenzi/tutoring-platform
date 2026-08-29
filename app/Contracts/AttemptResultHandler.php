<?php

namespace App\Contracts;

use App\Models\Attempt;

interface AttemptResultHandler
{
    /**
     * Parse a provider's raw completion payload into the normalized scoring
     * fields the Attempt Engine stores. Pass/fail is deliberately not
     * returned here — it's derived centrally by AttemptService from the
     * Session Lesson Block's own Passing Score, so no handler duplicates
     * that rule.
     *
     * @param  array<string, mixed>  $rawResult
     * @return array{raw_score: float|null, max_score: float|null, percentage: float|null, provider_metadata: array<string, mixed>}
     */
    public function parseResult(Attempt $attempt, array $rawResult): array;
}
