<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Order;
use App\Models\User;
use App\Repositories\GuestRepository;
use App\Support\Phone;
use App\Support\XlsxWriter;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * База гостей (ТЗ 2.1). Гость попадает в базу сам (бронь, заказ на странице)
 * или его заводит сотрудник; кассир может привязать счёт к гостю по телефону.
 */
class GuestService
{
    public function __construct(
        private readonly GuestRepository $guests,
        private readonly ActionLogger $logger,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): Guest
    {
        return Guest::create($data + ['source' => 'staff', 'created_by' => $user->id]);
    }

    /** @param array<string, mixed> $data */
    public function update(Guest $guest, array $data): Guest
    {
        $guest->update($data);

        return $guest;
    }

    /** Заказы и отзывы остаются в отчётах, только без привязки к гостю. */
    public function delete(Guest $guest): void
    {
        $guest->delete();
    }

    /**
     * Найти гостя по телефону или завести нового. Имя нового — какое назвали, иначе «Гость».
     *
     * @param  string  $source  staff | cashier | booking | order
     */
    public function findOrCreateByPhone(string $phone, ?string $name, string $source, ?User $user = null): Guest
    {
        $normalized = Phone::normalize($phone);

        if ($normalized === null) {
            throw ValidationException::withMessages(['guest_phone' => __('crm.guests.phone_invalid')]);
        }

        $guest = $this->guests->findByPhone($normalized);

        if ($guest !== null) {
            // Гость назвал имя, а в базе заглушка — уточняем.
            if (filled($name) && $guest->name === __('crm.guests.unnamed')) {
                $guest->update(['name' => trim((string) $name)]);
            }

            return $guest;
        }

        return Guest::create([
            'name' => filled($name) ? trim((string) $name) : __('crm.guests.unnamed'),
            'phone' => $normalized,
            'source' => $source,
            'created_by' => $user?->id,
        ]);
    }

    /** Кассир называет телефон гостя — счёт попадает в историю гостя. */
    public function attachToOrder(Order $order, string $phone, ?string $name, User $cashier): Guest
    {
        $guest = $this->findOrCreateByPhone($phone, $name, 'cashier', $cashier);
        $order->forceFill(['guest_id' => $guest->id])->save();

        return $guest;
    }

    /** Выгрузка базы в Excel — право «Экспорт базы гостей». Пишется в журнал: это персональные данные. */
    public function export(User $user): XlsxWriter
    {
        $rows = $this->guests->forExport();
        $xlsx = (new XlsxWriter(__('crm.guests.title')))
            ->setWidths([28, 16, 12, 32, 32, 10, 14, 12, 12])
            ->addHeader([
                __('crm.guests.name'), __('crm.guests.phone'), __('crm.guests.birthday'),
                __('crm.guests.address'), __('crm.guests.comment'), __('crm.guests.visits'),
                __('crm.guests.spent'), __('crm.guests.last_visit'), __('crm.guests.since'),
            ]);

        foreach ($rows as $g) {
            $last = $g->getAttribute('last_visit');
            $xlsx->addRow([
                $g->name,
                Phone::format($g->phone),
                $g->birthday?->format('d.m.Y'),
                $g->address,
                $g->comment,
                (int) $g->getAttribute('visits'),
                ['v' => ((int) $g->getAttribute('spent')) / 100, 's' => 'money'],
                $last ? Carbon::parse($last)->format('d.m.Y') : null,
                $g->created_at?->format('d.m.Y'),
            ]);
        }

        $this->logger->log(ActionLogger::GUESTS_EXPORTED, null, ['count' => $rows->count()], $user);

        return $xlsx;
    }
}
