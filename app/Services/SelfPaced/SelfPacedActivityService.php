<?php

namespace App\Services\SelfPaced;

use App\Enums\SelfPacedActivityType;
use App\Models\SelfPacedActivity;
use App\Models\SelfPacedModule;
use App\Support\ContentItems;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SelfPacedActivityService
{
    public function __construct(private readonly SelfPacedModuleContentService $moduleContent) {}

    public function create(SelfPacedModule $module, array $data): SelfPacedActivity
    {
        if (($data['type'] ?? null) === SelfPacedActivityType::Content->value) {
            $data['content'] = $this->contentItems(null, $data['content']['items'] ?? []);
        }

        return $module->activities()->create([
            ...$data,
            'position' => $this->moduleContent->nextPosition($module),
        ])->fresh();
    }

    public function update(SelfPacedActivity $activity, array $data): SelfPacedActivity
    {
        if ($activity->type === SelfPacedActivityType::Content && array_key_exists('content', $data)) {
            $data['content'] = $this->contentItems($activity, $data['content']['items'] ?? []);
        }

        $activity->update($data);

        return $activity->fresh();
    }

    /**
     * Build a Content activity's stored item list. File items must point at
     * this activity's own attachments; attachments the list no longer uses
     * (uploaded in the editor, then removed before saving) are deleted.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: list<array<string, mixed>>}
     */
    private function contentItems(?SelfPacedActivity $activity, array $items): array
    {
        $referenced = ContentItems::mediaIds($items);
        $owned = $activity ? $activity->attachments()->pluck('id')->all() : [];

        if (array_diff($referenced, $owned) !== []) {
            throw ValidationException::withMessages([
                'content' => 'One of the files in this activity could not be found. Please re-upload it.',
            ]);
        }

        $activity?->attachments()->whereNotIn('id', $referenced)->get()->each(function ($attachment) {
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            $attachment->delete();
        });

        return ['items' => ContentItems::normalise($items)];
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
