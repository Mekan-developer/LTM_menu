<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Фильтры экрана «Все заказы». Неверный фильтр не ломает экран — просто не применяется. */
class OrderListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'channel' => ['nullable', Rule::enum(OrderChannel::class)],
            // active = открыт или ждёт счёт; остальные — как в OrderStatus
            'status' => ['nullable', Rule::in(['active', ...array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases())])],
            'waiter' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:60'],
            'show' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Фильтры для репозитория: без периода и номера страницы.
     *
     * @return array{channel: ?string, status: ?string, waiter: ?int, q: ?string}
     */
    public function filters(): array
    {
        return [
            'channel' => $this->validated('channel'),
            'status' => $this->validated('status'),
            'waiter' => $this->filled('waiter') ? (int) $this->validated('waiter') : null,
            'q' => filled($this->validated('q')) ? trim((string) $this->validated('q')) : null,
        ];
    }
}
