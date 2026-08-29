<?php

namespace App\Enums;

enum FinancialFeeType: string
{
    case PlatformCommission = 'platform_commission';
    case PlatformFixed = 'platform_fixed';
    case ProviderFee = 'provider_fee';
}
