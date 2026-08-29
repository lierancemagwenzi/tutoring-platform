<?php

namespace App\Enums;

enum SubmissionType: string
{
    case None = 'none';
    case Text = 'text';
    case FileUpload = 'file_upload';
    case TextAndFile = 'text_and_file';
    case Code = 'code';
}
