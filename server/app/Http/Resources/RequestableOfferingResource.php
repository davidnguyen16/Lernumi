<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Public course labels for members requesting an offering scope; no assessment or roster data. */
class RequestableOfferingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'institutionId' => $this->institution_id,
            'subjectId' => $this->subject_id, 'termId' => $this->term_id,
            'subject' => ['subjectCode' => $this->subject->subject_code, 'subjectName' => $this->subject->subject_name],
            'term' => ['year' => $this->term->year, 'session' => $this->term->session],
        ];
    }
}
