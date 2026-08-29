<?php

namespace App\Models;

use App\Enums\AttemptProvider;
use App\Enums\LessonBlockStatus;
use App\Enums\LessonBlockType;
use App\Enums\SubmissionType;
use App\Services\H5p\H5PService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonBlock extends Model
{
    /**
     * The learning-activity block types the Submission Engine supports. All
     * of them share the same submission workflow — future types are added
     * here, never via a type-specific submission system.
     */
    private const SUBMISSION_ELIGIBLE_TYPES = [
        LessonBlockType::Assignment,
        LessonBlockType::Homework,
        LessonBlockType::Project,
        LessonBlockType::Lab,
        LessonBlockType::Reflection,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lesson_id',
        'block_type',
        'position',
        'title',
        'content',
        'settings',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'block_type' => LessonBlockType::class,
            'position' => 'integer',
            'content' => 'array',
            'settings' => 'array',
            'status' => LessonBlockStatus::class,
        ];
    }

    /**
     * The lesson this block belongs to.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * The media items that make up this block, when it is a media block.
     */
    public function mediaItems(): HasMany
    {
        return $this->hasMany(MediaItem::class)->orderBy('position');
    }

    /**
     * The quiz this block references, when it is a quiz block.
     *
     * Not a formal Eloquent relation: the quiz_id lives inside the generic
     * `content` JSON column rather than a dedicated FK column, so that no
     * block-type-specific column is added to lesson_blocks.
     */
    public function quiz(): ?Quiz
    {
        $quizId = $this->content['quiz_id'] ?? null;

        return $quizId ? Quiz::find($quizId) : null;
    }

    /**
     * The learning activity this block references, when it is an assignment or homework block.
     *
     * Not a formal Eloquent relation, for the same reason as quiz() above.
     */
    public function learningActivity(): ?LearningActivity
    {
        $activityId = $this->content['learning_activity_id'] ?? null;

        return $activityId ? LearningActivity::find($activityId) : null;
    }

    /**
     * Display metadata for the H5P content this block references, when it
     * is an H5P block. Unlike quiz()/learningActivity(), this isn't an
     * Eloquent lookup at all — H5P content lives entirely on the dedicated
     * H5P server (see App\Services\H5p\H5PService); Laravel only stores the
     * content id inside the generic `content` JSON column.
     *
     * @return array<string, mixed>|null
     */
    public function h5pContent(): ?array
    {
        $h5pContentId = $this->content['h5p_content_id'] ?? null;

        return $h5pContentId ? app(H5PService::class)->get((string) $h5pContentId) : null;
    }

    /**
     * Whether this block is one the Submission Engine can accept work
     * against — its type must be one of the eligible activity types, and
     * the reusable content itself must actually expect a submission.
     */
    public function supportsSubmissions(): bool
    {
        if (! in_array($this->block_type, self::SUBMISSION_ELIGIBLE_TYPES, true)) {
            return false;
        }

        return $this->learningActivity()?->submission_type !== SubmissionType::None;
    }

    /**
     * The Attempt Engine provider this block's content is delivered through,
     * if any. The Quiz block type is rendered with SurveyJS under the hood
     * (see QuizBuilder.vue/QuizAttempt.vue), so it maps to the 'surveyjs'
     * provider here rather than needing a dedicated SurveyJS block type.
     * Adding a future provider-backed block type is one new match arm here.
     */
    public function attemptProvider(): ?AttemptProvider
    {
        return match ($this->block_type) {
            LessonBlockType::Quiz => AttemptProvider::SurveyJs,
            LessonBlockType::H5p => AttemptProvider::H5p,
            default => null,
        };
    }
}
