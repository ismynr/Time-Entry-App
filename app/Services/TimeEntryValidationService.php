<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;

class TimeEntryValidationService
{
    public function validate(array $entries, array $ignoredEntryIds = []): array
    {
        $errors = [];
        $totals = [];
        $projectByEmployeeDate = [];

        foreach ($entries as $index => $entry) {
            $key = $entry['employee_id'].'|'.$entry['entry_date'];
            $totals[$key] = ($totals[$key] ?? 0) + (float) $entry['hours'];

            if (isset($projectByEmployeeDate[$key]) && (int) $projectByEmployeeDate[$key] !== (int) $entry['project_id']) {
                $errors["entries.$index.project_id"][] = 'An employee may only log one project per date.';
            }

            $projectByEmployeeDate[$key] ??= $entry['project_id'];
            $this->validateRelationships($entry, $index, $errors);
        }

        $this->validateExistingEntries($entries, $totals, $projectByEmployeeDate, $errors, $ignoredEntryIds);

        return $errors;
    }

    private function validateRelationships(array $entry, int $index, array &$errors): void
    {
        if (! Employee::whereKey($entry['employee_id'])->whereHas('companies', fn ($query) => $query->whereKey($entry['company_id']))->exists()) {
            $errors["entries.$index.employee_id"][] = 'Employee does not belong to the selected company.';
        }

        if (! Project::whereKey($entry['project_id'])->where('company_id', $entry['company_id'])->exists()) {
            $errors["entries.$index.project_id"][] = 'Project does not belong to the selected company.';
        }

        if (! Task::whereKey($entry['task_id'])->where('company_id', $entry['company_id'])->exists()) {
            $errors["entries.$index.task_id"][] = 'Task does not belong to the selected company.';
        }

        if (! Employee::whereKey($entry['employee_id'])->whereHas('projects', fn ($query) => $query->whereKey($entry['project_id']))->exists()) {
            $errors["entries.$index.project_id"][] = 'Employee is not assigned to the selected project.';
        }
    }

    private function validateExistingEntries(array $entries, array $submittedTotals, array $submittedProjects, array &$errors, array $ignoredEntryIds): void
    {
        foreach ($submittedTotals as $key => $submittedHours) {
            [$employeeId, $entryDate] = explode('|', $key, 2);
            $submittedProjectId = $submittedProjects[$key];

            $existing = TimeEntry::where('employee_id', $employeeId)
                ->whereDate('entry_date', $entryDate)
                ->when($ignoredEntryIds !== [], fn ($query) => $query->whereNotIn('id', $ignoredEntryIds))
                ->selectRaw('COALESCE(SUM(hours), 0) as total_hours')
                ->selectRaw('MIN(project_id) as project_id')
                ->first();

            $existingHours = (float) ($existing?->total_hours ?? 0);
            $existingProjectId = $existing?->project_id;

            foreach ($entries as $index => $entry) {
                $entryKey = $entry['employee_id'].'|'.$entry['entry_date'];

                if ($entryKey !== $key) {
                    continue;
                }

                if ($existingProjectId && (int) $existingProjectId !== (int) $submittedProjectId) {
                    $errors["entries.$index.project_id"][] = 'Existing entries for this employee/date use a different project.';
                }

                if ($existingHours + $submittedHours > 24) {
                    $errors["entries.$index.hours"][] = 'Existing entries plus submitted rows exceed 24 hours for the employee/date.';
                }
            }
        }
    }
}
