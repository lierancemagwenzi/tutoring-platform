<?php

namespace App\Enums;

enum SessionLessonBlockCompletionMode: string
{
    case NotTracked = 'not_tracked';
    case Optional = 'optional';
    case Required = 'required';
}
