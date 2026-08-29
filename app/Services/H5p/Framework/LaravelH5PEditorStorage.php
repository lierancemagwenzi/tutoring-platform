<?php

namespace App\Services\H5p\Framework;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Laravel adapter for H5peditorStorage (see
 * vendor/h5p/h5p-editor/h5peditor-storage.interface.php) — the smaller,
 * editor-specific counterpart to LaravelH5PFramework.
 */
class LaravelH5PEditorStorage implements \H5peditorStorage
{
    public function getLanguage($machineName, $majorVersion, $minorVersion, $language)
    {
        $library = DB::table('h5p_libraries')
            ->where('machine_name', $machineName)
            ->where('major_version', $majorVersion)
            ->where('minor_version', $minorVersion)
            ->first();

        if (! $library) {
            return false;
        }

        $json = DB::table('h5p_libraries_languages')
            ->where('library_id', $library->id)
            ->where('language_code', $language)
            ->value('language_json');

        return $json ?? false;
    }

    public function getAvailableLanguages($machineName, $majorVersion, $minorVersion)
    {
        $library = DB::table('h5p_libraries')
            ->where('machine_name', $machineName)
            ->where('major_version', $majorVersion)
            ->where('minor_version', $minorVersion)
            ->first();

        if (! $library) {
            return ['en'];
        }

        $codes = DB::table('h5p_libraries_languages')
            ->where('library_id', $library->id)
            ->pluck('language_code')
            ->all();

        return array_unique(['en', ...$codes]);
    }

    public function keepFile($fileId)
    {
        // The default filesystem content storage (H5PDefaultStorage) doesn't
        // need separate "pending vs. kept" bookkeeping — files copied into a
        // content folder are already permanent. No-op, matching how other
        // filesystem-storage H5P integrations implement this.
    }

    public function getLibraries($libraries = null)
    {
        $query = DB::table('h5p_libraries');

        if ($libraries !== null) {
            // Callers (H5peditor::getLibraries() parsing $_POST['libraries'])
            // build these as {uberName, name, majorVersion, minorVersion} —
            // 'name', not 'machineName' despite the interface docblock's
            // "$libraries List of library names + version" phrasing.
            $query->where(function ($query) use ($libraries) {
                foreach ($libraries as $library) {
                    $query->orWhere(function ($query) use ($library) {
                        $query->where('machine_name', $library->name)
                            ->where('major_version', $library->majorVersion)
                            ->where('minor_version', $library->minorVersion);
                    });
                }
            });
        } else {
            $query->where('runnable', true);
        }

        return $query->get()->map(fn ($row) => (object) [
            'id' => $row->id,
            'name' => $row->machine_name,
            'title' => $row->title,
            'majorVersion' => $row->major_version,
            'minorVersion' => $row->minor_version,
            'patchVersion' => $row->patch_version,
            'restricted' => (bool) $row->restricted,
            'metadataSettings' => $row->metadata_settings ? json_decode($row->metadata_settings) : null,
            'runnable' => $row->runnable,
        ])->all();
    }

    public function alterLibraryFiles(&$files, $libraries)
    {
        // No per-request file/script alteration hooks needed in this app.
    }

    public static function saveFileTemporarily($data, $move_file = false)
    {
        $dir = storage_path('app/h5p-temp/editor/'.Str::random(32));
        mkdir($dir, 0755, true);

        $filename = basename(parse_url($data, PHP_URL_PATH) ?: 'file');
        $destination = $dir.'/'.$filename;

        if ($move_file) {
            rename($data, $destination);
        } else {
            copy($data, $destination);
        }

        return (object) [
            'dir' => $dir,
            'fileName' => $filename,
        ];
    }

    public static function markFileForCleanup($file, $content_id)
    {
        // Temporary editor uploads live under storage/app/h5p-temp/editor and
        // are swept by removeTemporarilySavedFiles(); nothing to mark per-file.
    }

    public static function removeTemporarilySavedFiles($filePath)
    {
        if (is_dir($filePath)) {
            \H5PCore::deleteFileTree($filePath);
        } elseif (is_file($filePath)) {
            unlink($filePath);
        }
    }
}
