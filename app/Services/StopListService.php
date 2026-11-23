<?php

namespace App\Services;

use App\Enums\StopMode;
use App\Models\Dish;
use App\Models\StopListEntry;
use App\Models\User;
use App\Repositories\StopListRepository;
use Carbon\CarbonImmutable;

/** Стоп-меню (ТЗ 2.3): позиция в стопе исчезает у гостя и не добавляется в заказ. */
class StopListService
{
    /** @var array{dishes: array<int, true>, categories: array<int, true>}|null */
    private ?array $index = null;

    public function __construct(
        private readonly StopListRepository $stopList,
        private readonly ActionLogger $logger,
    ) {}

    public function stop(string $type, int $id, StopMode $mode, ?string $until, User $user): StopListEntry
    {
        $stoppable = $this->stopList->findStoppable($type, $id);

        $untilAt = match ($mode) {
            StopMode::EndOfDay => now()->endOfDay(),
            StopMode::UntilTime => CarbonImmutable::parse($until, config('app.timezone')),
            StopMode::Manual => null,
        };

        $entry = $this->stopList->upsert($stoppable, [
            'mode' => $mode->value,
            'until' => $untilAt,
            'created_by' => $user->id,
        ]);

        $this->logger->log(ActionLogger::STOP_ADDED, $stoppable, ['mode' => $mode->value, 'until' => $untilAt?->toIso8601String()], $user);
        $this->index = null;

        return $entry;
    }

    public function remove(StopListEntry $entry, User $user): void
    {
        $this->logger->log(ActionLogger::STOP_REMOVED, null, [
            'type' => $entry->stoppable_type, 'id' => $entry->stoppable_id,
        ], $user);

        $entry->delete();
        $this->index = null;
    }

    public function index(): array
    {
        return $this->index ??= $this->stopList->activeIndex();
    }

    public function isStopped(Dish $dish): bool
    {
        $index = $this->index();

        return isset($index['dishes'][$dish->id]) || isset($index['categories'][$dish->category_id]);
    }
}
