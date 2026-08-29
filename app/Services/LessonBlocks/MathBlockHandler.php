<?php

namespace App\Services\LessonBlocks;

use App\Contracts\LessonBlockHandler;
use App\Models\LessonBlock;
use Illuminate\Http\Request;

class MathBlockHandler implements LessonBlockHandler
{
    /**
     * @return array<string, mixed>
     */
    public function rules(bool $isUpdate): array
    {
        return [
            'latex' => ['required', 'string'],
            'display_mode' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContent(Request $request, ?LessonBlock $existing): array
    {
        return [
            'latex' => $request->input('latex'),
            'display_mode' => $request->boolean('display_mode'),
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
