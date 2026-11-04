<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = ['name', 'first_name', 'last_name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected $attributes = ['is_admin' => false, 'is_active' => true];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_admin' => 'boolean', 'is_active' => 'boolean', 'email_verified_at' => 'datetime'];
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function attempts(): HasMany { return $this->hasMany(AssessmentAttempt::class); }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
