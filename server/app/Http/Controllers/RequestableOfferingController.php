<?php

namespace App\Http\Controllers;

use App\Http\Resources\RequestableOfferingResource;
use App\Models\Institution;
use App\Services\AccessService;
use Illuminate\Http\Request;

class RequestableOfferingController
{
    public function __invoke(Request $request, Institution $institution, AccessService $access)
    {
        $access->authorize($request->user(), 'institution.view', $institution);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'perPage' => ['sometimes', 'integer', 'between:1,100'], 'search' => ['sometimes', 'nullable', 'string', 'max:200']]);
        $query = $institution->offerings()->with(['subject:id,subject_code,subject_name', 'term:id,year,session']);
        if ($request->filled('search')) {
            $query->whereHas('subject', fn ($subjects) => $subjects->whereRaw('(strpos(lower(subject_code), lower(?)) > 0 OR strpos(lower(subject_name), lower(?)) > 0)', [$request->input('search'), $request->input('search')]));
        }
        return RequestableOfferingResource::collection($query->orderBy('id')->paginate($request->integer('perPage', 20)));
    }
}
