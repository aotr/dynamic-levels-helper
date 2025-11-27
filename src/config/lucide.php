<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Icon Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk to use for storing cached Lucide icons.
    |
    */
    'icon_storage_disk' => env('LUCIDE_STORAGE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Icon Storage Path
    |--------------------------------------------------------------------------
    |
    | The relative path within the storage disk where icons will be cached.
    |
    */
    'icon_storage_path' => env('LUCIDE_STORAGE_PATH', 'lucide/icons'),

    /*
    |--------------------------------------------------------------------------
    | Remote Source
    |--------------------------------------------------------------------------
    |
    | The remote URL template for fetching Lucide icons.
    | Use {icon} as placeholder for the icon name.
    |
    */
    'remote_source' => env('LUCIDE_REMOTE_SOURCE', 'https://unpkg.com/lucide-static@latest/icons/{icon}.svg'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long to cache the icon list (in seconds). Default: 24 hours.
    |
    */
    'cache_ttl' => env('LUCIDE_CACHE_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Default Icon Attributes
    |--------------------------------------------------------------------------
    |
    | Default attributes to apply to all icons unless overridden.
    |
    */
    'default_attributes' => [
        'stroke' => 'currentColor',
        'stroke-width' => '2',
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
        'fill' => 'none',
    ],
];
