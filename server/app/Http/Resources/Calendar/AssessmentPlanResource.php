<?php

namespace App\Http\Resources\Calendar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['assessmentId' => $this->assessment_id, 'startDate' => $this->start_date->format('Y-m-d'), 'endDate' => $this->end_date->format('Y-m-d')];
    }
}
