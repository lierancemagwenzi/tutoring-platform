<?php

namespace App\Enums;

enum FaqAudience: string
{
    case Student = 'student';
    case Tutor = 'tutor';
    case Both = 'both';
}
