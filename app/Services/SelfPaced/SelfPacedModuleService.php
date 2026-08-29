<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedCourse;
use App\Models\SelfPacedModule;

class SelfPacedModuleService
{
    public function create(SelfPacedCourse $course, array $data): SelfPacedModule
    {
        return $course->modules()->create([...$data, 'position' => $course->modules()->count()])->fresh();
    }

    public function update(SelfPacedModule $module, array $data): SelfPacedModule
    {
        $module->update($data);

        return $module->fresh();
    }

    public function delete(SelfPacedModule $module): void
    {
        $module->delete();
    }

    /**
     * @param  list<int>  $moduleIds
     */
    public function reorder(SelfPacedCourse $course, array $moduleIds): void
    {
        foreach ($moduleIds as $position => $moduleId) {
            SelfPacedModule::whereKey($moduleId)->update(['position' => $position]);
        }
    }
}
