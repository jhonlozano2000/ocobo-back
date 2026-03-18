<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        // ── Discos privados ─────────────────────────────────────────────────
        // Ninguno de estos tiene 'url' ni symlink público.
        // El acceso se hace SIEMPRE a través de endpoints autenticados.

        'temp_files' => [
            'driver' => 'local',
            'root' => storage_path('app/temp_files'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'avatars' => [
            'driver' => 'local',
            'root' => storage_path('app/avatars'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'firmas' => [
            'driver' => 'local',
            'root' => storage_path('app/firmas'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'radicados_recibidos' => [
            'driver' => 'local',
            'root' => storage_path('app/radicados_recibidos'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'radicados_enviados' => [
            'driver' => 'local',
            'root' => storage_path('app/radicados_enviados'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'radicados_internos' => [
            'driver' => 'local',
            'root' => storage_path('app/radicados_internos'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'otros_archivos' => [
            'driver' => 'local',
            'root' => storage_path('app/otros_archivos'),
            'visibility' => 'private',
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    | SOLO el disco 'public' tiene symlink.
    | Los discos privados NO deben tener symlink en public/ — eso los expone.
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
