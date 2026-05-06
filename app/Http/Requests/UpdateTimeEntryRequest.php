<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'entry_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:24', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'hours.decimal' => 'Hours may have at most 2 decimal places.',
            'hours.gt' => 'Hours must be greater than zero.',
        ];
    }
}
