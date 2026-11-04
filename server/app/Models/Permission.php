<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
    public function roles(): BelongsToMany { return $this->belongsToMany(Role::class, 'role_permissions', 'permission_code', 'role_id', 'code', 'id'); }
}
