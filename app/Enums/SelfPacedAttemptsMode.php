<?php

namespace App\Enums;

enum SelfPacedAttemptsMode: string
{
    case Unlimited = 'unlimited';
    case Limited = 'limited';
}
