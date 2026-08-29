<?php

namespace App\Services\LessonBlocks;

use App\Enums\LearningActivityType;

class AssessmentBlockHandler extends LearningActivityBlockHandler
{
    protected function activityType(): LearningActivityType
    {
        return LearningActivityType::Assessment;
    }
}
