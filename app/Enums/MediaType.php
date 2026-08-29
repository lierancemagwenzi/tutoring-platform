<?php

namespace App\Enums;

enum MediaType: string
{
    case Pdf = 'pdf';
    case Image = 'image';
    case VideoUpload = 'video_upload';
    case VideoYoutube = 'video_youtube';
    case VideoVimeo = 'video_vimeo';
    case Zip = 'zip';
    case Doc = 'doc';
    case Docx = 'docx';
    case Ppt = 'ppt';
    case Pptx = 'pptx';
    case Xls = 'xls';
    case Xlsx = 'xlsx';
    case Csv = 'csv';
    case Txt = 'txt';
    case Mp3 = 'mp3';
    case Wav = 'wav';

    /**
     * Whether this media type is referenced by an external URL rather than an uploaded file.
     */
    public function isExternal(): bool
    {
        return in_array($this, [self::VideoYoutube, self::VideoVimeo], true);
    }

    /**
     * The file extensions/mimes accepted for an upload of this media type.
     *
     * @return list<string>
     */
    public function acceptedMimes(): array
    {
        return match ($this) {
            self::Pdf => ['pdf'],
            self::Image => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            self::VideoUpload => ['mp4', 'mov', 'avi', 'webm'],
            self::Zip => ['zip'],
            self::Doc => ['doc'],
            self::Docx => ['docx'],
            self::Ppt => ['ppt'],
            self::Pptx => ['pptx'],
            self::Xls => ['xls'],
            self::Xlsx => ['xlsx'],
            self::Csv => ['csv'],
            self::Txt => ['txt'],
            self::Mp3 => ['mp3'],
            self::Wav => ['wav'],
            self::VideoYoutube, self::VideoVimeo => [],
        };
    }
}
