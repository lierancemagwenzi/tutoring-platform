<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'password',
        'role',
        'status',
        'disabled_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'disabled_at' => 'datetime',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * The tutor profile associated with the user.
     */
    public function tutorProfile(): HasOne
    {
        return $this->hasOne(TutorProfile::class);
    }

    /**
     * The guardian record associated with the user, when the user is a minor student.
     */
    public function studentGuardian(): HasOne
    {
        return $this->hasOne(StudentGuardian::class, 'student_id');
    }

    /**
     * The bookings this user has requested as a student.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'student_id');
    }

    /**
     * The support tickets this user has raised — either a tutor or a
     * student, unlike PaymentTicket which is tutor-only.
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * The commission-snapshot transactions this user paid for as a student.
     */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'student_id');
    }

    /**
     * The orders this user has placed as a student.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'student_id');
    }

    /**
     * The enrollments this user holds as a student.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    /**
     * The OTP codes issued to this user, across every purpose.
     */
    public function emailOtps(): HasMany
    {
        return $this->hasMany(EmailOtp::class);
    }
}
