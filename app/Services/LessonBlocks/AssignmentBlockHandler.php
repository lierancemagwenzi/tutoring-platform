<?php

namespace App\Services\LessonBlocks;

use App\Enums\LearningActivityType;

class AssignmentBlockHandler extends LearningActivityBlockHandler
{
    protected function activityType(): LearningActivityType
    {
        return LearningActivityType::Assignment;
    }
}
