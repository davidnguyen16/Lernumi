<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $hidden = ['content_key'];
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function parentFolder(): BelongsTo { return $this->belongsTo(self::class, 'parent_folder_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_folder_id'); }
}
