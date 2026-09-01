<?php

return [

    /*
    |--------------------------------------------------------------------------
    | H5P mirror disk
    |--------------------------------------------------------------------------
    |
    | When set (e.g. "s3"), every file H5P writes to local disk — installed
    | content-type libraries, tutor-created content, exports — is also
    | mirrored to this Laravel filesystem disk, and H5pAssetController falls
    | back to it when a file is missing locally. Needed on Laravel Cloud,
    | where local disk is ephemeral per-instance and wiped on every deploy.
    | Left blank, behavior is unchanged: local disk only.
    |
    */

    'storage_disk' => env('H5P_STORAGE_DISK'),

];
