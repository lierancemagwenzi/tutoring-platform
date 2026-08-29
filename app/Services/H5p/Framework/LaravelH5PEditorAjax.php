<?php

namespace App\Services\H5p\Framework;

use Illuminate\Support\Facades\DB;

/**
 * Laravel adapter for H5PEditorAjaxInterface (see
 * vendor/h5p/h5p-editor/h5peditor-ajax.interface.php), consumed internally
 * by the bundled H5PEditorAjax helper class. The actual ajax HTTP routes
 * that call into H5PEditorAjax live in Phase 2's ajax controller — this
 * class only supplies the data/checks that helper needs.
 */
class LaravelH5PEditorAjax implements \H5PEditorAjaxInterface
{
    public function getLatestLibraryVersions()
    {
        return DB::table('h5p_libraries as l1')
            ->select('l1.id', 'l1.machine_name', 'l1.major_version', 'l1.minor_version', 'l1.patch_version')
            ->whereRaw('l1.id = (
                select l2.id from h5p_libraries l2
                where l2.machine_name = l1.machine_name
                order by l2.major_version desc, l2.minor_version desc, l2.patch_version desc
                limit 1
            )')
            ->get()
            ->map(fn ($row) => (object) [
                'machineName' => $row->machine_name,
                'majorVersion' => $row->major_version,
                'minorVersion' => $row->minor_version,
                'patchVersion' => $row->patch_version,
            ])
            ->all();
    }

    public function getContentTypeCache($machineName = null)
    {
        $cache = DB::table('h5p_options')->where('key', 'content_type_cache')->value('value');
        $libraries = $cache ? (json_decode($cache)->libraries ?? []) : [];

        if ($machineName === null) {
            return $libraries;
        }

        foreach ($libraries as $library) {
            if (($library->machineName ?? null) === $machineName) {
                return $library;
            }
        }

        return null;
    }

    public function getAuthorsRecentlyUsedLibraries()
    {
        // H5P content here is a shared, platform-wide library with no
        // per-author authorship tracked (see LaravelH5PFramework docblock) —
        // nothing to rank by recency.
        return [];
    }

    public function validateEditorToken($token)
    {
        // CSRF/auth for the editor's ajax surface is enforced by Laravel's
        // own route middleware (auth + tutor gate), not H5P's own token
        // scheme — see Phase 2's ajax routes.
        return true;
    }

    public function getTranslations($libraries, $language_code)
    {
        $translations = [];

        foreach ($libraries as $libraryString) {
            if (! preg_match('/(.+)\s(\d+)\.(\d+)$/', $libraryString, $matches)) {
                continue;
            }

            [, $machineName, $major, $minor] = $matches;

            $library = DB::table('h5p_libraries')
                ->where('machine_name', $machineName)
                ->where('major_version', $major)
                ->where('minor_version', $minor)
                ->first();

            if (! $library) {
                continue;
            }

            $json = DB::table('h5p_libraries_languages')
                ->where('library_id', $library->id)
                ->where('language_code', $language_code)
                ->value('language_json');

            if ($json) {
                $translations[$libraryString] = json_decode($json);
            }
        }

        return $translations;
    }
}
