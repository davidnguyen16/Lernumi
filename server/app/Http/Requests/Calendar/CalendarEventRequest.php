<?php

namespace App\Http\Requests\Calendar;

use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CalendarEventRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        $values = [];
        if (is_string($this->input('title'))) $values['title'] = trim($this->input('title'));
        if (is_string($this->input('note'))) $values['note'] = trim($this->input('note')) ?: null;
        $this->merge($values);
    }
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $date = ['date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/'];
        return [
            'title' => [$required, 'string', 'max:120'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'startAt' => [$required, ...$date],
            'endAt' => [$required, ...$date],
            'offeringId' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) return;
            $event = $this->route('event');
            $start = $this->input('startAt', $event instanceof CalendarEvent ? $event->start_at?->toISOString() : null);
            $end = $this->input('endAt', $event instanceof CalendarEvent ? $event->end_at?->toISOString() : null);
            if ($start && $end) {
                $from = CarbonImmutable::parse($start);
                $to = CarbonImmutable::parse($end);
                if ($to->lessThanOrEqualTo($from) || $from->diffInSeconds($to) > 86400) {
                    $validator->errors()->add('endAt', 'Events must end after they start and last at most 24 hours.');
                }
            }
        }];
    }
}
