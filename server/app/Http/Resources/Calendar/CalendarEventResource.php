<?php

namespace App\Http\Resources\Calendar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'note' => $this->note,
            'startAt' => $this->start_at->toISOString(), 'endAt' => $this->end_at->toISOString(),
            'offeringId' => $this->offering_id,
        ];
    }
}
