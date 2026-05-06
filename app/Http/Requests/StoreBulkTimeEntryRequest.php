<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBulkTimeEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.company_id' => ['required', 'integer', 'exists:companies,id'],
            'entries.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'entries.*.project_id' => ['required', 'integer', 'exists:projects,id'],
            'entries.*.task_id' => ['required', 'integer', 'exists:tasks,id'],
            'entries.*.entry_date' => ['required', 'date'],
            'entries.*.hours' => ['required', 'numeric', 'gt:0', 'max:24', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'entries.*.hours.decimal' => 'Hours may have at most 2 decimal places.',
            'entries.*.hours.gt' => 'Hours must be greater than zero.',
        ];
    }
}
