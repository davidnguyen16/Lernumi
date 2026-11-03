<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tool extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'description', 'code_base_location', 'active_version_id'];

    public function activeVersion(): BelongsTo { return $this->belongsTo(ToolVersion::class, 'active_version_id'); }
    public function versions(): HasMany { return $this->hasMany(ToolVersion::class); }
}
