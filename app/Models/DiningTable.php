<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Стол. В MVP без схемы зала: номер, зал (текстом), места и QR-код. */
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    protected $fillable = ['number', 'hall', 'seats', 'qr_token', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'seats' => 'integer', 'sort' => 'integer'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class)
            ->whereIn('status', OrderStatus::activeValues())
            ->latestOfMany();
    }
}
