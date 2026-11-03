<?php

namespace App\Http\Requests\Institutions;

class StoreInstitutionRequest extends InstitutionRequest
{
    public function rules(): array
    {
        return $this->institutionRules(false);
    }
}
