<?php

namespace Tests\Feature\H5p;

use App\Services\H5p\H5PKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class H5pDependencyOrderTest extends TestCase
{
    use RefreshDatabase;

    private function library(string $machineName): int
    {
        return DB::table('h5p_libraries')->insertGetId([
            'machine_name' => $machineName, 'title' => $machineName, 'major_version' => 1, 'minor_version' => 0,
            'patch_version' => 0, 'runnable' => true, 'fullscreen' => false, 'has_icon' => false, 'restricted' => false,
            'embed_types' => 'div', 'semantics' => '[]', 'preloaded_js' => 'js/main.js', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_dependencies_load_in_h5ps_computed_order_not_list_order(): void
    {
        $question = $this->library('H5P.Question');
        $multiChoice = $this->library('H5P.MultiChoice');
        DB::table('h5p_content')->insert([
            'id' => 1, 'library_id' => $multiChoice, 'params' => '{}', 'embed_type' => 'div',
            'slug' => 'q', 'metadata_title' => 'Q', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $framework = app(H5PKernel::class)->core()->h5pF;

        // H5P lists the content's own library first but weights its
        // dependency lower, so the dependency must load first.
        $framework->saveLibraryUsage(1, [
            'preloaded-H5P.MultiChoice' => ['library' => ['libraryId' => $multiChoice], 'type' => 'preloaded', 'weight' => 2],
            'preloaded-H5P.Question' => ['library' => ['libraryId' => $question], 'type' => 'preloaded', 'weight' => 1],
        ]);

        $this->assertSame(
            ['H5P.Question', 'H5P.MultiChoice'],
            array_column($framework->loadContentDependencies(1, 'preloaded'), 'machineName'),
        );
    }
}
