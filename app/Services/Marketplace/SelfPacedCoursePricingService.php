<?php

namespace App\Services\Marketplace;

use App\Models\SelfPacedCourse;
use App\Models\SelfPacedDiscountCode;
use Illuminate\Support\Collection;

/**
 * Resolves the marketplace-facing price of a self-paced course. A course
 * has no "sale price" field of its own — the discount shown is always the
 * best currently-valid SelfPacedDiscountCode for that course, computed
 * fresh rather than stored, so it never drifts from the code's own
 * validity window/redemption cap.
 */
class SelfPacedCoursePricingService
{
    /**
     * @return array{is_free: bool, currency: ?string, original_price: ?string, discount_percentage: ?float, discounted_price: ?string, savings: ?string}
     */
    public function resolve(SelfPacedCourse $course): array
    {
        $isFree = $course->price === null || (float) $course->price === 0.0;

        if ($isFree) {
            return [
                'is_free' => true,
                'currency' => $course->currency?->value,
                'original_price' => null,
                'discount_percentage' => null,
                'discounted_price' => null,
                'savings' => null,
            ];
        }

        $originalPrice = (float) $course->price;
        $bestCode = $this->bestActiveDiscount($course, $originalPrice);

        if (! $bestCode) {
            return [
                'is_free' => false,
                'currency' => $course->currency?->value,
                'original_price' => $this->money($originalPrice),
                'discount_percentage' => null,
                'discounted_price' => null,
                'savings' => null,
            ];
        }

        $discountedPrice = $bestCode->discountedPrice($originalPrice);

        return [
            'is_free' => false,
            'currency' => $course->currency?->value,
            'original_price' => $this->money($originalPrice),
            'discount_percentage' => $originalPrice > 0 ? round((($originalPrice - $discountedPrice) / $originalPrice) * 100, 2) : 0.0,
            'discounted_price' => $this->money($discountedPrice),
            'savings' => $this->money($originalPrice - $discountedPrice),
        ];
    }

    /**
     * The currently-valid discount code that gives the lowest price — a
     * course may have several codes (e.g. a lapsed launch promo alongside
     * an active one); only a valid one should ever affect display, and
     * only the best one when more than one is valid at once.
     */
    private function bestActiveDiscount(SelfPacedCourse $course, float $originalPrice): ?SelfPacedDiscountCode
    {
        /** @var Collection<int, SelfPacedDiscountCode> $codes */
        $codes = $course->discountCodes;

        return $codes
            ->filter(fn (SelfPacedDiscountCode $code) => $code->isValidNow())
            ->sortBy(fn (SelfPacedDiscountCode $code) => $code->discountedPrice($originalPrice))
            ->first();
    }

    /**
     * Formats a monetary value as a fixed-2-decimal string rather than a
     * raw float. A binary float can't always represent a decimal amount
     * exactly (e.g. 249.99), and depending on the server's
     * serialize_precision setting, json_encode can print that as a long
     * floating-point artifact (249.990000000000009...) instead of the
     * clean amount — the same reason Eloquent's own `decimal` cast
     * returns a string rather than a float.
     */
    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
