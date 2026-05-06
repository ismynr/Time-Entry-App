<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;

class CompanyLookupController extends Controller
{
    public function show(Company $company): array
    {
        $company->load([
            'employees' => fn ($query) => $query->orderBy('name')->select('employees.id', 'name', 'email'),
            'projects' => fn ($query) => $query
                ->with('employees:id')
                ->orderBy('name')
                ->select('id', 'company_id', 'name'),
            'tasks' => fn ($query) => $query->orderBy('name')->select('id', 'company_id', 'name'),
        ]);

        return [
            'employees' => $company->employees,
            'projects' => $company->projects->map(fn ($project) => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'name' => $project->name,
                'employee_ids' => $project->employees->pluck('id')->values(),
            ]),
            'tasks' => $company->tasks,
        ];
    }
}
