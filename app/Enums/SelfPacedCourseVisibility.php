<?php

namespace App\Enums;

enum SelfPacedCourseVisibility: string
{
    case PublicVisibility = 'public';
    case PrivateVisibility = 'private';
    case Unlisted = 'unlisted';
}
