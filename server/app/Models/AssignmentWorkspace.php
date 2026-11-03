<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentWorkspace extends Model
{
    protected $primaryKey = 'workspace_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class, 'workspace_id'); }
    public function attempt(): BelongsTo { return $this->belongsTo(AssessmentAttempt::class); }
    public function workSessions(): HasMany { return $this->hasMany(AssignmentWorkSession::class, 'assignment_workspace_id', 'workspace_id'); }
}
