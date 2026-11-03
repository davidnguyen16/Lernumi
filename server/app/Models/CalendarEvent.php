<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'offering_id', 'title', 'note', 'start_at', 'end_at'];
    protected function casts(): array { return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime']; }
    public function offering(): BelongsTo { return $this->belongsTo(Offering::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
