<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use App\Models\AddOn;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\ServiceArea;
use App\Models\DiningTable;
use App\Models\ReservationSchedule;
use App\Models\ReservationBlock;
use App\Observers\AuditableObserver;
use App\Services\PublicMenuCache;
use App\Services\AdminCatalogCache;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            AddOn::class,
            CashMovement::class,
            CashRegisterSession::class,
            Category::class,
            Ingredient::class,
            InventoryTransaction::class,
            Payment::class,
            Product::class,
            Reservation::class,
            Ticket::class,
            User::class,
            ServiceArea::class,
            DiningTable::class,
            ReservationSchedule::class,
            ReservationBlock::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }

        foreach ([AddOn::class, Category::class, Ingredient::class, Product::class] as $model) {
            $model::saved(fn () => app(PublicMenuCache::class)->forget());
            $model::deleted(fn () => app(PublicMenuCache::class)->forget());
        }

        foreach ([AddOn::class, Category::class, Ingredient::class, Product::class] as $model) {
            $model::saved(fn () => app(AdminCatalogCache::class)->forget());
            $model::deleted(fn () => app(AdminCatalogCache::class)->forget());
        }

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('checkout', fn (Request $request) =>
            Limit::perMinute(10)->by($request->ip())
        );

        RateLimiter::for('public-write', fn (Request $request) =>
            Limit::perMinute(10)->by($request->ip())
        );

        RateLimiter::for('public-read', fn (Request $request) =>
            Limit::perMinute(120)->by($request->ip())
        );
    }
}
