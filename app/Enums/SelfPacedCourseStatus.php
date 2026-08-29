<?php

namespace App\Enums;

enum SelfPacedCourseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case PrivateStatus = 'private';
    case Archived = 'archived';
}
