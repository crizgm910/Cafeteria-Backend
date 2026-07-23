<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class PublicMenuCache
{
    private const KEY = 'public-menu:v1';

    public function remember(Closure $builder): mixed
    {
        if (app()->environment('testing')) {
            return $builder();
        }

        return Cache::store('file')->remember(self::KEY, now()->addMinutes(5), $builder);
    }

    public function forget(): void
    {
        Cache::store('file')->forget(self::KEY);
    }
}
