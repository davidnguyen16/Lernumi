<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentGroup extends Model
{
    use HasUuids;
    protected $fillable = ['assessment_id', 'name', 'created_by_id'];
    public function assessment(): BelongsTo { return $this->belongsTo(Assessment::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
    public function members(): HasMany { return $this->hasMany(AssessmentGroupMember::class, 'group_id'); }
    public function attempts(): HasMany { return $this->hasMany(AssessmentAttempt::class, 'group_id'); }
}
