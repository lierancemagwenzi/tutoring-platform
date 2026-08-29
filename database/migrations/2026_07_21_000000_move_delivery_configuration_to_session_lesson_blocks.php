<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The learning-activity lesson block types whose `content` JSON column
     * references a `learning_activities` row via `learning_activity_id`.
     *
     * @var list<string>
     */
    private array $learningActivityBlockTypes = [
        'assignment', 'homework', 'practice', 'assessment',
        'project', 'lab', 'reflection', 'reading', 'external_activity',
    ];

    /**
     * Run the migrations.
     *
     * Delivery behaviour (availability, completion, attempts, passing score)
     * moves from the reusable Lesson Block layer (learning_activities) to the
     * delivery layer (session_lesson_blocks), so the same educational content
     * can be delivered differently across sessions. Existing per-activity
     * delivery settings are carried over to whatever session assignments
     * already reference that activity before the source columns are dropped.
     */
    public function up(): void
    {
        Schema::table('session_lesson_blocks', function (Blueprint $table) {
            $table->string('completion_mode')->default('not_tracked')->after('is_manually_released');
            $table->string('completion_rule')->nullable()->after('completion_mode');
            $table->string('attempts_mode')->default('unlimited')->after('completion_rule');
            $table->unsignedInteger('max_attempts')->nullable()->after('attempts_mode');
            $table->decimal('passing_score', 8, 2)->nullable()->after('max_attempts');
            $table->string('visibility')->default('visible')->after('passing_score');
        });

        $this->migrateExistingDeliveryConfiguration();

        Schema::table('learning_activities', function (Blueprint $table) {
            $table->dropColumn([
                'is_graded',
                'availability_mode',
                'opens_at',
                'closes_at',
                'submission_window_mode',
                'submission_closes_at',
                'late_submission_allowed',
                'attempts_mode',
                'max_attempts',
                'passing_score',
                'completion_type',
                'completion_conditions',
            ]);
        });
    }

    /**
     * Carry each learning activity's existing delivery settings over to every
     * session_lesson_block that already delivers it, using raw queries only
     * (never Eloquent models, whose current definitions no longer match the
     * schema this migration runs against).
     */
    private function migrateExistingDeliveryConfiguration(): void
    {
        $blocks = DB::table('lesson_blocks')
            ->whereIn('block_type', $this->learningActivityBlockTypes)
            ->get(['id', 'content']);

        foreach ($blocks as $block) {
            $content = json_decode($block->content ?? '{}', true);
            $activityId = $content['learning_activity_id'] ?? null;

            if (! $activityId) {
                continue;
            }

            $activity = DB::table('learning_activities')->where('id', $activityId)->first();

            if (! $activity) {
                continue;
            }

            $completionConditions = json_decode($activity->completion_conditions ?? '[]', true) ?: [];

            DB::table('session_lesson_blocks')
                ->where('lesson_block_id', $block->id)
                ->update([
                    'completion_mode' => count($completionConditions) > 0 ? 'required' : 'not_tracked',
                    'completion_rule' => $completionConditions[0] ?? null,
                    'attempts_mode' => $activity->attempts_mode,
                    'max_attempts' => $activity->max_attempts,
                    'passing_score' => $activity->passing_score,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_activities', function (Blueprint $table) {
            $table->boolean('is_graded')->default(true)->after('status');
            $table->string('availability_mode')->default('immediate')->after('submission_type');
            $table->timestamp('opens_at')->nullable()->after('availability_mode');
            $table->timestamp('closes_at')->nullable()->after('opens_at');
            $table->string('submission_window_mode')->default('always_open')->after('closes_at');
            $table->timestamp('submission_closes_at')->nullable()->after('submission_window_mode');
            $table->boolean('late_submission_allowed')->default(false)->after('submission_closes_at');
            $table->string('attempts_mode')->default('unlimited')->after('late_submission_allowed');
            $table->unsignedInteger('max_attempts')->nullable()->after('attempts_mode');
            $table->decimal('passing_score', 8, 2)->nullable()->after('max_score');
            $table->string('completion_type')->default('manual')->after('passing_score');
            $table->json('completion_conditions')->nullable()->after('completion_type');
        });

        Schema::table('session_lesson_blocks', function (Blueprint $table) {
            $table->dropColumn(['completion_mode', 'completion_rule', 'attempts_mode', 'max_attempts', 'passing_score', 'visibility']);
        });
    }
};
