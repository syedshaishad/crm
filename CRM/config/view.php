<?php

return [

    'paths' => [
        resource_path('views'),
    ],

    // Do not use realpath() here: distributable zips intentionally ship without
    // storage/framework/views, and realpath() would resolve to false during
    // bootstrap (before the installer middleware can create the directory).
    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),

];
