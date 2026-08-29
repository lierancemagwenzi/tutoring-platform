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
        // Snake_case, not the camelCase the interface docblock implies —
        // verified against the actual consumer, H5peditor::
        // mergeLocalLibsIntoCachedLibs(), which reads has_icon/machine_name/
        // major_version/etc directly (it mirrors a DB-cache-table row shape,
        // same convention as h5p_libraries itself).
        return DB::table('h5p_libraries as l1')
            ->select('l1.id', 'l1.machine_name', 'l1.title', 'l1.major_version', 'l1.minor_version', 'l1.patch_version', 'l1.has_icon', 'l1.restricted')
            ->whereRaw('l1.id = (
                select l2.id from h5p_libraries l2
                where l2.machine_name = l1.machine_name
                order by l2.major_version desc, l2.minor_version desc, l2.patch_version desc
                limit 1
            )')
            ->get()
            ->map(fn ($row) => (object) [
                'id' => $row->id,
                'machine_name' => $row->machine_name,
                'title' => $row->title,
                'major_version' => (int) $row->major_version,
                'minor_version' => (int) $row->minor_version,
                'patch_version' => (int) $row->patch_version,
                'has_icon' => (bool) $row->has_icon,
                'patch_version_in_folder_name' => false,
                'restricted' => (bool) $row->restricted,
            ])
            ->all();
    }

    public function getContentTypeCache($machineName = null)
    {
        // The Hub's own cache shape keys the list "contentTypes" and
        // identifies each entry by "id" (e.g. "H5P.Accordion") — verified
        // against a real h5p.org response, not the h5p-core docblock (which
        // doesn't specify the shape). H5peditor::getCachedLibsMap() (called
        // by getUserSpecificContentTypeCache()) expects a different, flatter
        // snake_case shape though — the row shape of the DB-backed hub cache
        // table other platform integrations use — so entries are remapped
        // here rather than passed through raw.
        $cache = DB::table('h5p_options')->where('key', 'content_type_cache')->value('value');
        $entries = $cache ? (json_decode($cache)->contentTypes ?? []) : [];
        $libraries = array_map($this->mapHubEntry(...), $entries);

        if ($machineName === null) {
            return $libraries;
        }

        foreach ($libraries as $library) {
            if ($library->machine_name === $machineName) {
                return $library;
            }
        }

        return null;
    }

    protected function mapHubEntry(object $entry): object
    {
        return (object) [
            'id' => crc32($entry->id),
            'machine_name' => $entry->id,
            'major_version' => (int) ($entry->version->major ?? 0),
            'minor_version' => (int) ($entry->version->minor ?? 0),
            'patch_version' => (int) ($entry->version->patch ?? 0),
            'h5p_major_version' => (int) ($entry->coreApiVersionNeeded->major ?? 0),
            'h5p_minor_version' => (int) ($entry->coreApiVersionNeeded->minor ?? 0),
            'title' => $entry->title ?? '',
            'summary' => $entry->summary ?? '',
            'description' => $entry->description ?? '',
            'icon' => $entry->icon ?? null,
            'created_at' => isset($entry->createdAt) ? strtotime($entry->createdAt) : 0,
            'updated_at' => isset($entry->updatedAt) ? strtotime($entry->updatedAt) : 0,
            'is_recommended' => $entry->isRecommended ?? false,
            'popularity' => (int) ($entry->popularity ?? 0),
            'screenshots' => json_encode($entry->screenshots ?? []),
            'license' => json_encode($entry->license ?? null),
            'owner' => $entry->owner ?? null,
        ];
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
