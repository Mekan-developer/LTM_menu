<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\StockMovementType;
use App\Enums\WriteOffReason;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Склад (ТЗ 2.3 «Остатки»): приход, списание при продаже по составу блюд, ручное списание,
 * инвентаризация. Остаток может уйти в минус — продажу это не блокирует.
 * Всё в текущем филиале (пока он один — «Основной»).
 */
class StockService
{
    public function __construct(private readonly ActionLogger $logger) {}

    /**
     * Приход: остаток растёт, цена прихода становится ценой продукта в справочнике —
     * себестоимость блюд с ним пересчитывается (ТЗ 2.3).
     *
     * @param  list<array{product_id: int, quantity: float|int|string, price: string|int|float}>  $lines
     */
    public function receipt(array $lines, ?string $note, User $user): StockDocument
    {
        $this->assertUniqueProducts($lines);

        return DB::transaction(function () use ($lines, $note, $user) {
            $doc = $this->document(StockMovementType::Receipt, $note, $user);
            $total = 0;

            foreach ($lines as $line) {
                $product = Product::query()->lockForUpdate()->findOrFail((int) $line['product_id']);
                $price = Money::toMinor((string) $line['price']);
                $qty = round((float) $line['quantity'], 3);

                $total += $this->move($product, StockMovementType::Receipt, $qty, $price, $user, documentId: $doc->id)->amount;

                if ($product->price !== $price) {
                    $this->logger->log(ActionLogger::PRODUCT_PRICE_CHANGED, $product, [
                        'name' => $product->name_ru, 'old' => $product->price, 'new' => $price,
                        'unit' => $product->unit->value, 'document_id' => $doc->id,
                    ], $user);
                    $product->forceFill(['price' => $price])->save();
                }
            }

            $doc->forceFill(['total' => $total])->save();
            $this->logger->log(ActionLogger::STOCK_RECEIPT, $doc, ['total' => $total, 'lines' => count($lines), 'note' => $note], $user);

            return $doc;
        });
    }

    /**
     * Ручное списание (порча, бой, персонал) — с причиной; «другое» — с комментарием.
     *
     * @param  list<array{product_id: int, quantity: float|int|string}>  $lines
     */
    public function writeOff(array $lines, WriteOffReason $reason, ?string $note, User $user): StockDocument
    {
        $this->assertUniqueProducts($lines);

        if ($reason === WriteOffReason::Other && blank($note)) {
            throw ValidationException::withMessages(['note' => __('crm.stock.note_required')]);
        }

        return DB::transaction(function () use ($lines, $reason, $note, $user) {
            $doc = $this->document(StockMovementType::WriteOff, $note, $user, $reason);
            $total = 0;

            foreach ($lines as $line) {
                $product = Product::query()->findOrFail((int) $line['product_id']);
                $total += $this->move($product, StockMovementType::WriteOff, -round((float) $line['quantity'], 3), $product->price, $user, documentId: $doc->id)->amount;
            }

            $doc->forceFill(['total' => $total])->save();
            $this->logger->log(ActionLogger::STOCK_WRITE_OFF, $doc, [
                'total' => $total, 'reason' => $reason->value, 'lines' => count($lines), 'note' => $note,
            ], $user);

            return $doc;
        });
    }

    /**
     * Инвентаризация: фактические остатки против расчётных. Расхождение (в количестве и TMT)
     * исправляет остаток. Непосчитанные продукты не трогаем.
     *
     * @param  list<array{product_id: int, actual: float|int|string}>  $counts
     */
    public function inventory(array $counts, ?string $note, User $user): StockDocument
    {
        $this->assertUniqueProducts($counts);

        return DB::transaction(function () use ($counts, $note, $user) {
            $doc = $this->document(StockMovementType::Inventory, $note, $user);
            $shortage = 0;
            $surplus = 0;

            foreach ($counts as $count) {
                $product = Product::query()->findOrFail((int) $count['product_id']);
                $expected = $this->lockBalance($product->id)->quantity;
                $delta = round((float) $count['actual'] - $expected, 3);

                if ($delta == 0) {
                    continue;
                }

                $amount = $this->move($product, StockMovementType::Inventory, $delta, $product->price, $user, documentId: $doc->id)->amount;
                $amount < 0 ? $shortage += $amount : $surplus += $amount;
            }

            $doc->forceFill(['total' => $shortage + $surplus])->save();
            $this->logger->log(ActionLogger::STOCK_INVENTORY, $doc, [
                'shortage' => $shortage, 'surplus' => $surplus, 'counted' => count($counts), 'note' => $note,
            ], $user);

            return $doc;
        });
    }

