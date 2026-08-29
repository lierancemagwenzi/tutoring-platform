<?php

namespace App\Services\Commerce;

use App\Services\Admin\PlatformSettingService;

/**
 * Computes the Platform & Booking Fee — a percentage-based charge added on
 * top of a tutor's price and paid by the student, kept entirely as platform
 * revenue (unlike the platform commission, which is deducted from the
 * tutor's payout instead). Global-rate only, admin-configurable via
 * PlatformSettingService; unlike FinancialRule there is no per-tutor/
 * service override. Tutoring bookings only — never applied to course
 * purchases.
 */
class BookingFeeService
{
    public function __construct(private readonly PlatformSettingService $settings) {}

    /**
     * @return array{enabled: bool, percentage: float|null, amount: float}
     */
    public function calculate(float $price): array
    {
        $enabled = (bool) $this->settings->get('pricing.booking_fee_enabled');

        if (! $enabled) {
            return ['enabled' => false, 'percentage' => null, 'amount' => 0.0];
        }

        $percentage = (float) $this->settings->get('pricing.booking_fee_percentage');
        $amount = round($price * $percentage / 100, 2);

        return ['enabled' => true, 'percentage' => $percentage, 'amount' => $amount];
    }
}
