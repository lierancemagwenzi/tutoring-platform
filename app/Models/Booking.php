<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'tutor_profile_id',
        'service_id',
        'availability_slot_id',
        'order_id',
        'date',
        'start_time',
        'end_time',
        'price',
        'currency',
        'status',
        'message',
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
            'price' => 'decimal:2',
            'currency' => Currency::class,
            'status' => BookingStatus::class,
        ];
    }

    /**
     * The student who requested this booking.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * The tutor profile this booking is with.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * The service being booked.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The availability slot the requested time falls within.
     */
    public function availabilitySlot(): BelongsTo
    {
        return $this->belongsTo(AvailabilitySlot::class);
    }

    /**
     * The teaching sessions scheduled for this booking's purchased package,
     * one at a time by the tutor, up to the service's purchased count. Also
     * how a group class (Service.max_students_per_session > 1) shares one
     * TeachingSession across several bookings — the pivot is what lets both
     * concepts coexist.
     */
    public function teachingSessions(): BelongsToMany
    {
        return $this->belongsToMany(TeachingSession::class, 'booking_teaching_session')->withTimestamps();
    }

    /**
     * The commerce order that pays for this booking, created when the
     * tutor accepts the booking request.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The tutor/student chat thread for this booking. Only sendable while
     * the booking is Confirmed — see BookingChatService and
     * StoreBookingMessageRequest — but always readable regardless of status.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(BookingMessage::class)->oldest();
    }

    /**
     * The delivery instance (Session Lesson Block) this booking's sessions
     * assign for the given Lesson Block, if any — regardless of whether it
     * is currently available. Callers that need to gate student access
     * should also check the result's isAvailable(). Searches across every
     * session scheduled for this booking, since a multi-session package can
     * deliver different lessons across different sessions.
     */
    public function sessionLessonBlockFor(LessonBlock $lessonBlock): ?SessionLessonBlock
    {
        $teachingSessionIds = $this->teachingSessions()->pluck('teaching_sessions.id');

        if ($teachingSessionIds->isEmpty()) {
            return null;
        }

        return SessionLessonBlock::where('lesson_block_id', $lessonBlock->id)
            ->whereHas('sessionLesson', fn ($query) => $query->whereIn('teaching_session_id', $teachingSessionIds))
            ->first();
    }
}
