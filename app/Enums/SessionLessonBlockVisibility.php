<?php

namespace App\Enums;

enum SessionLessonBlockVisibility: string
{
    case Hidden = 'hidden';
    case Visible = 'visible';
    case Scheduled = 'scheduled';
}
