<?php

namespace App\Http\Requests\Tutor;

use App\Enums\LessonBlockStatus;
use App\Enums\LessonBlockType;
use App\Services\LessonBlocks\LessonBlockHandlerFactory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonBlockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('lessonBlock')->lesson->chapter->course->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Decode JSON-encoded fields sent as strings within multipart form data.
     */
    protected function prepareForValidation(): void
    {
        foreach (['content_json', 'settings'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => json_decode($this->input($field), true)]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $baseRules = [
            'block_type' => ['required', Rule::in(array_column(LessonBlockType::cases(), 'value'))],
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(LessonBlockStatus::class)],
            'settings' => ['nullable', 'array'],
        ];

        $blockType = LessonBlockType::tryFrom((string) $this->input('block_type'));

        if (! $blockType) {
            return $baseRules;
        }

        return [
            ...$baseRules,
            ...LessonBlockHandlerFactory::make($blockType)->rules(isUpdate: true),
        ];
    }
}
