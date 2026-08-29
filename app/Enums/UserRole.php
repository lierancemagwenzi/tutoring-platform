<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Tutor = 'tutor';
    case Admin = 'admin';
}
