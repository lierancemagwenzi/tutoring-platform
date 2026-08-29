<?php

namespace App\Http\Requests\Admin;

use App\Enums\FaqAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['sometimes', 'string'],
            'answer' => ['sometimes', 'string'],
            'audience' => ['sometimes', Rule::enum(FaqAudience::class)],
            'is_published' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ];
    }
}
