<?php

namespace App\Services\Auth;

/**
 * Decides whether a tutor applicant's measured connection meets the
 * registration minimums in config/tutors.php. The measurement itself runs
 * in the applicant's browser (resources/js/services/speedTest.js); the
 * verdict is made here so the requirements live in one place.
 */
class TutorSpeedTestEligibility
{
    /**
     * The registration minimums, as shown to the applicant.
     *
     * @return array{min_download_mbps: float, min_upload_mbps: float, max_latency_ms: float, max_jitter_ms: float}
     */
    public function requirements(): array
    {
        return config('tutors.speed_test');
    }

    /**
     * List a human-readable reason for each requirement the measurements miss.
     *
     * @param  array{download_mbps: float, upload_mbps: float, latency_ms: float, jitter_ms?: float|null}  $metrics
     * @return list<string>
     */
    public function failures(array $metrics): array
    {
        $requirements = $this->requirements();
        $failures = [];

        if ($metrics['download_mbps'] < $requirements['min_download_mbps']) {
            $failures[] = sprintf(
                'Download speed was %s Mbps — at least %s Mbps is needed to receive your learner’s video.',
                $this->format($metrics['download_mbps']),
                $this->format($requirements['min_download_mbps']),
            );
        }

        if ($metrics['upload_mbps'] < $requirements['min_upload_mbps']) {
            $failures[] = sprintf(
                'Upload speed was %s Mbps — at least %s Mbps is needed to send your camera and screen share.',
                $this->format($metrics['upload_mbps']),
                $this->format($requirements['min_upload_mbps']),
            );
        }

        if ($metrics['latency_ms'] > $requirements['max_latency_ms']) {
            $failures[] = sprintf(
                'Response time was %s ms — it needs to be %s ms or less so conversations don’t lag.',
                $this->format($metrics['latency_ms']),
                $this->format($requirements['max_latency_ms']),
            );
        }

        if (($metrics['jitter_ms'] ?? null) !== null && $metrics['jitter_ms'] > $requirements['max_jitter_ms']) {
            $failures[] = sprintf(
                'Connection stability (jitter) was %s ms — it needs to be %s ms or less for smooth video.',
                $this->format($metrics['jitter_ms']),
                $this->format($requirements['max_jitter_ms']),
            );
        }

        return $failures;
    }

    private function format(float $value): string
    {
        return $value >= 10 ? (string) round($value) : rtrim(rtrim(number_format($value, 1), '0'), '.');
    }
}
