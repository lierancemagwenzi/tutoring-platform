<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
