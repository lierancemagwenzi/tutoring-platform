<?php

namespace Tests\Feature\Tutor;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * H5P content itself is never stored in Laravel — every request here is
 * proxied to the dedicated H5P server (see h5p-server/ and
 * App\Services\H5p\H5PService), so the H5P server's HTTP responses are
 * faked rather than any local database state.
 */
class H5pContentTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): User
    {
        $user = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $user->id]);

        return $user->fresh();
    }

    public function test_tutor_can_list_h5p_content(): void
    {
        Sanctum::actingAs($this->tutor());

        Http::fake([
            '*/api/content' => Http::response([
                ['id' => '123', 'title' => 'Sample Question', 'mainLibrary' => 'H5P.MultiChoice', 'language' => 'en'],
            ]),
        ]);

        $response = $this->getJson('/api/tutor/h5p-content');

        $response->assertOk();
        $response->assertJsonPath('contents.0.id', '123');
        $response->assertJsonPath('contents.0.main_library', 'H5P.MultiChoice');
    }

    public function test_tutor_can_get_a_new_content_editor_model(): void
    {
        Sanctum::actingAs($this->tutor());

        Http::fake([
            '*/api/content/editor-model' => Http::response([
                'integration' => ['url' => 'http://127.0.0.1:8080'],
                'scripts' => ['/h5p/core/js/h5p.js'],
                'styles' => [],
            ]),
        ]);

        $response = $this->getJson('/api/tutor/h5p-content/editor-model');

        $response->assertOk();
        $response->assertJsonMissing(['library']);
    }

    public function test_tutor_can_get_an_existing_content_editor_model(): void
    {
        Sanctum::actingAs($this->tutor());

        Http::fake([
            '*/api/content/123/editor-model' => Http::response([
                'integration' => [],
                'scripts' => [],
                'styles' => [],
                'library' => 'H5P.MultiChoice 1.16',
                'metadata' => ['title' => 'Sample Question'],
                'params' => ['question' => 'What is 2 + 2?'],
            ]),
        ]);

        $response = $this->getJson('/api/tutor/h5p-content/123/editor-model');

        $response->assertOk();
        $response->assertJsonPath('library', 'H5P.MultiChoice 1.16');
    }

    public function test_tutor_can_get_a_player_model(): void
    {
        Sanctum::actingAs($this->tutor());

        Http::fake([
            '*/api/content/123/player-model' => Http::response([
                'contentId' => '123',
                'dependencies' => [],
            ]),
        ]);

        $response = $this->getJson('/api/tutor/h5p-content/123/player-model');

        $response->assertOk();
        $response->assertJsonPath('contentId', '123');
    }

    public function test_tutor_can_create_content_and_payload_is_translated_for_the_h5p_server(): void
    {
        Sanctum::actingAs($this->tutor());

        Http::fake([
            '*/api/content' => Http::response([
                'id' => '456',
                'metadata' => ['title' => 'Sample Question', 'mainLibrary' => 'H5P.MultiChoice'],
            ], 201),
        ]);

        $response = $this->postJson('/api/tutor/h5p-content', [
            'library' => 'H5P.MultiChoice 1.16',
            'params' => [
                'params' => ['question' => 'What is 2 + 2?'],
                'metadata' => ['title' => 'Sample Question', 'mainLibrary' => 'H5P.MultiChoice'],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('id', '456');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8080/api/content'
                && $request['mainLibraryUbername'] === 'H5P.MultiChoice 1.16'
                && $request['parameters'] === ['question' => 'What is 2 + 2?']
                && $request['metadata']['title'] === 'Sample Question'
                && $request->hasHeader('X-H5P-Api-Key');
        });
    }

    public function test_create_requires_library_and_params(): void
    {
        Sanctum::actingAs($this->tutor());

        $response = $this->postJson('/api/tutor/h5p-content', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['library', 'params']);
    }

    public function test_tutor_can_update_content(): void
    {
        Sanctum::actingAs($this->tutor());

        Http::fake([
            '*/api/content/123' => Http::response([
                'id' => '123',
                'metadata' => ['title' => 'Updated Question'],
            ]),
        ]);

        $response = $this->patchJson('/api/tutor/h5p-content/123', [
            'library' => 'H5P.MultiChoice 1.16',
            'params' => [
                'params' => ['question' => 'Updated?'],
                'metadata' => ['title' => 'Updated Question'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('metadata.title', 'Updated Question');

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && $request->url() === 'http://127.0.0.1:8080/api/content/123');
    }

    public function test_tutor_can_delete_content(): void
    {
        Sanctum::actingAs($this->tutor());

        Http::fake([
            '*/api/content/123' => Http::response(null, 204),
        ]);

        $response = $this->deleteJson('/api/tutor/h5p-content/123');

        $response->assertOk();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'http://127.0.0.1:8080/api/content/123');
    }

    public function test_tutor_can_export_content(): void
    {
        Sanctum::actingAs($this->tutor());

        Http::fake([
            '*/api/content/123/export' => Http::response('binary-h5p-package-bytes', 200, [
                'Content-Type' => 'application/octet-stream',
            ]),
        ]);

        $response = $this->get('/api/tutor/h5p-content/123/export');

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="123.h5p"');
        $this->assertSame('binary-h5p-package-bytes', $response->getContent());
    }

    public function test_tutor_can_import_a_package(): void
    {
        Sanctum::actingAs($this->tutor());

        Http::fake([
            '*/api/content/import' => Http::response([
                'id' => '789',
                'metadata' => ['title' => 'Imported Content'],
            ], 201),
        ]);

        $response = $this->postJson('/api/tutor/h5p-content/import', [
            'file' => UploadedFile::fake()->create('sample.h5p', 100),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('id', '789');
    }

    public function test_import_requires_an_h5p_extension(): void
    {
        Sanctum::actingAs($this->tutor());

        $response = $this->postJson('/api/tutor/h5p-content/import', [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_guest_cannot_access_h5p_content_endpoints(): void
    {
        $response = $this->getJson('/api/tutor/h5p-content');

        $response->assertUnauthorized();
    }
}
