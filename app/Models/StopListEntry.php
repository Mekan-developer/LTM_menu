<?php

namespace App\Models;

use App\Enums\StopMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StopListEntry extends Model
{
    protected $fillable = ['stoppable_type', 'stoppable_id', 'mode', 'until', 'created_by'];

    protected function casts(): array
    {
        return ['mode' => StopMode::class, 'until' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function stoppable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Действующие записи: ручной стоп или срок ещё не истёк.
     *
     * @param  Builder<StopListEntry>  $query
     * @return Builder<StopListEntry>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('mode', StopMode::Manual->value)
                ->orWhere('until', '>', now());
        });
    }
}
