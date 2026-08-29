<?php

namespace App\Models;

use App\Enums\ConnectedAccountProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorConnectedAccount extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tutor_profile_id',
        'provider',
        'provider_user_id',
        'email',
        'access_token',
        'refresh_token',
        'expires_at',
        'metadata',
        'connected_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => ConnectedAccountProvider::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'metadata' => 'array',
            'connected_at' => 'datetime',
        ];
    }

    /**
     * The tutor this connected account belongs to.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }
}
