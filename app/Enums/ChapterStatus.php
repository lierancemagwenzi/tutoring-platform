<?php

namespace App\Enums;

enum ChapterStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
