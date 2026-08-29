<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderSelfPacedModuleContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('selfPacedModule')->course->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.type' => ['required', Rule::in(['activity', 'assessment'])],
            'items.*.id' => ['required', 'integer'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $module = $this->route('selfPacedModule');
            $activityIds = $module->activities()->pluck('id')->all();
            $assessmentIds = $module->assessments()->pluck('id')->all();

            foreach ((array) $this->input('items', []) as $index => $item) {
                $ids = ($item['type'] ?? null) === 'activity' ? $activityIds : $assessmentIds;

                if (! in_array((int) ($item['id'] ?? null), $ids, true)) {
                    $validator->errors()->add("items.{$index}.id", 'This item does not belong to the module.');
                }
            }
        });
    }
}
