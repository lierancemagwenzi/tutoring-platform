<?php

namespace App\Models;

use App\Enums\ConnectedAccountProvider;
use App\Enums\ServiceVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TutorProfile extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'bio',
        'profile_photo',
        'display_name',
        'years_experience',
        'occupation',
        'languages',
        'teaching_style',
        'about_me',
        'why_choose_me',
        'government_id_path',
        'government_id_name',
        'onboarding_step',
        'onboarding_complete',
        'meeting_provider',
        'admin_note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'years_experience' => 'integer',
            'onboarding_step' => 'integer',
            'onboarding_complete' => 'boolean',
            'meeting_provider' => ConnectedAccountProvider::class,
        ];
    }

    /**
     * The user that owns the tutor profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The qualifications the tutor has added to their application.
     */
    public function qualifications(): HasMany
    {
        return $this->hasMany(TutorQualification::class);
    }

    /**
     * The supporting documents the tutor has uploaded to their application.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(TutorDocument::class);
    }

    /**
     * The subjects this tutor has been assigned to teach.
     */
    public function tutorSubjects(): HasMany
    {
        return $this->hasMany(TutorSubject::class);
    }

    /**
     * The dates this tutor has configured teaching availability for.
     */
    public function availabilityDates(): HasMany
    {
        return $this->hasMany(AvailabilityDate::class);
    }

    /**
     * The learning products this tutor offers.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * The learning products this tutor has published, visible to students in the marketplace.
     */
    public function publishedServices(): HasMany
    {
        return $this->services()->where('visibility', ServiceVisibility::Published);
    }

    /**
     * The bookings students have requested with this tutor.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The teaching sessions scheduled for this tutor.
     */
    public function teachingSessions(): HasMany
    {
        return $this->hasMany(TeachingSession::class);
    }

    /**
     * The courses this tutor has authored.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * The self-paced courses (Idea B) this tutor has authored — an
     * entirely independent catalog from Tutor-Led Learning's courses().
     */
    public function selfPacedCourses(): HasMany
    {
        return $this->hasMany(SelfPacedCourse::class);
    }

    /**
     * The H5P content this tutor has tagged as belonging to their
     * self-paced courses (see SelfPacedH5pContent).
     */
    public function selfPacedH5pContents(): HasMany
    {
        return $this->hasMany(SelfPacedH5pContent::class);
    }

    /**
     * The reusable SurveyJS question banks this tutor has authored for
     * self-paced course Assessments.
     */
    public function selfPacedSurveyContents(): HasMany
    {
        return $this->hasMany(SelfPacedSurveyContent::class);
    }

    /**
     * The third-party accounts (Google, and later Microsoft/Zoom/LiveKit)
     * this tutor has connected via OAuth.
     */
    public function connectedAccounts(): HasMany
    {
        return $this->hasMany(TutorConnectedAccount::class);
    }

    /**
     * The banking details the admin pays this tutor out to.
     */
    public function bankAccount(): HasOne
    {
        return $this->hasOne(TutorBankAccount::class);
    }

    public function paymentTickets(): HasMany
    {
        return $this->hasMany(PaymentTicket::class);
    }

    /**
     * List the human-readable reasons this application is not yet ready to submit.
     *
     * @return list<string>
     */
    public function missingSubmissionRequirements(): array
    {
        $missing = [];

        if (! $this->display_name || ! $this->bio || ! $this->years_experience || ! $this->occupation || empty($this->languages)) {
            $missing[] = 'Basic information is incomplete.';
        }

        if (! $this->teaching_style || ! $this->about_me || ! $this->why_choose_me) {
            $missing[] = 'Professional profile is incomplete.';
        }

        if ($this->qualifications()->doesntExist()) {
            $missing[] = 'At least one qualification is required.';
        }

        if (! $this->government_id_path) {
            $missing[] = 'A government ID document is required.';
        }

        return $missing;
    }
}
