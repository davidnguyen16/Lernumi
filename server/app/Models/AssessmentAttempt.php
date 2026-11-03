<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssessmentAttempt extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'assessment_id', 'group_id', 'start_at', 'submit_at'];

    protected function casts(): array
    {
        return ['start_at' => 'immutable_datetime', 'submit_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assessment(): BelongsTo { return $this->belongsTo(Assessment::class); }
    public function group(): BelongsTo { return $this->belongsTo(AssessmentGroup::class); }
    public function workspace(): HasOne { return $this->hasOne(AssignmentWorkspace::class, 'attempt_id'); }
    public function marks(): HasMany { return $this->hasMany(AssessmentAttemptMark::class, 'attempt_id'); }
    public function result(): HasOne { return $this->hasOne(AssessmentAttemptResult::class, 'attempt_id'); }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $owned) => $owned->where('user_id', $user->id)
            ->orWhereHas('group.members', fn (Builder $members) => $members->where('user_id', $user->id)));
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id || ($this->group_id !== null
            && $this->group()->whereHas('members', fn (Builder $members) => $members->where('user_id', $user->id))->exists());
    }
}
