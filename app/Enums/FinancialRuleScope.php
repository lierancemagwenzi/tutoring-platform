<?php

namespace App\Enums;

enum FinancialRuleScope: string
{
    case Global = 'global';
    case Tutor = 'tutor';
    case Service = 'service';
    case Course = 'course';
}
