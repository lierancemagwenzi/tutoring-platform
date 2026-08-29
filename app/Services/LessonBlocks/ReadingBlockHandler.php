<?php

namespace App\Services\LessonBlocks;

use App\Enums\LearningActivityType;

class ReadingBlockHandler extends LearningActivityBlockHandler
{
    protected function activityType(): LearningActivityType
    {
        return LearningActivityType::Reading;
    }
}
