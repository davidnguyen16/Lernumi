<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleRequest extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected function casts(): array { return ['requested_at' => 'datetime', 'processed_at' => 'datetime']; }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
