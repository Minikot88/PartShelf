<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProductionConfiguration
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (app()->environment('production')) {
            $safe = ! config('app.debug') && str_starts_with(config('app.url', ''), 'https://') && config('app.key')
                && config('partshelf.file_transport') === 'object' && config('partshelf.image_disk') === 'r2'
                && config('session.secure') && config('session.http_only')
                && in_array(config('session.driver'), ['database', 'redis'], true)
                && in_array(config('cache.default'), ['database', 'redis'], true)
                && config('database.default') === 'pgsql';
            foreach (['key', 'secret', 'bucket', 'endpoint'] as $key) {
                $safe = $safe && filled(config('filesystems.disks.r2.'.$key));
            }
            $safe = $safe && str_starts_with(config('filesystems.disks.r2.endpoint', ''), 'https://');
            if (! $safe) {
                return response()->json(['message' => 'Service configuration is incomplete.'], 503)->header('Cache-Control', 'no-store, private');
            }
        }

        return $next($request);
    }
}
