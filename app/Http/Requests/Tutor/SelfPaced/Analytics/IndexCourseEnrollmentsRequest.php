<?php

namespace App\Http\Requests\Tutor\SelfPaced\Analytics;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCourseEnrollmentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Course ownership is checked once, up front, by the controller via
     * SelfPacedCoursePolicy — nothing here is student-specific.
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
            'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'completed'])],
            'certificate_issued' => ['nullable', 'boolean'],
            'inactive_only' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
