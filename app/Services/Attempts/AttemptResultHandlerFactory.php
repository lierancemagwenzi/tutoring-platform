<?php

namespace App\Services\Attempts;

use App\Contracts\AttemptResultHandler;
use App\Enums\AttemptProvider;

class AttemptResultHandlerFactory
{
    /**
     * Resolve the concrete result handler for the given provider.
     */
    public static function make(AttemptProvider $provider): AttemptResultHandler
    {
        return match ($provider) {
            AttemptProvider::H5p => app(H5pAttemptResultHandler::class),
            AttemptProvider::SurveyJs => app(SurveyJsAttemptResultHandler::class),
        };
    }
}
