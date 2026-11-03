<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentWorkSession extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $dateFormat = 'Y-m-d H:i:s.v';
    protected function casts(): array { return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime']; }
    public function workspace(): BelongsTo { return $this->belongsTo(AssignmentWorkspace::class, 'assignment_workspace_id', 'workspace_id'); }
    public function tool(): BelongsTo { return $this->belongsTo(Tool::class); }
    public function toolVersion(): BelongsTo { return $this->belongsTo(ToolVersion::class); }
    public function eventBatches(): HasMany { return $this->hasMany(EventBatch::class, 'assignment_work_session_id'); }
}
