<?php

namespace App\Services\H5p\Framework;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * The Laravel adapter for H5PFrameworkInterface (see
 * vendor/h5p/h5p-core/h5p.classes.php) — this is the piece every H5P PHP
 * integration (Drupal, WordPress, Moodle) implements to wire the shared H5P
 * core library to its own storage/permissions.
 *
 * Like the old Node h5p-server it replaces, H5P content here is a shared,
 * platform-wide library with no per-tutor ownership at this layer —
 * hasPermission() always allows, matching the old service's
 * LaissezFairePermissionSystem. Laravel's controllers (see
 * App\Services\LessonBlocks\H5pBlockHandler) enforce authorization above
 * this layer, exactly as before.
 */
class LaravelH5PFramework implements \H5PFrameworkInterface
{
    /** @var array<string, string[]> */
    protected array $messages = ['error' => [], 'info' => []];

    public function getPlatformInfo()
    {
        return [
            'name' => 'Laravel',
            'version' => app()->version(),
            'h5pVersion' => \H5PCore::$coreApi['majorVersion'].'.'.\H5PCore::$coreApi['minorVersion'],
        ];
    }

    public function fetchExternalData($url, $data = null, $blocking = true, $stream = null, $fullData = false, $headers = [], $files = [], $method = 'POST')
    {
        $request = Http::withHeaders($headers);

        foreach ($files as $name => $path) {
            $request = $request->attach($name, file_get_contents($path), basename($path));
        }

        // H5PEditorAjax::callHubEndpoint() (vendor code) calls this with
        // $method defaulting to 'POST' and $data left null when downloading
        // a content type package — but h5p.org's Hub API now 403s POST on
        // that endpoint ("distribution supports only cachable requests",
        // a CloudFront method restriction). A POST with no body has nothing
        // to submit anyway, so treat "$method is POST but there's no data"
        // as the real signal to use GET instead — this doesn't affect the
        // registration/content-type-cache calls (fetchLibrariesMetadata),
        // which always pass real $data and still POST correctly.
        $method = ($method === 'POST' && empty($data)) ? 'GET' : $method;

        $response = $method === 'GET'
            ? $request->get($url, $data ?? [])
            : $request->post($url, $data ?? []);

        if ($stream !== null) {
            file_put_contents($stream, $response->body());
        }

        if ($fullData) {
            return [
                'headers' => $response->headers(),
                'data' => $response->body(),
                'status' => $response->status(),
            ];
        }

        return $response->successful() ? $response->body() : null;
    }

    public function setLibraryTutorialUrl($machineName, $tutorialUrl)
    {
        DB::table('h5p_libraries')
            ->where('machine_name', $machineName)
            ->update(['tutorial_url' => $tutorialUrl]);
    }

    public function setErrorMessage($message, $code = null)
    {
        $this->messages['error'][] = $code ? "[{$code}] {$message}" : $message;
    }

    public function setInfoMessage($message)
    {
        $this->messages['info'][] = $message;
    }

    public function getMessages($type)
    {
        $messages = $this->messages[$type] ?? [];
        $this->messages[$type] = [];

        return $messages;
    }

    public function t($message, $replacements = [])
    {
        // No i18n layer for H5P core/editor strings in this app — just apply
        // the interface's documented !/@/%-prefixed placeholder substitution.
        $substitutions = [];
        foreach ($replacements as $key => $value) {
            $substitutions[$key] = str_starts_with((string) $key, '@')
                ? e($value)
                : $value;
        }

        return strtr($message, $substitutions);
    }

    public function getLibraryFileUrl($libraryFolderName, $fileName)
    {
        return route('h5p.libraries.file', ['library' => $libraryFolderName, 'file' => $fileName]);
    }

