<?php

namespace App\Services\LessonBlocks;

use App\Enums\LearningActivityType;

class HomeworkBlockHandler extends LearningActivityBlockHandler
{
    protected function activityType(): LearningActivityType
    {
        return LearningActivityType::Homework;
    }
}
