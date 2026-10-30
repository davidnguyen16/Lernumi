<?php

namespace App\Http\Requests\Subjects;

class UpdateSubjectRequest extends SubjectRequest
{
    public function rules(): array
    {
        return $this->subjectRules(partial: $this->isMethod('PATCH'));
    }

    public function subjectAttributes(): array
    {
        $attributes = parent::subjectAttributes();

        if ($this->isMethod('PUT')) {
            $attributes['subject_description'] ??= null;
        }

        return $attributes;
    }
}
