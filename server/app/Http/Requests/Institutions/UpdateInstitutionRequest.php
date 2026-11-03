<?php

namespace App\Http\Requests\Institutions;

class UpdateInstitutionRequest extends InstitutionRequest
{
    public function rules(): array { return $this->institutionRules($this->isMethod('PATCH')); }
}
