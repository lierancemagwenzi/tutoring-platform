<?php

namespace App\Services\LearnerProgress;

use App\Contracts\SelfPacedAttemptResultHandler;
use App\Enums\SelfPacedAssessmentProvider;

class SelfPacedAttemptResultHandlerFactory
{
    /**
     * Resolve the concrete result handler for the given provider.
     */
    public static function make(SelfPacedAssessmentProvider $provider): SelfPacedAttemptResultHandler
    {
        return match ($provider) {
            SelfPacedAssessmentProvider::H5p => app(SelfPacedH5pAttemptResultHandler::class),
            SelfPacedAssessmentProvider::SurveyJs => app(SelfPacedSurveyJsAttemptResultHandler::class),
        };
    }
}
