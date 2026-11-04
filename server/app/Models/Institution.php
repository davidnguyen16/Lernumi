<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Institution extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['id', 'name', 'website'];

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function terms(): HasMany { return $this->hasMany(StudyTerm::class); }
    public function studyTerms(): HasMany { return $this->terms(); }
    public function offerings(): HasMany { return $this->hasMany(Offering::class); }
    public function roles(): HasMany { return $this->hasMany(Role::class); }
    public function roleRequests(): HasMany { return $this->hasMany(RoleRequest::class); }
    public function scopeRequests(): HasManyThrough { return $this->hasManyThrough(ScopeAssignmentRequest::class, Offering::class); }
}
