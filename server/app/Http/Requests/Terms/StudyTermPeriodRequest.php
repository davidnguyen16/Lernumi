<?php

namespace App\Http\Requests\Terms;

use App\Services\AccessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StudyTermPeriodRequest extends FormRequest
{
    public function authorize(): bool { return app(AccessService::class)->can($this->user(), 'term.update', $this->route('institution')); }

    public function rules(): array
    {
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        return ['name' => [$presence, 'filled', 'string', 'max:120'],
            'type' => [$presence, Rule::in(['TEACHING', 'BREAK', 'EXAM'])],
            'startAt' => [$presence, 'date'], 'endAt' => [$presence, 'date']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) return;
            $period = $this->route('period');
            $start = $this->input('startAt', $period?->start_at);
            $end = $this->input('endAt', $period?->end_at);
            if ($start && $end && CarbonImmutable::parse($end)->lessThanOrEqualTo(CarbonImmutable::parse($start)))
                $validator->errors()->add('endAt', 'The end time must be after the start time.');
        }];
    }

    public function attributesForModel(): array
    {
        $attributes = [];
        foreach (['name' => 'name', 'type' => 'type', 'startAt' => 'start_at', 'endAt' => 'end_at'] as $field => $column)
            if (array_key_exists($field, $this->validated())) $attributes[$column] = $this->validated($field);
        return $attributes;
    }
}
