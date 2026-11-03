<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScopeAssignment extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected function casts(): array { return ['granted_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function roleAssignment(): BelongsTo { return $this->belongsTo(RoleAssignment::class); }
    public function offering(): BelongsTo { return $this->belongsTo(Offering::class); }
}
