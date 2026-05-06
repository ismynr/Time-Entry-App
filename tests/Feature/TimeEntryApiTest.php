<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_time_entries_can_be_created(): void
    {
        [$company, $employee, $project, $task] = $this->validAssignment();

        $this->postJson('/api/time-entries/bulk', [
            'entries' => [[
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'project_id' => $project->id,
                'task_id' => $task->id,
                'entry_date' => '2026-05-06',
                'hours' => 3.5,
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.0.employee.id', $employee->id);

        $this->assertDatabaseHas('time_entries', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'entry_date' => '2026-05-06',
            'hours' => 3.5,
        ]);
    }

    public function test_employee_cannot_log_different_projects_on_same_date(): void
    {
        [$company, $employee, $project, $task] = $this->validAssignment();
        $otherProject = Project::create(['company_id' => $company->id, 'name' => 'Other Project']);
        $employee->projects()->attach($otherProject);

        $this->postJson('/api/time-entries/bulk', [
            'entries' => [
                [
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'project_id' => $project->id,
                    'task_id' => $task->id,
                    'entry_date' => '2026-05-06',
                    'hours' => 2,
                ],
                [
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'project_id' => $otherProject->id,
                    'task_id' => $task->id,
                    'entry_date' => '2026-05-06',
                    'hours' => 2,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entries.1.project_id');

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_relationship_mismatches_are_rejected(): void
    {
        [$company, $employee, $project, $task] = $this->validAssignment();
        $otherCompany = Company::create(['name' => 'Other Company']);
        $otherTask = Task::create(['company_id' => $otherCompany->id, 'name' => 'Other Task']);

        $this->postJson('/api/time-entries/bulk', [
            'entries' => [[
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'project_id' => $project->id,
                'task_id' => $otherTask->id,
                'entry_date' => '2026-05-06',
                'hours' => 1,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entries.0.task_id');

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_employee_project_assignment_is_required(): void
    {
        [$company, $employee, , $task] = $this->validAssignment();
        $unassignedProject = Project::create(['company_id' => $company->id, 'name' => 'Unassigned']);

        $this->postJson('/api/time-entries/bulk', [
            'entries' => [[
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'project_id' => $unassignedProject->id,
                'task_id' => $task->id,
                'entry_date' => '2026-05-06',
                'hours' => 1,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entries.0.project_id');
    }

    public function test_existing_hours_plus_submitted_rows_cannot_exceed_twenty_four(): void
    {
        [$company, $employee, $project, $task] = $this->validAssignment();

        $this->postJson('/api/time-entries/bulk', [
            'entries' => [[
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'project_id' => $project->id,
                'task_id' => $task->id,
                'entry_date' => '2026-05-06',
                'hours' => 21,
            ]],
        ])->assertCreated();

        $this->postJson('/api/time-entries/bulk', [
            'entries' => [
                [
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'project_id' => $project->id,
                    'task_id' => $task->id,
                    'entry_date' => '2026-05-06',
                    'hours' => 2,
                ],
                [
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'project_id' => $project->id,
                    'task_id' => $task->id,
                    'entry_date' => '2026-05-06',
                    'hours' => 2,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entries.0.hours', 'entries.1.hours']);

        $this->assertDatabaseCount('time_entries', 1);
    }

    public function test_company_lookups_include_project_employee_ids(): void
    {
        [$company, $employee, $project] = $this->validAssignment();

        $this->getJson("/api/companies/{$company->id}/lookups")
            ->assertOk()
            ->assertJsonPath('projects.0.id', $project->id)
            ->assertJsonPath('projects.0.employee_ids.0', $employee->id);
    }

    public function test_time_entry_can_be_updated_without_conflicting_with_itself(): void
    {
        [$company, $employee, $project, $task] = $this->validAssignment();
        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'entry_date' => '2026-05-06',
            'hours' => 4,
        ]);

        $this->putJson("/api/time-entries/{$entry->id}", [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'entry_date' => '2026-05-06',
            'hours' => 6,
        ])
            ->assertOk()
            ->assertJsonPath('data.hours', '6.00');

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'hours' => 6,
        ]);
    }

    public function test_time_entry_update_rejects_project_conflict(): void
    {
        [$company, $employee, $project, $task] = $this->validAssignment();
        $otherProject = Project::create(['company_id' => $company->id, 'name' => 'Other Project']);
        $employee->projects()->attach($otherProject);

        TimeEntry::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'entry_date' => '2026-05-06',
            'hours' => 4,
        ]);
        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'entry_date' => '2026-05-07',
            'hours' => 2,
        ]);

        $this->putJson("/api/time-entries/{$entry->id}", [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'project_id' => $otherProject->id,
            'task_id' => $task->id,
            'entry_date' => '2026-05-06',
            'hours' => 2,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');
    }

    public function test_history_can_search_and_sort(): void
    {
        [$company, $employee, $project, $task] = $this->validAssignment();
        $otherEmployee = Employee::create(['name' => 'Zoe', 'email' => 'zoe@example.com']);
        $company->employees()->attach($otherEmployee);
        $otherEmployee->projects()->attach($project);

        TimeEntry::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'entry_date' => '2026-05-06',
            'hours' => 1,
        ]);
        TimeEntry::create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'entry_date' => '2026-05-07',
            'hours' => 2,
        ]);

        $this->getJson('/api/time-entries?search=zoe&sort=employee&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.employee.name', 'Zoe')
            ->assertJsonCount(1, 'data');
    }

    private function validAssignment(): array
    {
        $company = Company::create(['name' => 'Acme']);
        $employee = Employee::create(['name' => 'Ari', 'email' => 'ari@example.com']);
        $project = Project::create(['company_id' => $company->id, 'name' => 'Build']);
        $task = Task::create(['company_id' => $company->id, 'name' => 'Development']);

        $company->employees()->attach($employee);
        $employee->projects()->attach($project);

        return [$company, $employee, $project, $task];
    }
}
