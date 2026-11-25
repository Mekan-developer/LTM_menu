<?php

namespace App\Models;

use App\Enums\BonusType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Начисление или списание бонусов гостя: сумма со знаком (тенге) и баланс после. */
class BonusTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['guest_id', 'order_id', 'payment_id', 'type', 'amount', 'balance_after', 'user_id'];

    protected function casts(): array
    {
        return [
            'type' => BonusType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
