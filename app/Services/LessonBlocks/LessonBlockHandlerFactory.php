<?php

namespace App\Services\LessonBlocks;

use App\Contracts\LessonBlockHandler;
use App\Enums\LessonBlockType;

class LessonBlockHandlerFactory
{
    /**
     * Resolve the concrete block handler implementation for the given block type.
     */
    public static function make(LessonBlockType $type): LessonBlockHandler
    {
        return match ($type) {
            LessonBlockType::RichText => new RichTextBlockHandler,
            LessonBlockType::Media => new MediaBlockHandler,
            LessonBlockType::Quiz => new QuizBlockHandler,
            LessonBlockType::Math => new MathBlockHandler,
            LessonBlockType::Mermaid => new MermaidBlockHandler,
            LessonBlockType::H5p => new H5pBlockHandler,
            LessonBlockType::Assignment => new AssignmentBlockHandler,
            LessonBlockType::Homework => new HomeworkBlockHandler,
            LessonBlockType::Practice => new PracticeBlockHandler,
            LessonBlockType::Assessment => new AssessmentBlockHandler,
            LessonBlockType::Project => new ProjectBlockHandler,
            LessonBlockType::Lab => new LabBlockHandler,
            LessonBlockType::Reflection => new ReflectionBlockHandler,
            LessonBlockType::Reading => new ReadingBlockHandler,
            LessonBlockType::External => new ExternalActivityBlockHandler,
        };
    }
}
