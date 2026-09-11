<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /* 위임장 서명 그림 (2026-09-11 지시).

           표 하나와 이 폴더만 운영 서버로 옮긴다. 그래서 다른 파일과 섞지 않고
           제 디스크로 둔다 — 디스크의 뿌리가 곧 옮길 폴더다.

           폴더를 2775 로 세우는 까닭: 라라벨은 private 폴더를 0700 으로 만든다.
           그러면 폴더를 세운 www-data 말고는 들여다볼 수 없어, 배포 사용자가
           폴더째 묶지도 옮기지도 못한다(ls 조차 Permission denied 로 막힌다).
           웹에서 바로 열리는 자리가 아니라 무리가 없다. */
        'delegation' => [
            'driver' => 'local',
            'root'   => storage_path('app/private/delegation-signs'),
            'throw'  => false,
            'report' => false,
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0644],
                'dir'  => ['public' => 02775, 'private' => 02775],
            ],
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
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
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
