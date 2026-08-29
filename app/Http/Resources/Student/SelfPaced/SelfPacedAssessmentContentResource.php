<?php

namespace App\Http\Resources\Student\SelfPaced;

use App\Enums\SelfPacedAssessmentProvider;
use App\Models\SelfPacedSurveyContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single assessment's player-facing content — the SurveyJS question set
 * (never including correct answers; see SelfPacedSurveyContent::toSurveyJson())
 * or the H5P content id to hand to the H5P player widget.
 */
class SelfPacedAssessmentContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'self_paced_module_id' => $this->self_paced_module_id,
            'assessment_type' => $this->assessment_type->value,
            'provider' => $this->provider?->value,
            'title' => $this->title,
            'description' => $this->description,
            'required' => $this->required,
            'passing_score' => $this->passing_score,
            'attempts_mode' => $this->attempts_mode->value,
            'max_attempts' => $this->max_attempts,
            'time_limit_minutes' => $this->time_limit_minutes,
            'randomize_questions' => $this->randomize_questions,
            'show_results' => $this->show_results,
            'show_correct_answers' => $this->show_correct_answers,
            'survey_json' => $this->provider === SelfPacedAssessmentProvider::SurveyJs
                ? $this->surveyContent()?->toSurveyJson()
                : null,
            'h5p_content_id' => $this->provider === SelfPacedAssessmentProvider::H5p
                ? ($this->provider_config['h5p_content_id'] ?? null)
                : null,
        ];
    }

    private function surveyContent(): ?SelfPacedSurveyContent
    {
        $surveyContentId = $this->provider_config['survey_content_id'] ?? null;

        return $surveyContentId ? SelfPacedSurveyContent::with('questions')->find($surveyContentId) : null;
    }
}
