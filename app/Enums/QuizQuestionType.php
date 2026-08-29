<?php

namespace App\Enums;

enum QuizQuestionType: string
{
    case SingleChoice = 'radiogroup';
    case MultipleChoice = 'checkbox';
    case TrueFalse = 'boolean';
    case ShortText = 'text';

    /**
     * Whether answers to this question type can be automatically scored.
     */
    public function isAutoScorable(): bool
    {
        return $this !== self::ShortText;
    }
}
