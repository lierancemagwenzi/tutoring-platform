<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderSelfPacedModulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('selfPacedCourse')->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'module_ids' => ['required', 'array'],
            'module_ids.*' => [
                'integer',
                Rule::exists('self_paced_modules', 'id')->where('self_paced_course_id', $this->route('selfPacedCourse')->id),
            ],
        ];
    }
}
