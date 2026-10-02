<?php

namespace Tests\Feature\H5p;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Browsers fetch video in ranges — and an MP4 with its index at the end
 * can't start until the tail range returns — so H5P assets must honour
 * Range requests rather than always sending the whole file.
 */
class H5pAssetRangeRequestTest extends TestCase
{
    private string $dir;

    private string $contents;

    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway content id so this never touches real content.
        $this->dir = storage_path('app/h5p/content/987654321/videos');
        File::ensureDirectoryExists($this->dir);
        $this->contents = implode('', range('a', 'z')).str_repeat('0123456789', 10);
        file_put_contents("{$this->dir}/clip.mp4", $this->contents);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->dir));

        parent::tearDown();
    }

    private function getRange(string $range)
    {
        $response = $this->get('/h5p-assets/content/987654321/videos/clip.mp4', ['Range' => $range]);
        // BinaryFileResponse only applies the range when sent — capture it.
        ob_start();
        $response->baseResponse->sendContent();

        return [$response, ob_get_clean()];
    }

    public function test_a_range_from_an_offset_returns_just_that_part(): void
    {
        [$response, $body] = $this->getRange('bytes=100-');

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 100-125/126');
        $this->assertSame(substr($this->contents, 100), $body);
    }

    public function test_a_suffix_range_returns_the_end_of_the_file(): void
    {
        [$response, $body] = $this->getRange('bytes=-6');

        $response->assertStatus(206);
        $this->assertSame('456789', $body);
    }

    public function test_a_bounded_range_returns_exactly_those_bytes(): void
    {
        [$response, $body] = $this->getRange('bytes=0-4');

        $response->assertStatus(206);
        $response->assertHeader('Accept-Ranges', 'bytes');
        $this->assertSame('abcde', $body);
    }

    public function test_without_a_range_the_whole_file_is_served_with_the_right_type(): void
    {
        $response = $this->get('/h5p-assets/content/987654321/videos/clip.mp4');

        $response->assertOk();
        $this->assertStringStartsWith('video/mp4', $response->headers->get('Content-Type'));
    }
}
