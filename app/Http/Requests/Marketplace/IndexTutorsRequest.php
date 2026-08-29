<?php

namespace App\Http\Requests\Marketplace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTutorsRequest extends FormRequest
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
            'curriculum_id' => ['nullable', 'integer', Rule::exists('curricula', 'id')->where('is_active', true)],
            'service_category_id' => ['nullable', 'integer', Rule::exists('service_categories', 'id')->where('is_active', true)],
            'session_format_id' => ['nullable', 'integer', Rule::exists('session_formats', 'id')->where('is_active', true)],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'gte:price_min'],
            'language' => ['nullable', 'string', 'max:255'],
            'years_experience_min' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'string', Rule::in(['lowest_price', 'highest_price', 'most_experienced', 'alphabetical', 'newest'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
