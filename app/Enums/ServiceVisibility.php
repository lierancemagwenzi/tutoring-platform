<?php

namespace App\Enums;

enum ServiceVisibility: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
}
