<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyTermPeriod extends Model
{
    use HasUuids;

    protected $fillable = ['study_term_id', 'name', 'type', 'start_at', 'end_at'];
    protected function casts(): array { return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime']; }
    public function studyTerm(): BelongsTo { return $this->belongsTo(StudyTerm::class); }
    public function term(): BelongsTo { return $this->studyTerm(); }
}
