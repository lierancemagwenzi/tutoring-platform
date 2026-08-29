<?php

namespace App\Enums;

/**
 * The rendering/scoring engine an Assessment delegates to. The Assessment
 * itself owns every educational setting (passing score, attempts, time
 * limit, ...); a provider only owns how the questions are authored and
 * presented. Adding a future provider is one new case here plus a
 * provider_config shape — never a schema change.
 */
enum SelfPacedAssessmentProvider: string
{
    case SurveyJs = 'surveyjs';
    case H5p = 'h5p';
}
