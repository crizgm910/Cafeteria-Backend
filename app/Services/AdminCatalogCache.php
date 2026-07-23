<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class AdminCatalogCache
{
    private const CATALOG_KEY = 'admin-catalog:v2';
    private const INVENTORY_KEY = 'admin-inventory:v1';

    public function catalog(Closure $builder): mixed
    {
        return $this->remember(self::CATALOG_KEY, $builder);
    }

    public function inventory(Closure $builder): mixed
    {
        return $this->remember(self::INVENTORY_KEY, $builder);
    }

    public function forget(): void
    {
        Cache::store('file')->forget(self::CATALOG_KEY);
        Cache::store('file')->forget(self::INVENTORY_KEY);
    }

    private function remember(string $key, Closure $builder): mixed
    {
        if (app()->environment('testing')) {
            return $builder();
        }

        return Cache::store('file')->remember($key, now()->addMinutes(5), $builder);
    }
}
