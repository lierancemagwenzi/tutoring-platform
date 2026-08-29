<?php

namespace App\Enums;

enum RefundStatus: string
{
    case NotRefunded = 'not_refunded';
    case Refunded = 'refunded';
}
