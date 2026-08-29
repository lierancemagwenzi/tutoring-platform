<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilitySlot extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'availability_date_id',
        'start_time',
        'end_time',
    ];

    /**
     * The availability date this time slot belongs to.
     */
    public function availabilityDate(): BelongsTo
    {
        return $this->belongsTo(AvailabilityDate::class);
    }
}
