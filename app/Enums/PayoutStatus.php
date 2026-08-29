<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Processing = 'processing';
    case Paid = 'paid';
    case OnHold = 'on_hold';
    case Adjusted = 'adjusted';
}
