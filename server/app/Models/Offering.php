<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offering extends Model
{
    use HasUuids;

    protected $fillable = ['institution_id', 'subject_id', 'term_id'];
    public function institution(): BelongsTo { return $this->belongsTo(Institution::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function term(): BelongsTo { return $this->belongsTo(StudyTerm::class, 'term_id'); }
    public function studyTerm(): BelongsTo { return $this->term(); }
    public function assessments(): HasMany { return $this->hasMany(Assessment::class); }
}
