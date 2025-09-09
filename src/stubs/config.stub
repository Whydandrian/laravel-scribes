<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Upload Paths
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'private' => 'uploads/private',
        'public'  => 'uploads/public',
        'temp'    => 'uploads/temp',
        'sharing' => 'uploads/sharing',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Naming Strategies
    |--------------------------------------------------------------------------
    | Bisa ditambah sesuai kebutuhan. Setiap strategy adalah Closure yang
    | menerima UploadedFile $file dan array $context.
    */
    'file_naming' => [

        'file_naming' => [
            'default' => [\App\Support\FileNamingStrategies::class, 'default'],
            'avatar'  => [\App\Support\FileNamingStrategies::class, 'avatar'],
            'file'    => [\App\Support\FileNamingStrategies::class, 'file'],
            'date'    => [\App\Support\FileNamingStrategies::class, 'date'],
            'slug'    => [\App\Support\FileNamingStrategies::class, 'slug'],
        ],

    ],

];
