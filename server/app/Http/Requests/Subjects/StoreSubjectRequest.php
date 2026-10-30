<?php

namespace App\Http\Requests\Subjects;

class StoreSubjectRequest extends SubjectRequest
{
    public function rules(): array
    {
        return $this->subjectRules(partial: false);
    }
}
