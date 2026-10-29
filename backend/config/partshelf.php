<?php

return [
    'demo_enabled' => env('PARTSHELF_DEMO_ENABLED', false),
    'deployment_environment' => env('VERCEL_ENV'),
    'file_transport' => env('FILE_TRANSPORT', 'multipart'),
    'image_disk' => env('PRODUCT_IMAGE_DISK', 'public'),
];
