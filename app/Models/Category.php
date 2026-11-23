<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasLocalizedName;

    protected $fillable = ['name_tm', 'name_ru', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort' => 'integer'];
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    public function stopEntry(): MorphOne
    {
        return $this->morphOne(StopListEntry::class, 'stoppable');
    }
}
