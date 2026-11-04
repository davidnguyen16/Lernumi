<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    public function institution(): BelongsTo { return $this->belongsTo(Institution::class); }
    public function permissions(): BelongsToMany { return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_code', 'id', 'code'); }
    public function includedRoles(): BelongsToMany { return $this->belongsToMany(self::class, 'role_inclusions', 'including_role_id', 'included_role_id'); }
    public function requiredRoles(): BelongsToMany { return $this->belongsToMany(self::class, 'role_request_visibilities', 'requestable_role_id', 'required_role_id'); }
    public function assignments(): HasMany { return $this->hasMany(RoleAssignment::class); }
    public function requests(): HasMany { return $this->hasMany(RoleRequest::class); }
}
