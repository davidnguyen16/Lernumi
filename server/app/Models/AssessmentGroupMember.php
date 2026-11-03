<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentGroupMember extends Model
{
    use HasUuids;
    protected $fillable = ['group_id', 'assessment_id', 'user_id'];
    public function group(): BelongsTo { return $this->belongsTo(AssessmentGroup::class, 'group_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
