<?php

namespace App\Enums;

enum LearningActivityType: string
{
    case Assignment = 'assignment';
    case Homework = 'homework';
    case Practice = 'practice';
    case Assessment = 'assessment';
    case Project = 'project';
    case Lab = 'lab';
    case Reflection = 'reflection';
    case Reading = 'reading';
    case External = 'external_activity';
}
