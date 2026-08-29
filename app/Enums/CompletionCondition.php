<?php

namespace App\Enums;

enum CompletionCondition: string
{
    case ViewActivity = 'view_activity';
    case SubmitWork = 'submit_work';
    case PassActivity = 'pass_activity';
    case AchieveScore = 'achieve_score';
    case TutorApproval = 'tutor_approval';
}
