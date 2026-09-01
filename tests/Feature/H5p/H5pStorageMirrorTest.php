<?php

namespace Tests\Feature\H5p;

use App\Services\H5p\Framework\LaravelH5PStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * LaravelH5PStorage mirrors every write/delete to config('h5p.storage_disk')
 * on top of H5PDefaultStorage's normal local-disk behavior — see the class
 * docblock and H5PKernel::fileStorage() for why (Laravel Cloud's local disk
 * is ephemeral per-instance).
 */
class H5pStorageMirrorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/h5p-mirror-'.uniqid());
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        \H5PCore::deleteFileTree($this->root);

        parent::tearDown();
    }

    private function storage(): LaravelH5PStorage
    {
        return new LaravelH5PStorage($this->root);
    }

    public function test_save_content_mirrors_to_the_configured_disk(): void
    {
        config(['h5p.storage_disk' => 'h5p_mirror']);
        Storage::fake('h5p_mirror');

        $source = $this->root.'/source-'.uniqid();
        mkdir($source, 0755, true);
        file_put_contents($source.'/content.json', '{"foo":"bar"}');

        $this->storage()->saveContent($source, ['id' => 5]);

        $this->assertFileExists("{$this->root}/content/5/content.json");
        Storage::disk('h5p_mirror')->assertExists('h5p/content/5/content.json');
    }

    public function test_delete_content_removes_it_from_the_mirror_disk(): void
    {
        config(['h5p.storage_disk' => 'h5p_mirror']);
        Storage::fake('h5p_mirror');

        $source = $this->root.'/source-'.uniqid();
        mkdir($source, 0755, true);
        file_put_contents($source.'/content.json', '{}');

        $storage = $this->storage();
        $storage->saveContent($source, ['id' => 7]);
        Storage::disk('h5p_mirror')->assertExists('h5p/content/7/content.json');

        $storage->deleteContent(['id' => 7]);

        Storage::disk('h5p_mirror')->assertMissing('h5p/content/7/content.json');
    }

    public function test_scratch_temp_files_are_never_mirrored(): void
    {
        config(['h5p.storage_disk' => 'h5p_mirror']);
        Storage::fake('h5p_mirror');

        $tempDir = $this->root.'/temp/extracted-'.uniqid();
        mkdir($tempDir, 0755, true);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'scratch');
        rewind($stream);

        $this->storage()->saveFileFromZip($tempDir, 'file.txt', $stream);

        $this->assertFileExists("{$tempDir}/file.txt");
        Storage::disk('h5p_mirror')->assertDirectoryEmpty('h5p');
    }

    public function test_mirroring_is_skipped_without_a_configured_disk(): void
    {
        config(['h5p.storage_disk' => null]);

        $source = $this->root.'/source-'.uniqid();
        mkdir($source, 0755, true);
        file_put_contents($source.'/content.json', '{}');

        $this->storage()->saveContent($source, ['id' => 9]);

        $this->assertFileExists("{$this->root}/content/9/content.json");
    }
}
