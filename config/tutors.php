<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration Speed Test
    |--------------------------------------------------------------------------
    |
    | The minimum connection a tutor applicant must measure during
    | registration before they can continue their application. Upload
    | matters as much as download — the tutor's camera and screen share go
    | up — and a 1:1 HD Google Meet call sends ~2.6 Mbps.
    |
    */

    'speed_test' => [
        'min_download_mbps' => (float) env('TUTOR_SPEED_TEST_MIN_DOWNLOAD_MBPS', 4),
        'min_upload_mbps' => (float) env('TUTOR_SPEED_TEST_MIN_UPLOAD_MBPS', 2.5),
        'max_latency_ms' => (float) env('TUTOR_SPEED_TEST_MAX_LATENCY_MS', 200),
        'max_jitter_ms' => (float) env('TUTOR_SPEED_TEST_MAX_JITTER_MS', 50),
    ],

];
