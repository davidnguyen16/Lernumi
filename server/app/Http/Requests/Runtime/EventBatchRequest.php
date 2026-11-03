<?php

namespace App\Http\Requests\Runtime;

use App\Exceptions\ToolApiException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EventBatchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if (strlen($this->getContent()) > 1048576) throw new ToolApiException('PAYLOAD_TOO_LARGE', 'Event batches must not exceed 1 MiB.', 413);
        if (! $this->isJson()) throw new ToolApiException('UNSUPPORTED_MEDIA_TYPE', 'Use application/json for event batches.', 415);
    }

    public function rules(): array
    {
        return ['batchId' => ['required', 'uuid'], 'seqStart' => ['required', 'integer', 'between:0,2147483647'],
            'seqEnd' => ['required', 'integer', 'gte:seqStart', 'max:2147483647'],
            'clientAt' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T.*(?:Z|[+-]\d{2}:\d{2})$/'],
            'events' => ['required', 'array', 'min:1', 'max:2000']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) return;
            $body = json_decode($this->getContent());
            if (! is_object($body) || ! is_array($body->events ?? null) || array_diff(array_keys((array) $body), ['batchId', 'seqStart', 'seqEnd', 'clientAt', 'events']))
                $validator->errors()->add('events', 'Supply an event array and only the documented batch fields.');
            elseif (count($body->events) !== (int) $this->input('seqEnd') - (int) $this->input('seqStart') + 1)
                $validator->errors()->add('events', 'Event count must match the inclusive sequence range.');
            elseif (! $this->storable($body->events))
                $validator->errors()->add('events', 'Events must contain finite JSON numbers and strings without null characters.');
        }];
    }

    private function storable(mixed $value): bool
    {
        if (is_float($value)) return is_finite($value);
        if (is_string($value)) return ! str_contains($value, "\0");
        if (is_array($value) || is_object($value)) {
            foreach ($value as $key => $item) if (! $this->storable((string) $key) || ! $this->storable($item)) return false;
        }
        return true;
    }
}
