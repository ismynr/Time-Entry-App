<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBulkTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Models\TimeEntry;
use App\Services\TimeEntryCreationService;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    public function index(Request $request)
    {
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $sortColumns = [
            'company' => 'companies.name',
            'date' => 'time_entries.entry_date',
            'employee' => 'employees.name',
            'project' => 'projects.name',
            'task' => 'tasks.name',
            'hours' => 'time_entries.hours',
            'created_at' => 'time_entries.created_at',
        ];
        $sortColumn = $sortColumns[$sort] ?? 'time_entries.entry_date';

        return TimeEntry::query()
            ->with(['company:id,name', 'employee:id,name,email', 'project:id,name', 'task:id,name'])
            ->join('companies', 'companies.id', '=', 'time_entries.company_id')
            ->join('employees', 'employees.id', '=', 'time_entries.employee_id')
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
            ->select('time_entries.*')
            ->when($request->integer('company_id'), fn ($query, int $companyId) => $query->where('time_entries.company_id', $companyId))
            ->when($request->integer('employee_id'), fn ($query, int $employeeId) => $query->where('time_entries.employee_id', $employeeId))
            ->when($request->integer('project_id'), fn ($query, int $projectId) => $query->where('time_entries.project_id', $projectId))
            ->when($request->date('date_from'), fn ($query, $date) => $query->whereDate('time_entries.entry_date', '>=', $date))
            ->when($request->date('date_to'), fn ($query, $date) => $query->whereDate('time_entries.entry_date', '<=', $date))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('companies.name', 'like', "%{$search}%")
                        ->orWhere('employees.name', 'like', "%{$search}%")
                        ->orWhere('employees.email', 'like', "%{$search}%")
                        ->orWhere('projects.name', 'like', "%{$search}%")
                        ->orWhere('tasks.name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $direction)
            ->orderByDesc('time_entries.created_at')
            ->paginate($request->integer('per_page', 10));
    }

    public function store(StoreBulkTimeEntryRequest $request, TimeEntryCreationService $creator)
    {
        return response()->json([
            'message' => 'Time entries saved.',
            'data' => $creator->create($request->validated('entries')),
        ], 201);
    }

    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry, TimeEntryCreationService $creator)
    {
        return response()->json([
            'message' => 'Time entry updated.',
            'data' => $creator->update($timeEntry, $request->validated()),
        ]);
    }
}
