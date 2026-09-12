<?php

namespace App\Models;

use App\Enums\LessonBlockStatus;
use App\Enums\LessonBlockType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lesson_id',
        'title',
        'description',
        'status',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LessonBlockStatus::class,
            'settings' => 'array',
        ];
    }

    /**
     * The lesson this quiz belongs to.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * The questions that make up this quiz.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    /**
     * The student attempts recorded against this quiz.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * The lesson block that wraps this quiz, if any — the reverse of
     * LessonBlock::quiz(). Not a formal Eloquent relation for the same
     * reason: quiz_id lives inside the block's generic `content` JSON
     * column rather than a dedicated FK. Used to keep the block's own
     * status in sync when the quiz itself is published (see
     * QuizController::publish()) — otherwise the Lesson Builder keeps
     * showing the block as Draft after the tutor publishes its quiz.
     */
    public function lessonBlock(): ?LessonBlock
    {
        return LessonBlock::query()
            ->where('lesson_id', $this->lesson_id)
            ->where('block_type', LessonBlockType::Quiz)
            ->get()
            ->first(fn (LessonBlock $block) => ($block->content['quiz_id'] ?? null) === $this->id);
    }
}
