<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subject extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['subject_code', 'subject_name', 'subject_description'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
