<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case Started = 'started';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
    case TimedOut = 'timed_out';
}
