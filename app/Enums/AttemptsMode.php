<?php

namespace App\Enums;

enum AttemptsMode: string
{
    case Unlimited = 'unlimited';
    case Limited = 'limited';
}
