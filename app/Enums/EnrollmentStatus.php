<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
