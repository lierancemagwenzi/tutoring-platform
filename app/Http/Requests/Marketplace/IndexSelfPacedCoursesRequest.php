<?php

namespace App\Http\Requests\Marketplace;

use App\Enums\SelfPacedDifficulty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSelfPacedCoursesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            'grade_id' => ['nullable', 'integer', Rule::exists('grades', 'id')->where('is_active', true)],
            'difficulty' => ['nullable', Rule::enum(SelfPacedDifficulty::class)],
            'language' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', Rule::in(['free', 'paid'])],
            'tutor_id' => ['nullable', 'integer', Rule::exists('tutor_profiles', 'id')],
            'duration_max' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'most_popular', 'highest_rated', 'price_low', 'price_high', 'alphabetical'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
