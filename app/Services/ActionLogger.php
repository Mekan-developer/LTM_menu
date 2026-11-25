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

    public const SALE_CHANGED = 'sale_changed';

    public const PRODUCT_PRICE_CHANGED = 'product_price_changed';

    public const RECIPE_CHANGED = 'recipe_changed';

    public const STOCK_RECEIPT = 'stock_receipt';

    public const STOCK_WRITE_OFF = 'stock_write_off';

    public const STOCK_INVENTORY = 'stock_inventory';

    public const BONUS_SPENT = 'bonus_spent';

    public const ATTENDANCE_CORRECTED = 'attendance_corrected';

    public const TABLE_TRANSFERRED = 'table_transferred';

    public const TABLE_MOVED = 'table_moved';

    public const HALL_LAYOUT_SAVED = 'hall_layout_saved';

    public const GUESTS_EXPORTED = 'guests_exported';

    public const BOOKING_CANCELLED = 'booking_cancelled';

    public const ORDER_CANCELLED = 'order_cancelled';

    public const DELIVERY_FEE_CHANGED = 'delivery_fee_changed';

    public const PAYMENT_METHOD_CHANGED = 'payment_method_changed';

    public const COURIER_CASH_RECEIVED = 'courier_cash_received';

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
