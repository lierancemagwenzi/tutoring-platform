<?php

namespace App\Enums;

enum TutorDocumentType: string
{
    case Degree = 'degree';
    case TeachingCertificate = 'teaching_certificate';
    case PoliceClearance = 'police_clearance';
    case Other = 'other';
}
