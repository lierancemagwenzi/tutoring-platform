<?php

namespace Tests\Feature\H5p;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * H5pAssetController falls back to config('h5p.storage_disk') when a
 * library/content file is missing locally — covers a file created by a
 * different instance, or local disk wiped by a Laravel Cloud redeploy (see
 * LaravelH5PStorage, which writes the mirror side).
 */
class H5pAssetMirrorFallbackTest extends TestCase
{
    private string $localFile;

    protected function tearDown(): void
    {
        if (isset($this->localFile) && is_file($this->localFile)) {
            unlink($this->localFile);
            @rmdir(dirname($this->localFile));
        }

        parent::tearDown();
    }

    public function test_serves_a_library_file_missing_locally_from_the_mirror_disk(): void
    {
        config(['h5p.storage_disk' => 'h5p_mirror']);
        Storage::fake('h5p_mirror');

        $library = 'Test.MirrorFallback-'.uniqid();
        Storage::disk('h5p_mirror')->put("h5p/libraries/{$library}/library.json", '{"mirrored":true}');

        $this->localFile = storage_path("app/h5p/libraries/{$library}/library.json");
        $this->assertFileDoesNotExist($this->localFile);

        $response = $this->get("/h5p-assets/libraries/{$library}/library.json");

        $response->assertOk();
        $this->assertSame('{"mirrored":true}', $response->streamedContent());
        $this->assertFileExists($this->localFile);
    }

    public function test_404s_when_the_file_is_on_neither_disk(): void
    {
        config(['h5p.storage_disk' => 'h5p_mirror']);
        Storage::fake('h5p_mirror');

        $response = $this->get('/h5p-assets/libraries/Nothing.Here-1.0/library.json');

        $response->assertNotFound();
    }
}
