<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAttemptMark extends Model
{
    use HasUuids;
    protected $fillable = ['attempt_id', 'marked_by_id', 'score', 'out_of', 'feedback', 'status', 'supersedes_mark_id', 'submitted_at'];
    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'out_of' => 'decimal:2', 'submitted_at' => 'immutable_datetime'];
    }
    public function attempt(): BelongsTo { return $this->belongsTo(AssessmentAttempt::class, 'attempt_id'); }
    public function marker(): BelongsTo { return $this->belongsTo(User::class, 'marked_by_id'); }
    public function previous(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_mark_id'); }
}
