<?php

namespace App\Enums;

enum LessonBlockType: string
{
    // A container holding any number of rich text / maths / diagram / file
    // items, in order. Supersedes the single-item RichText, Media, Math and
    // Mermaid types, which remain only so older rows keep resolving.
    case Content = 'content';
    case RichText = 'rich_text';
    case Media = 'media';
    case Quiz = 'quiz';
    case Math = 'math';
    case Mermaid = 'mermaid';
    case H5p = 'h5p';
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
