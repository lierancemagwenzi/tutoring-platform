<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGradeRequest extends FormRequest
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
        $grade = $this->route('grade');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('grades', 'name')->ignore($grade)],
            'level' => ['sometimes', 'required', 'integer', 'min:0', 'max:255', Rule::unique('grades', 'level')->ignore($grade)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'level.unique' => 'Another grade already uses this level.',
        ];
    }
}