    public function getUploadedH5pFolderPath()
    {
        $path = storage_path('app/h5p-temp/upload');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    public function getUploadedH5pPath()
    {
        return $this->getUploadedH5pFolderPath().'.h5p';
    }

    public function loadAddons()
    {
        return [];
    }

    public function getLibraryConfig($libraries = null)
    {
        return [];
    }

    public function loadLibraries()
    {
        $rows = DB::table('h5p_libraries')
            ->orderBy('title')
            ->orderByDesc('major_version')
            ->orderByDesc('minor_version')
            ->orderByDesc('patch_version')
            ->get();

        $libraries = [];
        foreach ($rows as $row) {
            $libraries[$row->machine_name][] = (object) [
                'id' => $row->id,
                'title' => $row->title,
                'machineName' => $row->machine_name,
                'majorVersion' => $row->major_version,
                'minorVersion' => $row->minor_version,
                'patchVersion' => $row->patch_version,
                'runnable' => $row->runnable,
                'restricted' => $row->restricted,
                'isOld' => false,
            ];
        }

        return $libraries;
    }

    public function getAdminUrl()
    {
        // No H5P library-management admin screen in this app yet.
        return '';
    }

    public function getLibraryId($machineName, $majorVersion = null, $minorVersion = null)
    {
        $query = DB::table('h5p_libraries')->where('machine_name', $machineName);

        if ($majorVersion !== null) {
            $query->where('major_version', $majorVersion);
        }
        if ($minorVersion !== null) {
            $query->where('minor_version', $minorVersion);
        }

        $library = $query->orderByDesc('major_version')
            ->orderByDesc('minor_version')
            ->orderByDesc('patch_version')
            ->first();

        return $library->id ?? false;
    }

    public function getWhitelist($isLibrary, $defaultContentWhitelist, $defaultLibraryWhitelist)
    {
        return $isLibrary
            ? "{$defaultContentWhitelist} {$defaultLibraryWhitelist}"
            : $defaultContentWhitelist;
    }

    public function isPatchedLibrary($library)
    {
        $existing = DB::table('h5p_libraries')
            ->where('machine_name', $library['machineName'])
            ->where('major_version', $library['majorVersion'])
            ->where('minor_version', $library['minorVersion'])
            ->first();

        return $existing !== null && $existing->patch_version < $library['patchVersion'];
    }

    public function isInDevMode()
    {
        return false;
    }

    public function mayUpdateLibraries()
    {
        // Gated by Laravel route middleware above this layer, not here.
        return true;
    }

    public function saveLibraryData(&$libraryData, $new = true)
    {
        $row = [
            'machine_name' => $libraryData['machineName'],
            'title' => $libraryData['title'],
            'major_version' => $libraryData['majorVersion'],
            'minor_version' => $libraryData['minorVersion'],
            'patch_version' => $libraryData['patchVersion'],
            'runnable' => $libraryData['runnable'] ?? false,
            'fullscreen' => $libraryData['fullscreen'] ?? false,
            'has_icon' => $libraryData['hasIcon'] ?? false,
            'embed_types' => $this->csvFromList($libraryData['embedTypes'] ?? null),
            'preloaded_js' => $this->csvFromPathList($libraryData['preloadedJs'] ?? null),
            'preloaded_css' => $this->csvFromPathList($libraryData['preloadedCss'] ?? null),
            'drop_library_css' => $this->csvFromKeyList($libraryData['dropLibraryCss'] ?? null, 'machineName'),
            // H5PValidator::getLibraryData() reads semantics.json via
            // getJsonData($path, true) — already a validated JSON string,
            // not a decoded array, so it's stored as-is (json_encode()-ing
            // it here would double-encode it into a JSON string of a JSON
            // string, which is what happened before this was caught testing
            // a real installed library's semantics rather than a hand-typed
            // test fixture).
            'semantics' => $libraryData['semantics'] ?? null,
            // Already boolified+encoded by H5PStorage before this is called.
            'metadata_settings' => $libraryData['metadataSettings'] ?? null,
            'updated_at' => now(),
        ];

        if ($new && empty($libraryData['libraryId'])) {
            $row['created_at'] = now();
            $libraryData['libraryId'] = DB::table('h5p_libraries')->insertGetId($row);
        } else {
            DB::table('h5p_libraries')->where('id', $libraryData['libraryId'])->update($row);
        }

        // H5PValidator::getLibraryData() populates $libraryData['language']
        // from the library's own language/*.json files (e.g. en.json) when
        // present — each value already a validated JSON string, same as
        // 'semantics' above. Without this, H5peditorStorage::getLanguage()
        // has nothing to return and editor widgets that pull their own UI
        // strings this way (H5PEditor.WizardSettings, H5PEditor.RangeList,
        // etc — anything beyond the static core editor/language/en.js
        // strings) render "Missing translations for library X".
        if (! empty($libraryData['language'])) {
            foreach ($libraryData['language'] as $languageCode => $languageJson) {
                DB::table('h5p_libraries_languages')->updateOrInsert(
                    ['library_id' => $libraryData['libraryId'], 'language_code' => $languageCode],
                    ['language_json' => $languageJson],
                );
            }
        }
    }

    public function insertContent($content, $contentMainId = null)
    {
        return DB::table('h5p_content')->insertGetId($this->contentRow($content) + ['created_at' => now(), 'updated_at' => now()]);
    }

    public function updateContent($content, $contentMainId = null)
    {
        DB::table('h5p_content')->where('id', $content['id'])->update($this->contentRow($content) + ['updated_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function contentRow(array $content): array
    {
        $metadata = \H5PMetadata::toDBArray($content['metadata'] ?? []);

        $row = [
            'library_id' => $content['library']['libraryId'],
            'params' => $content['params'],
            'embed_type' => $content['embedType'] ?? 'div',
            'disabled_features' => $content['disable'] ?? 0,
            'slug' => $content['slug'] ?? null,
            'filtered_parameters' => $content['filtered'] ?? null,
        ];

        foreach ($metadata as $field => $value) {
            $row['metadata_'.($field === 'a11y_title' ? 'a11y_title' : $field)] = $value;
        }

        return $row;
    }

    public function resetContentUserData($contentId)
    {
        // No H5P xAPI/user-state tracking owned by this layer — attempt
        // results live in App\Models\Attempt, outside H5P's own storage.
    }

    public function saveLibraryDependencies($libraryId, $dependencies, $dependency_type)
    {
        foreach ($dependencies as $dependency) {
            $requiredId = $this->getLibraryId($dependency['machineName'], $dependency['majorVersion'], $dependency['minorVersion']);

            if ($requiredId) {
                DB::table('h5p_libraries_libraries')->insert([
                    'library_id' => $libraryId,
                    'required_library_id' => $requiredId,
                    'dependency_type' => $dependency_type,
                ]);
            }
        }
    }

    public function copyLibraryUsage($contentId, $copyFromId, $contentMainId = null)
    {
        $rows = DB::table('h5p_content_libraries')->where('content_id', $copyFromId)->get();

        foreach ($rows as $row) {
            DB::table('h5p_content_libraries')->insert([
                'content_id' => $contentId,
                'library_id' => $row->library_id,
                'dependency_type' => $row->dependency_type,
                'drop_css' => $row->drop_css,
                'weight' => $row->weight,
            ]);
        }
    }

    public function deleteContentData($contentId)
    {
        DB::table('h5p_content')->where('id', $contentId)->delete();
    }

    public function deleteLibraryUsage($contentId)
    {
        DB::table('h5p_content_libraries')->where('content_id', $contentId)->delete();
    }

    public function saveLibraryUsage($contentId, $librariesInUse)
    {
        $weight = 0;
        foreach ($librariesInUse as $dependency) {
            DB::table('h5p_content_libraries')->insert([
                'content_id' => $contentId,
                'library_id' => $dependency['library']['libraryId'],
                'dependency_type' => $dependency['type'],
                'drop_css' => filled($dependency['library']['dropLibraryCss'] ?? null),
                'weight' => $weight++,
            ]);
        }
    }

    public function getLibraryUsage($libraryId, $skipContent = false)
    {
        return [
            'content' => $skipContent ? 0 : DB::table('h5p_content')->where('library_id', $libraryId)->count(),
            'libraries' => DB::table('h5p_libraries_libraries')->where('required_library_id', $libraryId)->count(),
        ];
    }

    public function loadLibrary($machineName, $majorVersion, $minorVersion)
    {
        $library = DB::table('h5p_libraries')
            ->where('machine_name', $machineName)
            ->where('major_version', $majorVersion)
            ->where('minor_version', $minorVersion)
            ->orderByDesc('patch_version')
            ->first();

        if (! $library) {
            return false;
        }

        $dependencies = DB::table('h5p_libraries_libraries as ll')
            ->join('h5p_libraries as l', 'l.id', '=', 'll.required_library_id')
            ->where('ll.library_id', $library->id)
            ->select('ll.dependency_type', 'l.machine_name', 'l.major_version', 'l.minor_version')
            ->get();

        $result = [
            'libraryId' => $library->id,
            'title' => $library->title,
            'machineName' => $library->machine_name,
            'majorVersion' => $library->major_version,
            'minorVersion' => $library->minor_version,
            'patchVersion' => $library->patch_version,
            'runnable' => $library->runnable,
            'fullscreen' => $library->fullscreen,
            'embedTypes' => $library->embed_types,
            'preloadedJs' => $library->preloaded_js,
            'preloadedCss' => $library->preloaded_css,
            'dropLibraryCss' => $library->drop_library_css,
            'semantics' => $library->semantics,
            'preloadedDependencies' => [],
            'dynamicDependencies' => [],
            'editorDependencies' => [],
        ];

        $map = ['preloaded' => 'preloadedDependencies', 'dynamic' => 'dynamicDependencies', 'editor' => 'editorDependencies'];
        foreach ($dependencies as $dependency) {
            $result[$map[$dependency->dependency_type]][] = [
                'machineName' => $dependency->machine_name,
                'majorVersion' => $dependency->major_version,
                'minorVersion' => $dependency->minor_version,
            ];
        }

        return $result;
    }

    public function loadLibrarySemantics($machineName, $majorVersion, $minorVersion)
    {
        return DB::table('h5p_libraries')
            ->where('machine_name', $machineName)
            ->where('major_version', $majorVersion)
            ->where('minor_version', $minorVersion)
            ->value('semantics');
    }

    public function alterLibrarySemantics(&$semantics, $machineName, $majorVersion, $minorVersion)
    {
        // No semantics customization hooks in this app.
    }

    public function deleteLibraryDependencies($libraryId)
    {
        DB::table('h5p_libraries_libraries')->where('library_id', $libraryId)->delete();
    }

    public function lockDependencyStorage()
    {
        // Single-process/non-clustered writes for now; revisit if H5P library
        // installs ever run concurrently.
    }

    public function unlockDependencyStorage()
    {
        //
    }

    public function deleteLibrary($library)
    {
        $id = is_array($library) ? $library['libraryId'] : $library->id;
        DB::table('h5p_libraries')->where('id', $id)->delete();
    }

    public function loadContent($id)
    {
        $content = DB::table('h5p_content as c')
            ->join('h5p_libraries as l', 'l.id', '=', 'c.library_id')
            ->where('c.id', $id)
            ->select('c.*', 'l.id as lib_id', 'l.machine_name', 'l.major_version', 'l.minor_version', 'l.embed_types', 'l.fullscreen')
            ->first();

        if (! $content) {
            return null;
        }

        return [
            'id' => $content->id,
            'contentId' => $content->id,
            'params' => $content->params,
            'filtered' => $content->filtered_parameters,
            'embedType' => $content->embed_type,
            'disable' => $content->disabled_features,
            'slug' => $content->slug,
            'title' => $content->metadata_title,
            'language' => $content->metadata_default_language ?? 'en',
            'libraryId' => $content->lib_id,
            'libraryName' => $content->machine_name,
            'libraryMajorVersion' => $content->major_version,
            'libraryMinorVersion' => $content->minor_version,
            'libraryEmbedTypes' => $content->embed_types,
            'libraryFullscreen' => $content->fullscreen,
            'metadata' => [
                'title' => $content->metadata_title,
                'a11yTitle' => $content->metadata_a11y_title,
                'authors' => $content->metadata_authors ? json_decode($content->metadata_authors, true) : [],
                'source' => $content->metadata_source,
                'license' => $content->metadata_license,
                'licenseVersion' => $content->metadata_license_version,
                'licenseExtras' => $content->metadata_license_extras,
                'authorComments' => $content->metadata_author_comments,
                'yearFrom' => $content->metadata_year_from,
                'yearTo' => $content->metadata_year_to,
                'changes' => $content->metadata_changes ? json_decode($content->metadata_changes, true) : [],
                'defaultLanguage' => $content->metadata_default_language,
            ],
        ];
    }

    public function loadContentDependencies($id, $type = null)
    {
        $query = DB::table('h5p_content_libraries as cl')
            ->join('h5p_libraries as l', 'l.id', '=', 'cl.library_id')
            ->where('cl.content_id', $id)
            ->select('l.*', 'cl.dependency_type', 'cl.drop_css', 'cl.weight');

        if ($type !== null) {
            $query->where('cl.dependency_type', $type);
        }

        return $query->orderBy('cl.weight')->get()->map(fn ($row) => [
            'libraryId' => $row->id,
            'machineName' => $row->machine_name,
            'majorVersion' => $row->major_version,
            'minorVersion' => $row->minor_version,
            'patchVersion' => $row->patch_version,
            'preloadedJs' => $row->preloaded_js,
            'preloadedCss' => $row->preloaded_css,
            'dropCss' => $row->drop_css ? $row->machine_name : null,
        ])->all();
    }

    public function getOption($name, $default = null)
    {
        $value = DB::table('h5p_options')->where('key', $name)->value('value');

        return $value ?? $default;
    }

    public function setOption($name, $value)
    {
        DB::table('h5p_options')->updateOrInsert(['key' => $name], ['value' => $value]);
    }

    public function updateContentFields($id, $fields)
    {
        $map = ['filtered' => 'filtered_parameters', 'slug' => 'slug'];
        $row = [];
        foreach ($fields as $field => $value) {
            $row[$map[$field] ?? $field] = $value;
        }

        DB::table('h5p_content')->where('id', $id)->update($row);
    }

    public function clearFilteredParameters($library_ids)
    {
        DB::table('h5p_content')->whereIn('library_id', $library_ids)->update(['filtered_parameters' => null]);
    }

    public function getNumNotFiltered()
    {
        return DB::table('h5p_content')->whereNull('filtered_parameters')->count();
    }

    public function getNumContent($libraryId, $skip = null)
    {
        $query = DB::table('h5p_content')->where('library_id', $libraryId);
        if ($skip) {
            $query->whereNotIn('id', $skip);
        }

        return $query->count();
    }

    public function isContentSlugAvailable($slug)
    {
        return ! DB::table('h5p_content')->where('slug', $slug)->exists();
    }

    public function getLibraryStats($type)
    {
        // No H5P event log kept in this app — attempt/completion stats live
        // in App\Models\Attempt instead.
        return [];
    }

    public function getNumAuthors()
    {
        return 0;
    }

    public function saveCachedAssets($key, $libraries)
    {
        foreach ($libraries as $library) {
            DB::table('h5p_libraries_cachedassets')->insert([
                'hash' => $key,
                'library_id' => $library['libraryId'],
            ]);
        }
    }

    public function deleteCachedAssets($library_id)
    {
        $hashes = DB::table('h5p_libraries_cachedassets')->where('library_id', $library_id)->pluck('hash')->all();

        if (! empty($hashes)) {
            DB::table('h5p_libraries_cachedassets')->whereIn('hash', $hashes)->delete();
        }

        return $hashes;
    }

    public function getLibraryContentCount()
    {
        return DB::table('h5p_content as c')
            ->join('h5p_libraries as l', 'l.id', '=', 'c.library_id')
            ->selectRaw("concat(l.machine_name, ' ', l.major_version, '.', l.minor_version) as lib, count(*) as total")
            ->groupBy('l.machine_name', 'l.major_version', 'l.minor_version')
            ->pluck('total', 'lib')
            ->all();
    }

    public function afterExportCreated($content, $filename)
    {
        //
    }

    public function hasPermission($permission, $id = null)
    {
        // Shared content library, no per-tutor restriction at this layer —
        // see class docblock.
        return true;
    }

    public function replaceContentTypeCache($contentTypeCache)
    {
        $this->setOption('content_type_cache', json_encode($contentTypeCache));
    }

    public function libraryHasUpgrade($library)
    {
        $newer = DB::table('h5p_libraries')
            ->where('machine_name', $library['machineName'])
            ->where(function ($query) use ($library) {
                $query->where('major_version', '>', $library['majorVersion'])
                    ->orWhere(function ($query) use ($library) {
                        $query->where('major_version', $library['majorVersion'])
                            ->where('minor_version', '>', $library['minorVersion']);
                    });
            })
            ->exists();

        return $newer;
    }

    public function replaceContentHubMetadataCache($metadata, $lang)
    {
        $this->setOption("content_hub_metadata_{$lang}", json_encode($metadata));
    }

    public function getContentHubMetadataCache($lang = 'en')
    {
        $value = $this->getOption("content_hub_metadata_{$lang}");

        return $value ? json_decode($value) : null;
    }

    public function getContentHubMetadataChecked($lang = 'en')
    {
        return $this->getOption("content_hub_metadata_checked_{$lang}");
    }

    public function setContentHubMetadataChecked($time, $lang = 'en')
    {
        $this->setOption("content_hub_metadata_checked_{$lang}", $time);

        return true;
    }

    public function resetHubOrganizationData()
    {
        DB::table('h5p_options')->where('key', 'like', 'content_hub_%')->delete();
    }

    protected function csvFromList(?array $items): ?string
    {
        return $items === null ? null : implode(', ', $items);
    }

    protected function csvFromPathList(?array $items): ?string
    {
        return $items === null ? null : implode(', ', array_column($items, 'path'));
    }

    protected function csvFromKeyList(?array $items, string $key): ?string
    {
        return $items === null ? null : implode(', ', array_column($items, $key));
    }
}
