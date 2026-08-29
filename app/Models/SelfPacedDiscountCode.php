<?php

namespace App\Models;

use App\Enums\SelfPacedDiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SelfPacedDiscountCode extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'self_paced_course_id',
        'code',
        'discount_type',
        'discount_value',
        'max_redemptions',
        'times_redeemed',
        'starts_at',
        'expires_at',
        'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => SelfPacedDiscountType::class,
            'discount_value' => 'decimal:2',
            'max_redemptions' => 'integer',
            'times_redeemed' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /**
     * The course this discount code applies to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(SelfPacedCourse::class, 'self_paced_course_id');
    }

    /**
     * Whether this code could be redeemed right now — active, within its
     * date window, and under its redemption cap. Authoring-time preview
     * only; there is no checkout flow to actually redeem it yet.
     */
    public function isValidNow(): bool
    {
        if (! $this->active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->times_redeemed >= $this->max_redemptions) {
            return false;
        }

        return true;
    }

    /**
     * The price after this code is applied, floored at zero.
     */
    public function discountedPrice(float $price): float
    {
        $discounted = $this->discount_type === SelfPacedDiscountType::Percentage
            ? $price - ($price * (float) $this->discount_value / 100)
            : $price - (float) $this->discount_value;

        return round(max(0, $discounted), 2);
    }
}
