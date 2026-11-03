<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalWorkspace extends Model
{
    protected $primaryKey = 'workspace_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class, 'workspace_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
