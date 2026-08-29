<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedCourse;
use App\Models\SelfPacedDiscountCode;

class SelfPacedDiscountCodeService
{
    public function create(SelfPacedCourse $course, array $data): SelfPacedDiscountCode
    {
        $data['code'] = strtoupper($data['code']);

        return $course->discountCodes()->create($data)->fresh();
    }

    public function update(SelfPacedDiscountCode $discountCode, array $data): SelfPacedDiscountCode
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $discountCode->update($data);

        return $discountCode->fresh();
    }

    public function delete(SelfPacedDiscountCode $discountCode): void
    {
        $discountCode->delete();
    }

    /**
     * Authoring-time preview of what a code would do — there is no
     * checkout flow yet, so this never touches times_redeemed.
     *
     * @return array{valid: bool, message: ?string, original_price: ?string, discounted_price: ?string}
     */
    public function preview(SelfPacedCourse $course, string $code): array
    {
        $discountCode = $course->discountCodes()->whereRaw('UPPER(code) = ?', [strtoupper($code)])->first();

        if (! $discountCode) {
            return ['valid' => false, 'message' => 'No such code for this course.', 'original_price' => null, 'discounted_price' => null];
        }

        if (! $discountCode->isValidNow()) {
            return ['valid' => false, 'message' => 'This code is not currently valid.', 'original_price' => null, 'discounted_price' => null];
        }

        if ($course->price === null) {
            return ['valid' => false, 'message' => 'This course has no price set.', 'original_price' => null, 'discounted_price' => null];
        }

        $originalPrice = (float) $course->price;

        return [
            'valid' => true,
            'message' => null,
            // Formatted as fixed-2-decimal strings, not raw floats — a
            // binary float can't always represent a decimal amount exactly
            // (e.g. 249.99), and depending on the server's
            // serialize_precision setting, json_encode can print that as a
            // long floating-point artifact instead of the clean amount.
            'original_price' => number_format($originalPrice, 2, '.', ''),
            'discounted_price' => number_format($discountCode->discountedPrice($originalPrice), 2, '.', ''),
        ];
    }
}
