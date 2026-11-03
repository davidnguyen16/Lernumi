<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentPlan extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'assessment_id', 'start_date', 'end_date'];
    protected function casts(): array { return ['start_date' => 'immutable_date', 'end_date' => 'immutable_date']; }
    public function assessment(): BelongsTo { return $this->belongsTo(Assessment::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
