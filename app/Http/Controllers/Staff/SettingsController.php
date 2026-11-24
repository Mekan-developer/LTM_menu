<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\SettingsRequest;
use App\Services\BackupService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly BackupService $backups,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => $this->settings->all(),
            'backup' => $this->backups->status(),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->validated());

        return back()->with('success', __('crm.saved'));
    }
}
