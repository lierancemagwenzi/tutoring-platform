<?php

namespace App\Enums;

enum SelfPacedAssessmentType: string
{
    case PracticeQuiz = 'practice_quiz';
    case KnowledgeCheck = 'knowledge_check';
    case ChapterTest = 'chapter_test';
    case MockExamination = 'mock_examination';
    case FinalExamination = 'final_examination';
}
