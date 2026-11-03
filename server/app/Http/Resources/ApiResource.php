<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** Transport mapping for flat models; toArray honors model-hidden secrets. */
class ApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = [];
        foreach ($this->resource->toArray() as $key => $value) {
            $result[Str::camel($key)] = $value;
        }

        return $result;
    }
}
