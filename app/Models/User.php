<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\SystemRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'login', 'password', 'pin', 'role_id', 'is_active',
        'pin_failed_attempts', 'pin_locked_until',
    ];

    protected $hidden = ['password', 'pin', 'remember_token'];

    /** Права проверяются на каждом запросе — грузим роль сразу, без N+1. */
    protected $with = ['role.permissions'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'pin' => 'hashed',
            'is_active' => 'boolean',
            'pin_locked_until' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isOwner(): bool
    {
        return $this->role?->slug === SystemRole::Owner->value;
    }

    /** У владельца все права есть всегда и не снимаются (ТЗ 1.3). */
    public function hasPermission(Permission|string $permission): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $key = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($key, $this->permissionKeys(), true);
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        if ($this->isOwner()) {
            return Permission::values();
        }

        return $this->role?->permissionKeys() ?? [];
    }
}
