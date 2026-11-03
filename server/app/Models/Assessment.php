<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasUuids;

    protected $fillable = ['offering_id', 'name', 'description', 'weight', 'hurdle', 'is_group', 'publish_at', 'due_at', 'expected_due'];
    protected function casts(): array
    {
        return ['weight' => 'float', 'hurdle' => 'boolean', 'is_group' => 'boolean', 'publish_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime'];
    }
    public function offering(): BelongsTo { return $this->belongsTo(Offering::class); }
    public function allowedTools(): BelongsToMany { return $this->belongsToMany(Tool::class, 'assessment_allow_tools'); }
    public function tools(): BelongsToMany { return $this->allowedTools(); }
    public function attempts(): HasMany { return $this->hasMany(AssessmentAttempt::class); }
    public function groups(): HasMany { return $this->hasMany(AssessmentGroup::class); }
    public function plans(): HasMany { return $this->hasMany(AssessmentPlan::class); }
}
