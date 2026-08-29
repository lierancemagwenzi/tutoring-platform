<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedActivity;
use App\Models\SelfPacedAssessment;
use App\Models\SelfPacedModule;
use Illuminate\Support\Facades\DB;

/**
 * Learning Activities and Assessments are stored in separate tables but
 * share a single display order within a module (see
 * SelfPacedModule::orderedContent()). This service is the one place that
 * assigns and reorders that shared position namespace, so no other code
 * has to know both tables are involved.
 */
class SelfPacedModuleContentService
{
    public function nextPosition(SelfPacedModule $module): int
    {
        return $module->activities()->count() + $module->assessments()->count();
    }

    /**
     * @param  list<array{type: string, id: int}>  $items
     */
    public function reorder(SelfPacedModule $module, array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $position => $item) {
                match ($item['type']) {
                    'activity' => SelfPacedActivity::whereKey($item['id'])->update(['position' => $position]),
                    'assessment' => SelfPacedAssessment::whereKey($item['id'])->update(['position' => $position]),
                };
            }
        });
    }
}
