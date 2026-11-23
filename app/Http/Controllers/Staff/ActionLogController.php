<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActionLog;
use App\Repositories\ActionLogRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Журнал действий (ТЗ 2.11) — право «Журнал действий». */
class ActionLogController extends Controller
{
    public function __construct(private readonly ActionLogRepository $logs) {}

    public function index(Request $request): Response
    {
        $page = $this->logs->paginate($request->string('action')->value() ?: null);

        return Inertia::render('Logs/Index', [
            'logs' => collect($page->items())->map(fn (ActionLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user?->name,
                'subject' => $log->subject_type ? $log->subject_type.' #'.$log->subject_id : null,
                'payload' => $log->payload,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
            'next' => $page->nextPageUrl(),
            'prev' => $page->previousPageUrl(),
            'filter' => $request->string('action')->value(),
        ]);
    }
}
