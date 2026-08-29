<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedActivity;
use App\Models\SelfPacedModule;
use Illuminate\Support\Facades\Storage;

class SelfPacedActivityService
{
    public function __construct(private readonly SelfPacedModuleContentService $moduleContent) {}

    public function create(SelfPacedModule $module, array $data): SelfPacedActivity
    {
        return $module->activities()->create([
            ...$data,
            'position' => $this->moduleContent->nextPosition($module),
        ])->fresh();
    }

    public function update(SelfPacedActivity $activity, array $data): SelfPacedActivity
    {
        $activity->update($data);

        return $activity->fresh();
    }

    public function delete(SelfPacedActivity $activity): void
    {
        foreach ($activity->attachments as $attachment) {
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }

        $activity->delete();
    }
}
