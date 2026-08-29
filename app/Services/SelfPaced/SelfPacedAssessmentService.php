<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedAssessment;
use App\Models\SelfPacedModule;

class SelfPacedAssessmentService
{
    public function __construct(private readonly SelfPacedModuleContentService $moduleContent) {}

    public function create(SelfPacedModule $module, array $data): SelfPacedAssessment
    {
        return $module->assessments()->create([
            ...$data,
            'position' => $this->moduleContent->nextPosition($module),
        ])->fresh();
    }

    public function update(SelfPacedAssessment $assessment, array $data): SelfPacedAssessment
    {
        $assessment->update($data);

        return $assessment->fresh();
    }

    public function delete(SelfPacedAssessment $assessment): void
    {
        $assessment->delete();
    }
}
