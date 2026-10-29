<?php

namespace App\Support;

final class DemoEnvironment
{
    public static function cloudPreview(): bool
    {
        return app()->environment('production')
            && config('partshelf.deployment_environment') === 'preview'
            && config('database.default') === 'pgsql'
            && blank(config('database.connections.pgsql.url'))
            && config('database.connections.pgsql.search_path') === 'partshelf_demo'
            && config('database.connections.pgsql.username') === 'partshelf_demo_runtime';
    }

    public static function enabled(): bool
    {
        return (bool) config('partshelf.demo_enabled')
            && (app()->environment(['local', 'testing']) || self::cloudPreview());
    }
}
