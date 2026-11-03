<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyTerm extends Model
{
    use HasUuids;

    protected $fillable = ['institution_id', 'year', 'session'];
    protected function casts(): array { return ['year' => 'integer', 'session' => 'integer']; }
    public function institution(): BelongsTo { return $this->belongsTo(Institution::class); }
    public function periods(): HasMany { return $this->hasMany(StudyTermPeriod::class)->orderBy('start_at')->orderBy('id'); }
    public function offerings(): HasMany { return $this->hasMany(Offering::class, 'term_id'); }
}
