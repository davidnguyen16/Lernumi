<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBatch extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d H:i:s.v';
    protected $guarded = ['id'];
    protected function casts(): array { return ['events' => 'object', 'client_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime']; }
    public function workSession(): BelongsTo { return $this->belongsTo(AssignmentWorkSession::class, 'assignment_work_session_id'); }
}
