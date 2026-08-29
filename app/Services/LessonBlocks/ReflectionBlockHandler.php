<?php

namespace App\Services\LessonBlocks;

use App\Enums\LearningActivityType;

class ReflectionBlockHandler extends LearningActivityBlockHandler
{
    protected function activityType(): LearningActivityType
    {
        return LearningActivityType::Reflection;
    }
}
