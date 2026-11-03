<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleAssignment extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected function casts(): array { return ['granted_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function scopeAssignments(): HasMany { return $this->hasMany(ScopeAssignment::class); }
}
