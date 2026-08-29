<?php

namespace App\Enums;

enum QualificationLevel: string
{
    case Certificate = 'certificate';
    case Diploma = 'diploma';
    case BachelorsDegree = 'bachelors_degree';
    case Honours = 'honours';
    case Masters = 'masters';
    case PhD = 'phd';
    case Other = 'other';
}
