<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Seeds real h5p_libraries/h5p_content rows so tests exercise the actual
 * H5PService (backed by App\Services\H5p\H5PKernel/H5PCore) instead of
 * mocking an HTTP call to the old Node h5p-server.
 */
trait InteractsWithH5pLibrary
{
    protected function seedH5pLibrary(string $machineName = 'H5P.MultiChoice', int $major = 1, int $minor = 16): int
    {
        $id = DB::table('h5p_libraries')->insertGetId([
            'machine_name' => $machineName,
            'title' => str($machineName)->after('.')->headline(),
            'major_version' => $major,
            'minor_version' => $minor,
            'patch_version' => 0,
            'runnable' => true,
            'fullscreen' => false,
            'has_icon' => false,
            'restricted' => false,
            'embed_types' => 'div',
            'semantics' => json_encode([
                ['name' => 'question', 'type' => 'text', 'label' => 'Question'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // H5PStorage::exportLibrary() reads the library folder straight off
        // disk (H5PDefaultStorage) — a DB row alone isn't enough for export
        // to succeed, so mirror what installing a real library leaves behind.
        $folder = storage_path("app/h5p/libraries/{$machineName}-{$major}.{$minor}");
        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
        file_put_contents("{$folder}/library.json", json_encode([
            'title' => $machineName,
            'machineName' => $machineName,
            'majorVersion' => $major,
            'minorVersion' => $minor,
            'patchVersion' => 0,
            'runnable' => 1,
        ]));

        return $id;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function seedH5pContent(
        int $id,
        string $title = 'Sample Question',
        array $params = ['question' => 'What is 2 + 2?'],
        ?int $libraryId = null,
    ): int {
        $libraryId ??= $this->seedH5pLibrary();

        DB::table('h5p_content')->insert([
            'id' => $id,
            'library_id' => $libraryId,
            'params' => json_encode($params),
            'filtered_parameters' => json_encode($params),
            'embed_type' => 'div',
            'slug' => str($title)->slug(),
            'metadata_title' => $title,
            'metadata_default_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * Every piece of H5P content requires a classification row — this is
     * both its ownership record and its Grade/Subject/Curriculum tag (see
     * App\Models\H5pContentClassification).
     */
    protected function seedH5pClassification(
        int $contentId,
        int $tutorProfileId,
        int $gradeId,
        int $subjectId,
        int $curriculumId,
    ): void {
        DB::table('h5p_content_classifications')->insert([
            'h5p_content_id' => $contentId,
            'tutor_profile_id' => $tutorProfileId,
            'grade_id' => $gradeId,
            'subject_id' => $subjectId,
            'curriculum_id' => $curriculumId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
