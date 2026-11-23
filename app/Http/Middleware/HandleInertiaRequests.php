<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private readonly SettingsService $settings) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing('role.permissions');

        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role?->localizedName(),
                    'is_owner' => $user->isOwner(),
                    'permissions' => $user->permissionKeys(),
                ] : null,
                'shared_device' => (bool) $request->session()->get('shared_device', false),
            ],
            'app' => fn () => [
                'restaurant_name' => $this->settings->get('restaurant_name'),
                'shared_idle_seconds' => (int) $this->settings->get('shared_idle_seconds'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
