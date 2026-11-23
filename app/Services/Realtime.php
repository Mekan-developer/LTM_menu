<?php

namespace App\Services;

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
}
