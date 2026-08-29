<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'provider',
        'provider_reference',
        'payment_reference',
        'amount',
        'currency',
        'status',
        'payment_method',
        'provider_response',
        'paid_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentTransactionStatus::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'provider_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The order this payment attempt belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The commission snapshot recorded when this payment was confirmed —
     * see CommissionSnapshotService.
     */
    public function financialTransaction(): HasOne
    {
        return $this->hasOne(FinancialTransaction::class);
    }
}
