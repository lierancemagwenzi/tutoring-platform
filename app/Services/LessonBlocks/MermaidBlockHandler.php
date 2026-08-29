<?php

namespace App\Services\LessonBlocks;

use App\Contracts\LessonBlockHandler;
use App\Models\LessonBlock;
use Illuminate\Http\Request;

class MermaidBlockHandler implements LessonBlockHandler
{
    /**
     * @return array<string, mixed>
     */
    public function rules(bool $isUpdate): array
    {
        return [
            'diagram' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContent(Request $request, ?LessonBlock $existing): array
    {
        return [
            'diagram' => $request->input('diagram'),
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
