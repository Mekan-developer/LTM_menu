<?php

namespace App\Services;

use App\Events\HallChanged;
use App\Models\Order;
use Throwable;

/**
 * Отправка событий в Reverb. Если сокет-сервер недоступен, работа персонала не ломается:
 * ошибка пишется в лог, а экраны подтянут данные плановым опросом.
 */
class Realtime
{
    public function send(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Статус стола на схеме зала изменился. Зал берём из стола заказа;
     * заказ без стола (доставка — следующий этап) схему не трогает.
     */
    public function hallOf(?Order $order): void
    {
        $hallId = $order?->table?->hall_id;

        if ($hallId !== null) {
            $this->send(new HallChanged($hallId));
        }
    }
}
