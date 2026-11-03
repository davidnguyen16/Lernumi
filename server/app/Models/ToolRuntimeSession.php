<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolRuntimeSession extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected function casts(): array { return ['capabilities' => 'array', 'read_only' => 'boolean', 'revoked_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime']; }
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tool(): BelongsTo { return $this->belongsTo(Tool::class); }
    public function toolVersion(): BelongsTo { return $this->belongsTo(ToolVersion::class); }
    public function workSession(): BelongsTo { return $this->belongsTo(AssignmentWorkSession::class, 'work_session_id'); }
}
