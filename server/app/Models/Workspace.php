<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workspace extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'last_opened_at'];
    protected function casts(): array { return ['last_opened_at' => 'immutable_datetime']; }
    public function personal(): HasOne { return $this->hasOne(PersonalWorkspace::class, 'workspace_id'); }
    public function assignment(): HasOne { return $this->hasOne(AssignmentWorkspace::class, 'workspace_id'); }
    public function documents(): HasMany { return $this->hasMany(Document::class); }
}
