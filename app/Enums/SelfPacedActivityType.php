<?php

namespace App\Enums;

enum SelfPacedActivityType: string
{
    case RichText = 'rich_text';
    case Video = 'video';
    case Pdf = 'pdf';
    case ImageGallery = 'image_gallery';
    case Audio = 'audio';
    case Document = 'document';
    case Presentation = 'presentation';
    case Spreadsheet = 'spreadsheet';
    case Download = 'download';
    case ExternalResource = 'external_resource';
    case Mermaid = 'mermaid';
    case Katex = 'katex';
    case Assignment = 'assignment';
    case Homework = 'homework';
    case Reading = 'reading';
    case H5p = 'h5p';

    /**
     * Whether this activity type is backed by one or more uploaded/linked
     * files (SelfPacedActivityAttachment rows) rather than inline content.
     */
    public function usesAttachment(): bool
    {
        return in_array($this, [
            self::Video,
            self::Pdf,
            self::ImageGallery,
            self::Audio,
            self::Document,
            self::Presentation,
            self::Spreadsheet,
            self::Download,
        ], true);
    }

    /**
     * Whether this activity type stores its content inline on the
     * activity's own content JSON column instead of via attachments.
     */
    public function usesInlineContent(): bool
    {
        return ! $this->usesAttachment();
    }
}
