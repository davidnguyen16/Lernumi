<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAttemptResult extends Model
{
    protected $primaryKey = 'attempt_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['attempt_id', 'mark_id', 'finalised_by_id', 'finalised_at', 'note'];
    protected function casts(): array { return ['finalised_at' => 'immutable_datetime']; }
    public function attempt(): BelongsTo { return $this->belongsTo(AssessmentAttempt::class, 'attempt_id'); }
    public function mark(): BelongsTo { return $this->belongsTo(AssessmentAttemptMark::class); }
    public function finaliser(): BelongsTo { return $this->belongsTo(User::class, 'finalised_by_id'); }
}
