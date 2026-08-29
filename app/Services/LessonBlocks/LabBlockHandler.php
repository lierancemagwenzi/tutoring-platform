<?php

namespace App\Services\LessonBlocks;

use App\Enums\LearningActivityType;

class LabBlockHandler extends LearningActivityBlockHandler
{
    protected function activityType(): LearningActivityType
    {
        return LearningActivityType::Lab;
    }
}
