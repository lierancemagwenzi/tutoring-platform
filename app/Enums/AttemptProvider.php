<?php

namespace App\Enums;

/**
 * The interactive content provider an Attempt was made against. Adding a
 * future provider (e.g. Judge0) is: one new case here, one new
 * AttemptResultHandler, and one line in AttemptResultHandlerFactory —
 * nothing else in the Attempt Engine needs to change.
 */
enum AttemptProvider: string
{
    case H5p = 'h5p';
    case SurveyJs = 'surveyjs';
}
