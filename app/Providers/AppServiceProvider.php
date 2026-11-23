<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Настройки читаются один раз за запрос.
        $this->app->scoped(SettingsService::class);
    }

    public function boot(): void
    {
        // migrate:fresh и подобные команды запрещены в продакшене.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        JsonResource::withoutWrapping();
        Vite::prefetch(concurrency: 3);

        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'category' => Category::class,
            'dish' => Dish::class,
            'table' => DiningTable::class,
            'order' => Order::class,
            'order_item' => OrderItem::class,
            'payment' => Payment::class,
        ]);

        // Любое право из App\Enums\Permission можно проверять через Gate / @can.
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ? true : null);

        // Гость с QR: не больше 10 заказов в минуту с одного адреса.
        RateLimiter::for('guest-orders', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
