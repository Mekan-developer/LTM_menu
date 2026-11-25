<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Плановая смена сотрудника: начало и конец (ночная — до следующего дня). */
class Shift extends Model
{
    protected $fillable = ['branch_id', 'user_id', 'starts_at', 'ends_at', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<Attendance, $this> */
    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class)->latestOfMany('clock_in');
    }

    public function minutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }
}
