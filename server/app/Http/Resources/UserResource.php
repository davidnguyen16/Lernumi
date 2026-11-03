<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'firstName' => $this->first_name, 'lastName' => $this->last_name, 'email' => $this->email, 'emailVerified' => $this->hasVerifiedEmail(), 'isAdmin' => $this->is_admin, 'isSystemAdmin' => $this->is_admin, 'isActive' => $this->is_active, 'avatarUrl' => $this->avatar_key ? url('/api/v1/users/'.$this->id.'/avatar') : null, 'createdAt' => $this->created_at?->toISOString()];
    }
}
