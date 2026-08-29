<?php

namespace App\Services\LessonBlocks;

use App\Enums\LearningActivityType;

class ProjectBlockHandler extends LearningActivityBlockHandler
{
    protected function activityType(): LearningActivityType
    {
        return LearningActivityType::Project;
    }
}
