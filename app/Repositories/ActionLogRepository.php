<?php

namespace App\Repositories;

use App\Models\ActionLog;
use Illuminate\Contracts\Pagination\CursorPaginator;

class ActionLogRepository
{
    public function create(array $data): ActionLog
    {
        return ActionLog::create($data);
    }

    public function paginate(?string $action, int $perPage = 50): CursorPaginator
    {
        return ActionLog::query()
            ->with('user:id,name')
            ->when($action, fn ($q) => $q->where('action', $action))
            ->orderByDesc('id')
            ->cursorPaginate($perPage)
            ->withQueryString();
    }
}
