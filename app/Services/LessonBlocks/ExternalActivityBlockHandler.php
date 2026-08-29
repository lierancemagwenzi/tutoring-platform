<?php

namespace App\Services\LessonBlocks;

use App\Enums\LearningActivityType;
use App\Enums\SubmissionType;

class ExternalActivityBlockHandler extends LearningActivityBlockHandler
{
    protected function activityType(): LearningActivityType
    {
        return LearningActivityType::External;
    }

    /**
     * An external link-out has no submission by default — a tutor can still
     * attach a reflection or check via the block's settings.
     *
     * @return array<string, mixed>
     */
    protected function defaultAttributes(): array
    {
        return [
            'submission_type' => SubmissionType::None,
        ];
    }
}
