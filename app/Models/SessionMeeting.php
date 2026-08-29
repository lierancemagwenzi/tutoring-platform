<?php

namespace App\Models;

use App\Enums\ConnectedAccountProvider;
use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionMeeting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'teaching_session_id',
        'provider',
        'calendar_event_id',
        'meeting_id',
        'meeting_url',
        'organizer_email',
        'starts_at',
        'ends_at',
        'status',
        'metadata',
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
            'status' => MeetingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The teaching session this meeting was created for.
     */
    public function teachingSession(): BelongsTo
    {
        return $this->belongsTo(TeachingSession::class);
    }
}
