<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'login' => $this->login,
            'role_id' => $this->role_id,
            'role' => $this->role?->localizedName(),
            'is_owner' => $this->isOwner(),
            'is_active' => $this->is_active,
            'has_pin' => $this->pin !== null,
            'pin_locked' => (bool) $this->pin_locked_until?->isFuture(),
        ];
    }
}
