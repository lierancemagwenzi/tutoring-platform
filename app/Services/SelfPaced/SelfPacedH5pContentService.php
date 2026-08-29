<?php

namespace App\Services\SelfPaced;

use App\Models\SelfPacedH5pContent;
use App\Models\TutorProfile;
use Illuminate\Database\Eloquent\Collection;

class SelfPacedH5pContentService
{
    /**
     * @return Collection<int, SelfPacedH5pContent>
     */
    public function forTutor(TutorProfile $tutor): Collection
    {
        return $tutor->selfPacedH5pContents()->latest()->get();
    }

    public function register(TutorProfile $tutor, string $h5pContentId, ?string $title): SelfPacedH5pContent
    {
        return $tutor->selfPacedH5pContents()->updateOrCreate(
            ['h5p_content_id' => $h5pContentId],
            ['title' => $title],
        );
    }
}
