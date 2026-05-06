<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $companies = collect(['Acme Studio', 'Northwind Ops', 'Vertex Labs'])
            ->map(fn (string $name) => Company::create(['name' => $name]));

        $employees = collect([
            ['name' => 'Ari Wijaya', 'email' => 'ari@example.com'],
            ['name' => 'Bella Hart', 'email' => 'bella@example.com'],
            ['name' => 'Chen Park', 'email' => 'chen@example.com'],
            ['name' => 'Dina Morris', 'email' => 'dina@example.com'],
            ['name' => 'Evan Stone', 'email' => 'evan@example.com'],
            ['name' => 'Farah Lim', 'email' => 'farah@example.com'],
            ['name' => 'Gabe Nolan', 'email' => 'gabe@example.com'],
            ['name' => 'Hana Yusuf', 'email' => 'hana@example.com'],
        ])->map(fn (array $employee) => Employee::create($employee));

        $companies[0]->employees()->attach($employees->slice(0, 5)->pluck('id'));
        $companies[1]->employees()->attach($employees->slice(2, 4)->pluck('id'));
        $companies[2]->employees()->attach($employees->slice(4, 4)->pluck('id'));
        $companies[1]->employees()->attach($employees[0]->id);

        $projectsByCompany = $companies->mapWithKeys(function (Company $company, int $index) {
            return [$company->id => collect(['Platform Build', 'Client Support', 'Reporting Suite', 'Automation'])
                ->map(fn (string $name) => Project::create([
                    'company_id' => $company->id,
                    'name' => $name.' '.($index + 1),
                ]))];
        });

        $tasksByCompany = $companies->mapWithKeys(function (Company $company) {
            return [$company->id => collect(['Development', 'Design', 'QA', 'Planning', 'Review', 'Deployment'])
                ->map(fn (string $name) => Task::create([
                    'company_id' => $company->id,
                    'name' => $name,
                ]))];
        });

        $employees[0]->projects()->attach([$projectsByCompany[$companies[0]->id][0]->id, $projectsByCompany[$companies[1]->id][0]->id]);
        $employees[1]->projects()->attach([$projectsByCompany[$companies[0]->id][0]->id, $projectsByCompany[$companies[0]->id][1]->id]);
        $employees[2]->projects()->attach([$projectsByCompany[$companies[0]->id][2]->id, $projectsByCompany[$companies[1]->id][1]->id]);
        $employees[3]->projects()->attach([$projectsByCompany[$companies[0]->id][3]->id, $projectsByCompany[$companies[1]->id][2]->id]);
        $employees[4]->projects()->attach([$projectsByCompany[$companies[0]->id][1]->id, $projectsByCompany[$companies[2]->id][0]->id]);
        $employees[5]->projects()->attach([$projectsByCompany[$companies[1]->id][0]->id, $projectsByCompany[$companies[2]->id][1]->id]);
        $employees[6]->projects()->attach([$projectsByCompany[$companies[2]->id][2]->id]);
        $employees[7]->projects()->attach([$projectsByCompany[$companies[2]->id][3]->id]);

        TimeEntry::create([
            'company_id' => $companies[0]->id,
            'employee_id' => $employees[0]->id,
            'project_id' => $projectsByCompany[$companies[0]->id][0]->id,
            'task_id' => $tasksByCompany[$companies[0]->id][0]->id,
            'entry_date' => now()->subDay()->toDateString(),
            'hours' => 4.5,
        ]);

        TimeEntry::create([
            'company_id' => $companies[0]->id,
            'employee_id' => $employees[0]->id,
            'project_id' => $projectsByCompany[$companies[0]->id][0]->id,
            'task_id' => $tasksByCompany[$companies[0]->id][2]->id,
            'entry_date' => now()->subDay()->toDateString(),
            'hours' => 2,
        ]);

        TimeEntry::create([
            'company_id' => $companies[1]->id,
            'employee_id' => $employees[5]->id,
            'project_id' => $projectsByCompany[$companies[1]->id][0]->id,
            'task_id' => $tasksByCompany[$companies[1]->id][1]->id,
            'entry_date' => now()->toDateString(),
            'hours' => 7.5,
        ]);
    }
}
