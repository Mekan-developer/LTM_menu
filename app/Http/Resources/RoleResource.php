<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Role */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name_tm' => $this->name_tm,
            'name_ru' => $this->name_ru,
            'is_system' => $this->is_system,
            'is_owner' => $this->isOwner(),
            'users_count' => $this->users_count ?? null,
            'permissions' => $this->isOwner() ? Permission::values() : $this->permissionKeys(),
        ];
    }
}
