<?php

namespace App\Enums;

enum SelfPacedDiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
