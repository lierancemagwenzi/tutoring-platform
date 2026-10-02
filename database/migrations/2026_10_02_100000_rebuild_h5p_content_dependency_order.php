<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Content dependencies were saved in list order rather than H5P's computed
 * load order, so a library could load before the libraries it extends (see
 * LaravelH5PFramework::saveLibraryUsage()). Clearing the cached filtered
 * parameters makes H5P re-validate each piece of content the next time it's
 * shown, which rebuilds its dependency rows — now in the correct order. This
 * is the same mechanism H5P itself uses after a library upgrade
 * (clearFilteredParameters()); nothing about the content itself changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('h5p_content')->update(['filtered_parameters' => null]);
    }

    public function down(): void
    {
        // Nothing to undo — the cache is rebuilt on demand either way.
    }
};
