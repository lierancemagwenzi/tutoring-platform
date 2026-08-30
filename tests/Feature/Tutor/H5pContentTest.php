<?php

namespace Tests\Feature\Tutor;

use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithH5pLibrary;
use Tests\TestCase;

/**
 * H5P content is stored locally via the official H5P PHP libraries (see
 * App\Services\H5p\H5PService/H5PKernel) — these tests seed real
 * h5p_libraries/h5p_content rows (via InteractsWithH5pLibrary) rather than
 * mocking an HTTP call to the old Node h5p-server. Every piece of content
 * belongs to exactly one tutor and is classified under a Subject/Grade/
 * Curriculum (see App\Models\H5pContentClassification), mirroring
 * CourseTest's tutorWithApprovedSubject() fixture.
 */
class H5pContentTest extends TestCase
{
    use InteractsWithH5pLibrary, RefreshDatabase;

    private Subject $subject;

    private Curriculum $curriculum;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
    }

    private function tutorWithApprovedSubject(): User
    {
        $user = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['user_id' => $user->id]);

        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutorProfile->id,
            'subject_id' => $this->subject->id,
            'status' => 'approved',
        ]);

        $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $this->grade->id]);

        return $user->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function classificationPayload(array $overrides = []): array
    {
        return array_merge([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
        ], $overrides);
    }

    public function test_tutor_can_list_h5p_content(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutor);
        $this->seedH5pContent(123, 'Sample Question');
        $this->seedH5pClassification(123, $tutor->tutorProfile->id, $this->grade->id, $this->subject->id, $this->curriculum->id);

        $response = $this->getJson('/api/tutor/h5p-content');

        $response->assertOk();
        $response->assertJsonPath('contents.0.id', '123');
        $response->assertJsonPath('contents.0.main_library', 'H5P.MultiChoice 1.16');
        $response->assertJsonPath('contents.0.subject.id', $this->subject->id);
    }

    public function test_tutor_can_get_a_new_content_editor_model(): void
    {
        Sanctum::actingAs($this->tutorWithApprovedSubject());

        $response = $this->getJson('/api/tutor/h5p-content/editor-model');

        $response->assertOk();
        $response->assertJsonPath('library', null);
    }

    public function test_tutor_can_get_an_existing_content_editor_model(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutor);
        $this->seedH5pContent(123, 'Sample Question', ['question' => 'What is 2 + 2?']);
        $this->seedH5pClassification(123, $tutor->tutorProfile->id, $this->grade->id, $this->subject->id, $this->curriculum->id);

        $response = $this->getJson('/api/tutor/h5p-content/123/editor-model');

        $response->assertOk();
        $response->assertJsonPath('library', 'H5P.MultiChoice 1.16');
        $response->assertJsonPath('params.metadata.title', 'Sample Question');
        $response->assertJsonPath('classification.subject_id', $this->subject->id);
    }

    public function test_tutor_can_get_a_player_model(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutor);
        $this->seedH5pContent(123);
        $this->seedH5pClassification(123, $tutor->tutorProfile->id, $this->grade->id, $this->subject->id, $this->curriculum->id);

        $response = $this->getJson('/api/tutor/h5p-content/123/player-model');

        $response->assertOk();
        $response->assertJsonPath('integration.contents.cid-123.library', 'H5P.MultiChoice 1.16');
    }

    public function test_tutor_cannot_access_another_tutors_content(): void
    {
        $owner = $this->tutorWithApprovedSubject();
        $this->seedH5pContent(123);
        $this->seedH5pClassification(123, $owner->tutorProfile->id, $this->grade->id, $this->subject->id, $this->curriculum->id);

        Sanctum::actingAs($this->tutorWithApprovedSubject());

        $this->getJson('/api/tutor/h5p-content/123/editor-model')->assertForbidden();
        $this->getJson('/api/tutor/h5p-content/123/player-model')->assertForbidden();
        $this->deleteJson('/api/tutor/h5p-content/123')->assertForbidden();
        $this->get('/api/tutor/h5p-content/123/export')->assertForbidden();
    }

    public function test_tutor_can_create_content_and_payload_is_translated_for_the_h5p_server(): void
    {
        Sanctum::actingAs($this->tutorWithApprovedSubject());
        $this->seedH5pLibrary();

        $response = $this->postJson('/api/tutor/h5p-content', $this->classificationPayload([
            'library' => 'H5P.MultiChoice 1.16',
            'params' => [
                'params' => ['question' => 'What is 2 + 2?'],
                'metadata' => ['title' => 'Sample Question'],
            ],
        ]));

        $response->assertCreated();
        $response->assertJsonPath('metadata.title', 'Sample Question');
        $this->assertSame(
            ['question' => 'What is 2 + 2?'],
            json_decode(DB::table('h5p_content')->where('id', $response->json('id'))->value('params'), true),
        );
        $this->assertDatabaseHas('h5p_content_classifications', [
            'h5p_content_id' => $response->json('id'),
            'subject_id' => $this->subject->id,
        ]);
    }

    public function test_create_requires_library_and_params(): void
    {
        Sanctum::actingAs($this->tutorWithApprovedSubject());

        $response = $this->postJson('/api/tutor/h5p-content', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['library', 'params', 'grade_id', 'subject_id', 'curriculum_id']);
    }

    public function test_tutor_can_update_content(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutor);
        $this->seedH5pContent(123);
        $this->seedH5pClassification(123, $tutor->tutorProfile->id, $this->grade->id, $this->subject->id, $this->curriculum->id);

        $response = $this->patchJson('/api/tutor/h5p-content/123', $this->classificationPayload([
            'library' => 'H5P.MultiChoice 1.16',
            'params' => [
                'params' => ['question' => 'Updated?'],
                'metadata' => ['title' => 'Updated Question'],
            ],
        ]));

        $response->assertOk();
        $response->assertJsonPath('metadata.title', 'Updated Question');
        $this->assertSame('Updated Question', DB::table('h5p_content')->where('id', 123)->value('metadata_title'));
    }

    public function test_tutor_can_delete_content(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutor);
        $this->seedH5pContent(123);
        $this->seedH5pClassification(123, $tutor->tutorProfile->id, $this->grade->id, $this->subject->id, $this->curriculum->id);

        $response = $this->deleteJson('/api/tutor/h5p-content/123');

        $response->assertOk();
        $this->assertDatabaseMissing('h5p_content', ['id' => 123]);
    }

    public function test_tutor_can_export_content(): void
    {
        $tutor = $this->tutorWithApprovedSubject();
        Sanctum::actingAs($tutor);
        $this->seedH5pContent(123);
        $this->seedH5pClassification(123, $tutor->tutorProfile->id, $this->grade->id, $this->subject->id, $this->curriculum->id);

        $response = $this->get('/api/tutor/h5p-content/123/export');

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=123.h5p');
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_tutor_can_import_a_package(): void
    {
        Sanctum::actingAs($this->tutorWithApprovedSubject());
        $this->seedH5pLibrary('H5P.Test', 1, 0);

        $response = $this->postJson('/api/tutor/h5p-content/import', $this->classificationPayload([
            'file' => $this->fakeH5pPackage(),
        ]));

        $response->assertCreated();
        $response->assertJsonPath('metadata.title', 'Imported content');
        $this->assertDatabaseHas('h5p_content_classifications', [
            'h5p_content_id' => $response->json('id'),
            'subject_id' => $this->subject->id,
        ]);
    }

    public function test_import_requires_an_h5p_extension(): void
    {
        Sanctum::actingAs($this->tutorWithApprovedSubject());

        $response = $this->postJson('/api/tutor/h5p-content/import', $this->classificationPayload([
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_guest_cannot_access_h5p_content_endpoints(): void
    {
        $response = $this->getJson('/api/tutor/h5p-content');

        $response->assertUnauthorized();
    }

    /**
     * A minimal but real, valid .h5p package: h5p.json + content/content.json
     * + a bundled H5P.Test-1.0 library folder, matching exactly what
     * H5PValidator::isValidPackage() requires (see h5pRequired/libraryRequired
     * in vendor/h5p/h5p-core/h5p.classes.php).
     */
    private function fakeH5pPackage(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'h5p').'.h5p';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('h5p.json', json_encode([
            'title' => 'Imported content',
            'language' => 'en',
            'mainLibrary' => 'H5P.Test',
            'embedTypes' => ['div'],
            'preloadedDependencies' => [
                ['machineName' => 'H5P.Test', 'majorVersion' => 1, 'minorVersion' => 0],
            ],
        ]));
        $zip->addFromString('content/content.json', json_encode(['question' => 'Imported?']));
        $zip->addFromString('H5P.Test-1.0/library.json', json_encode([
            'title' => 'Test',
            'machineName' => 'H5P.Test',
            'majorVersion' => 1,
            'minorVersion' => 0,
            'patchVersion' => 0,
            'runnable' => 1,
        ]));
        $zip->addFromString('H5P.Test-1.0/semantics.json', json_encode([
            ['name' => 'question', 'type' => 'text', 'label' => 'Question'],
        ]));
        $zip->close();

        return new UploadedFile($path, 'sample.h5p', 'application/octet-stream', null, true);
    }
}
