<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Отметка прихода и ухода (на общем планшете по PIN). Исправить может пользователь с правом «Смены», с причиной. */
class Attendance extends Model
{
    protected $fillable = ['branch_id', 'user_id', 'shift_id', 'clock_in', 'clock_out', 'corrected_by', 'correction_reason'];

    protected function casts(): array
    {
        return ['clock_in' => 'datetime', 'clock_out' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** Отработано минут; открытая отметка — до текущего момента. */
    public function minutes(): int
    {
        return (int) $this->clock_in->diffInMinutes($this->clock_out ?? now());
    }
}
