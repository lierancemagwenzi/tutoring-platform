<?php

namespace App\Services\LessonBlocks;

use App\Contracts\LessonBlockHandler;
use App\Models\LessonBlock;
use Illuminate\Http\Request;

class RichTextBlockHandler implements LessonBlockHandler
{
    /**
     * @return array<string, mixed>
     */
    public function rules(bool $isUpdate): array
    {
        return [
            'content_html' => ['required', 'string'],
            'content_json' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContent(Request $request, ?LessonBlock $existing): array
    {
        return [
            'html' => $request->input('content_html'),
            'json' => $request->input('content_json'),
        ];
    }

    public function afterDelete(LessonBlock $block): void {}

    /**
     * @return array<string, mixed>
     */
    public function duplicateContent(LessonBlock $original, LessonBlock $copy): array
    {
        return $original->content;
    }
}
