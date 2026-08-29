<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use App\Enums\SelfPacedAssessmentProvider;
use App\Enums\SelfPacedAssessmentType;
use App\Enums\SelfPacedAttemptsMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSelfPacedAssessmentRequest extends FormRequest
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
            'assessment_type' => ['required', Rule::enum(SelfPacedAssessmentType::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'required' => ['sometimes', 'boolean'],
            'passing_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'attempts_mode' => ['sometimes', Rule::enum(SelfPacedAttemptsMode::class)],
            'max_attempts' => ['required_if:attempts_mode,limited', 'nullable', 'integer', 'min:1'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'randomize_questions' => ['sometimes', 'boolean'],
            'show_results' => ['sometimes', 'boolean'],
            'show_correct_answers' => ['sometimes', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'provider' => ['nullable', Rule::enum(SelfPacedAssessmentProvider::class)],
            'provider_config' => ['nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $provider = SelfPacedAssessmentProvider::tryFrom((string) $this->input('provider'));

            if (! $provider) {
                return;
            }

            $config = (array) $this->input('provider_config', []);
            $tutor = $this->user()->tutorProfile;

            match ($provider) {
                SelfPacedAssessmentProvider::SurveyJs => $this->requireOwnedContent(
                    $validator,
                    $config,
                    'survey_content_id',
                    fn (string $id) => $tutor->selfPacedSurveyContents()->whereKey($id)->exists(),
                ),
                SelfPacedAssessmentProvider::H5p => $this->requireOwnedContent(
                    $validator,
                    $config,
                    'h5p_content_id',
                    fn (string $id) => $tutor->selfPacedH5pContents()->where('h5p_content_id', $id)->exists(),
                ),
            };
        });
    }

    /**
     * Requires the config key be present AND, when it is, that it
     * references content this tutor has tagged for self-paced use — never
     * another tutor's, and never Tutor-Led Learning's untagged content.
     *
     * @param  array<string, mixed>  $config
     */
    private function requireOwnedContent(Validator $validator, array $config, string $key, callable $isOwnedByTutor): void
    {
        $value = $config[$key] ?? null;

        if (blank($value)) {
            $validator->errors()->add('provider_config', "The provider_config.{$key} field is required for this provider.");

            return;
        }

        if (! $isOwnedByTutor((string) $value)) {
            $validator->errors()->add('provider_config', "The selected provider_config.{$key} was not found in your self-paced content.");
        }
    }
}
