<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ScopeAssignmentCode extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $hidden = ['code_hash'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime']; }
}
