<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An immutable snapshot of the commission split computed the moment a
 * payment was confirmed — see CommissionSnapshotService. The commission
 * amounts (gross/fees/splits) never change after creation: if a
 * FinancialRule changes later, past transactions keep the amounts that
 * were true at the time they were paid. The payout tracking fields
 * (payout_status/paid_at/paid_by) and refund tracking fields
 * (refund_status/refunded_at/refunded_by) are the legitimate exceptions —
 * see PayoutService and RefundService — since whether the tutor has been
 * paid out, or the transaction later refunded, is necessarily determined
 * after the fact, by an admin. Refunding never mutates gross_amount/
 * platform_fee_total/tutor_amount — those stay the historical record of
 * what was actually charged; only the refund tracking fields change.
 */
class FinancialTransaction extends Model
{
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'order_id',
        'order_item_id',
        'tutor_profile_id',
        'student_id',
        'product_type',
        'product_id',
        'gross_amount',
        'currency',
        'financial_rule_id',
        'platform_fee_total',
        'tutor_amount',
        'payout_status',
        'paid_at',
        'paid_by',
        'refund_status',
        'refunded_at',
        'refunded_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'platform_fee_total' => 'decimal:2',
            'tutor_amount' => 'decimal:2',
            'payout_status' => PayoutStatus::class,
            'paid_at' => 'datetime',
            'refund_status' => RefundStatus::class,
            'refunded_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function financialRule(): BelongsTo
    {
        return $this->belongsTo(FinancialRule::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(FinancialTransactionFee::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(FinancialTransactionSplit::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(PaymentTicket::class);
    }

    /**
     * The purchased course or booking this transaction paid for — resolved
     * via the same morphMap OrderItem::product() uses (registered in
     * AppServiceProvider), since product_type/product_id are copied
     * verbatim from the OrderItem at snapshot time.
     */
    public function product(): MorphTo
    {
        return $this->morphTo();
    }
}
