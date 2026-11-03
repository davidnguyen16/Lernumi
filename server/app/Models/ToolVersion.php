<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolVersion extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;
    protected $guarded = ['id'];
    protected $hidden = ['archive_key', 'storage_prefix'];

    protected function casts(): array { return ['manifest' => 'array']; }
    public function tool(): BelongsTo { return $this->belongsTo(Tool::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
}
