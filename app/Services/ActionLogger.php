<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\ActionLogRepository;
use Illuminate\Database\Eloquent\Model;

/** Журнал действий (ТЗ 2.11): кто и когда удалил блюдо, дал скидку, отменил оплату, изменил цену… */
class ActionLogger
{
    public const ITEM_REMOVED = 'item_removed';

    public const QR_REJECTED = 'qr_rejected';

    public const DISCOUNT_APPLIED = 'discount_applied';

    public const PAYMENT_CANCELLED = 'payment_cancelled';

    public const PRICE_CHANGED = 'price_changed';

    public const TABLE_TRANSFERRED = 'table_transferred';

    public const STOP_ADDED = 'stop_added';

    public const STOP_REMOVED = 'stop_removed';

    public const ROLE_PERMISSIONS_CHANGED = 'role_permissions_changed';

    public function __construct(private readonly ActionLogRepository $logs) {}

    /** @param array<string, mixed> $payload */
    public function log(string $action, ?Model $subject = null, array $payload = [], ?User $user = null): void
    {
        $this->logs->create([
            'user_id' => ($user ?? auth()->user())?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'payload' => $payload ?: null,
        ]);
    }
}
