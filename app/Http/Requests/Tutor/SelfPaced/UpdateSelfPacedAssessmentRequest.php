<?php

namespace App\Http\Requests\Tutor\SelfPaced;

use App\Enums\SelfPacedAssessmentProvider;
use App\Enums\SelfPacedAssessmentType;
use App\Enums\SelfPacedAttemptsMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSelfPacedAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('selfPacedAssessment')->module->course->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assessment_type' => ['sometimes', Rule::enum(SelfPacedAssessmentType::class)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'required' => ['sometimes', 'boolean'],
            'passing_score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'attempts_mode' => ['sometimes', Rule::enum(SelfPacedAttemptsMode::class)],
            'max_attempts' => ['sometimes', 'required_if:attempts_mode,limited', 'nullable', 'integer', 'min:1'],
            'time_limit_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'available_until' => ['sometimes', 'nullable', 'date', 'after:available_from'],
            'randomize_questions' => ['sometimes', 'boolean'],
            'show_results' => ['sometimes', 'boolean'],
            'show_correct_answers' => ['sometimes', 'boolean'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'provider' => ['sometimes', 'nullable', Rule::enum(SelfPacedAssessmentProvider::class)],
            'provider_config' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('provider')) {
                return;
            }

            $provider = SelfPacedAssessmentProvider::tryFrom((string) $this->input('provider'));

            if (! $provider) {
                return;
            }

            $config = (array) $this->input('provider_config', []);
            $tutor = $this->user()->tutorProfile;
            $course = $this->route('selfPacedAssessment')->module->course;

            match ($provider) {
                SelfPacedAssessmentProvider::SurveyJs => $this->requireMatchingContent(
                    $validator,
                    $config,
                    'survey_content_id',
                    fn (string $id) => $tutor->selfPacedSurveyContents()
                        ->whereKey($id)
                        ->where('subject_id', $course->subject_id)
                        ->where('grade_id', $course->grade_id)
                        ->exists(),
                ),
                SelfPacedAssessmentProvider::H5p => $this->requireMatchingContent(
                    $validator,
                    $config,
                    'h5p_content_id',
                    fn (string $id) => $tutor->selfPacedH5pContents()
                        ->where('h5p_content_id', $id)
                        ->whereIn('h5p_content_id', fn ($query) => $query
                            ->select('h5p_content_id')
                            ->from('h5p_content_classifications')
                            ->where('subject_id', $course->subject_id)
                            ->where('grade_id', $course->grade_id))
                        ->exists(),
                ),
            };
        });
    }

    /**
     * Requires the config key be present AND, when it is, that it
     * references content this tutor has tagged for self-paced use and
     * whose subject/grade matches this course's own (self-paced courses
     * have no curriculum to also match on) — never another tutor's, never
     * Tutor-Led Learning's untagged content, and never a mismatched
     * subject/grade a student could never actually be shown.
     *
     * @param  array<string, mixed>  $config
     */
    private function requireMatchingContent(Validator $validator, array $config, string $key, callable $matchesCourse): void
    {
        $value = $config[$key] ?? null;

        if (blank($value)) {
            $validator->errors()->add('provider_config', "The provider_config.{$key} field is required for this provider.");

            return;
        }

        if (! $matchesCourse((string) $value)) {
            $validator->errors()->add(
                'provider_config',
                "The selected provider_config.{$key} doesn't match this course's subject and grade.",
            );
        }
    }
}