    /**
     * Списание при продаже (ТЗ 2.3): оплаченный счёт списывает продукты по составу каждого блюда.
     * Блюдо без калькуляции ничего не списывает. Повторный вызов для того же заказа ничего не делает.
     * Вызывается внутри транзакции оплаты.
     */
    public function consumeOrder(Order $order, ?User $user = null): void
    {
        if (StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::Sale->value)->exists()) {
            return;
        }

        $items = $order->items()
            ->whereIn('status', OrderItemStatus::billable())
            ->whereNotNull('dish_id')
            ->with('dish.ingredients.product')
            ->get();

        /** @var array<int, array{product: Product, qty: float}> $need */
        $need = [];
        foreach ($items as $item) {
            $ingredients = $item->dish->ingredients ?? collect();

            foreach ($ingredients as $ingredient) {
                $need[$ingredient->product_id] ??= ['product' => $ingredient->product, 'qty' => 0.0];
                $need[$ingredient->product_id]['qty'] += $ingredient->quantity * $item->quantity;
            }
        }

        ksort($need); // одинаковый порядок блокировок — без взаимных ожиданий
        foreach ($need as ['product' => $product, 'qty' => $qty]) {
            $this->move($product, StockMovementType::Sale, -round($qty, 3), $product->price, $user, orderId: $order->id);
        }
    }

    /** Отмена оплаты: списанное по заказу возвращается на склад по тем же ценам. Испорченное — ручным списанием. */
    public function restoreOrder(Order $order, ?User $user = null): void
    {
        $sold = StockMovement::query()->with('product')
            ->where('order_id', $order->id)->where('type', StockMovementType::Sale->value)
            ->orderBy('product_id')->get();

        $returned = StockMovement::query()
            ->where('order_id', $order->id)->where('type', StockMovementType::SaleReturn->value)->exists();

        if ($returned) {
            return;
        }

        foreach ($sold as $m) {
            $this->move($m->product, StockMovementType::SaleReturn, -$m->quantity, $m->price, $user, orderId: $order->id);
        }
    }

    public function setMinimum(Product $product, float $min): void
    {
        $balance = $this->lockBalance($product->id);
        $balance->forceFill(['min_quantity' => round(max(0, $min), 3)])->save();
    }

    /** Остаток с блокировкой строки; строки ещё нет — создаём с нулём. */
    private function lockBalance(int $productId): StockBalance
    {
        $branchId = Branch::currentId();

        return StockBalance::query()->where('branch_id', $branchId)->where('product_id', $productId)->lockForUpdate()->first()
            ?? StockBalance::query()->create(['branch_id' => $branchId, 'product_id' => $productId, 'quantity' => 0, 'min_quantity' => 0]);
    }

    private function move(
        Product $product,
        StockMovementType $type,
        float $qty,
        int $price,
        ?User $user,
        ?int $documentId = null,
        ?int $orderId = null,
    ): StockMovement {
        $balance = $this->lockBalance($product->id);
        $after = round($balance->quantity + $qty, 3);
        $balance->forceFill(['quantity' => $after])->save();

        return StockMovement::query()->create([
            'branch_id' => $balance->branch_id,
            'product_id' => $product->id,
            'type' => $type->value,
            'quantity' => $qty,
            'price' => $price,
            'amount' => (int) round($qty * $price),
            'balance_after' => $after,
            'document_id' => $documentId,
            'order_id' => $orderId,
            'user_id' => $user?->id,
        ]);
    }

    private function document(StockMovementType $type, ?string $note, User $user, ?WriteOffReason $reason = null): StockDocument
    {
        return StockDocument::query()->create([
            'branch_id' => Branch::currentId(),
            'type' => $type->value,
            'reason' => $reason?->value,
            'note' => filled($note) ? $note : null,
            'user_id' => $user->id,
        ]);
    }

    /** @param list<array<string, mixed>> $lines */
    private function assertUniqueProducts(array $lines): void
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('crm.stock.no_lines')]);
        }

        $ids = array_map(fn ($l) => (int) $l['product_id'], $lines);

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['lines' => __('crm.costing.duplicate')]);
        }
    }
}
