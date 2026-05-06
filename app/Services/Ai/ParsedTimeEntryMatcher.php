<?php

namespace App\Services\Ai;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;

class ParsedTimeEntryMatcher
{
    public function match(array $parsed, ?int $companyId = null): array
    {
        $company = $this->matchCompany($parsed['company_name'] ?? null, $companyId);
        $employee = $this->matchEmployee($parsed['employee_name'] ?? null, $company?->id);
        $project = $this->matchProject($parsed['project_name'] ?? null, $company?->id, $employee?->id);
        $task = $this->matchTask($parsed['task_name'] ?? null, $company?->id);
        $date = $this->normalizeDate($parsed['entry_date'] ?? null);
        $hours = isset($parsed['hours']) ? round((float) $parsed['hours'], 2) : null;

        $missing = [];

        foreach ([
            'company' => $company,
            'employee' => $employee,
            'project' => $project,
            'task' => $task,
            'entry_date' => $date,
            'hours' => $hours,
        ] as $field => $value) {
            if (! $value) {
                $missing[] = $field;
            }
        }

        return [
            'row' => [
                'company_id' => $company?->id,
                'entry_date' => $date,
                'employee_id' => $employee?->id,
                'project_id' => $project?->id,
                'task_id' => $task?->id,
                'hours' => $hours,
            ],
            'matched' => [
                'company' => $company ? ['id' => $company->id, 'name' => $company->name] : null,
                'employee' => $employee ? ['id' => $employee->id, 'name' => $employee->name] : null,
                'project' => $project ? ['id' => $project->id, 'name' => $project->name] : null,
                'task' => $task ? ['id' => $task->id, 'name' => $task->name] : null,
            ],
            'missing_fields' => array_values(array_unique(array_merge($parsed['missing_fields'] ?? [], $missing))),
        ];
    }

    private function matchCompany(?string $name, ?int $companyId): ?Company
    {
        if ($companyId) {
            return Company::find($companyId);
        }

        return $this->nameMatch(Company::query(), $name);
    }

    private function matchEmployee(?string $name, ?int $companyId): ?Employee
    {
        return $this->nameMatch(
            Employee::query()->when($companyId, fn ($query) => $query->whereHas('companies', fn ($query) => $query->whereKey($companyId))),
            $name
        );
    }

    private function matchProject(?string $name, ?int $companyId, ?int $employeeId): ?Project
    {
        return $this->nameMatch(
            Project::query()
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->when($employeeId, fn ($query) => $query->whereHas('employees', fn ($query) => $query->whereKey($employeeId))),
            $name
        );
    }

    private function matchTask(?string $name, ?int $companyId): ?Task
    {
        return $this->nameMatch(
            Task::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId)),
            $name
        );
    }

    private function nameMatch($query, ?string $name)
    {
        if (! $name) {
            return null;
        }

        $normalized = $this->normalizeName($name);

        return $query->get()
            ->first(fn ($model) => $this->normalizeName($model->name) === $normalized)
            ?? $query->get()->first(fn ($model) => str_contains($this->normalizeName($model->name), $normalized) || str_contains($normalized, $this->normalizeName($model->name)));
    }

    private function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($name)));
    }

    private function normalizeDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'm-d-Y', 'd-m-Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $date);
            } catch (\Throwable) {
                continue;
            }

            if ($parsed && $parsed->format($format) === $date) {
                return $parsed->toDateString();
            }
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
