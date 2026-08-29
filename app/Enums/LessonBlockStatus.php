<?php

namespace App\Enums;

enum LessonBlockStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
