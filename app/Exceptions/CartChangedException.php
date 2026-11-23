<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Меню у гостя было из кэша: цена изменилась или блюдо в стопе.
 * Гость видит сообщение до отправки заказа (ТЗ 2.3).
 */
class CartChangedException extends Exception
{
    /** @param list<array{dish_id:int, type:string, old?:int, new?:int}> $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('cart_changed');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => __('crm.guest.cart_changed'),
            'problems' => $this->problems,
        ], 409);
    }
}
