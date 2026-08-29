<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TeachingSession extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tutor_profile_id',
        'service_id',
        'availability_slot_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'completed_at',
        'tutor_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => SessionStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The tutor profile this session belongs to.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * The service this session is an instance of.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The availability slot this session was scheduled within.
     */
    public function availabilitySlot(): BelongsTo
    {
        return $this->belongsTo(AvailabilitySlot::class);
    }

    /**
     * The bookings attached to this session — more than one when it's a
     * shared group-class occurrence (Service.max_students_per_session > 1).
     */
    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_teaching_session')->withTimestamps();
    }

    /**
     * The virtual meeting generated for this session, if any.
     */
    public function sessionMeeting(): HasOne
    {
        return $this->hasOne(SessionMeeting::class);
    }

    /**
     * The lessons assigned to this session, in order.
     */
    public function sessionLessons(): HasMany
    {
        return $this->hasMany(SessionLesson::class)->orderBy('position');
    }
}
