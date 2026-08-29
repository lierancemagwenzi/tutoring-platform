<?php

namespace App\Enums;

enum SessionLessonBlockAvailabilityMode: string
{
    case AlwaysAvailable = 'always_available';
    case Immediate = 'immediate';
    case ManualRelease = 'manual_release';
    case ScheduledRelease = 'scheduled_release';
    case AssessmentWindow = 'assessment_window';
}
