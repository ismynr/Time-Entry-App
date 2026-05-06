<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiTimeEntryParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_ai_parser_returns_matched_row_for_confirming(): void
    {
        [$company, $employee, $project, $task] = $this->validLookups();

        $this->postJson('/api/ai/time-entry/parse', [
            'company_id' => $company->id,
            'text' => 'Ari Wijaya worked on Platform Build on 01/01/2026 doing Development for 4 hours.',
        ])
            ->assertOk()
            ->assertJsonPath('provider', 'fake')
            ->assertJsonPath('row.company_id', $company->id)
            ->assertJsonPath('row.employee_id', $employee->id)
            ->assertJsonPath('row.project_id', $project->id)
            ->assertJsonPath('row.task_id', $task->id)
            ->assertJsonPath('row.entry_date', '2026-01-01')
            ->assertJsonPath('row.hours', 4)
            ->assertJsonPath('missing_fields', []);
    }

    public function test_fake_ai_parser_reports_missing_matches_for_user_confirmation(): void
    {
        $this->validLookups();

        $this->postJson('/api/ai/time-entry/parse', [
            'text' => 'John worked on Project X on 01/01/2026 doing cleanup for 4 hours.',
        ])
            ->assertOk()
            ->assertJsonPath('parsed.employee_name', 'John')
            ->assertJsonPath('parsed.project_name', 'Project X')
            ->assertJsonPath('row.employee_id', null)
            ->assertJsonPath('row.project_id', null)
            ->assertJsonPath('row.task_id', null)
            ->assertJsonFragment(['employee'])
            ->assertJsonFragment(['project'])
            ->assertJsonFragment(['task']);
    }

    public function test_ai_parse_requires_text(): void
    {
        $this->postJson('/api/ai/time-entry/parse', ['text' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('text');
    }

    #[DataProvider('naturalLanguageEntryPhrases')]
    public function test_fake_ai_parser_accepts_multiple_natural_language_phrasings(string $text, string $date, float $hours): void
    {
        [$company, $employee, $project, $task] = $this->validLookups();

        $response = $this->postJson('/api/ai/time-entry/parse', [
            'company_id' => $company->id,
            'text' => $text,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('row.company_id', $company->id)
            ->assertJsonPath('row.employee_id', $employee->id)
            ->assertJsonPath('row.project_id', $project->id)
            ->assertJsonPath('row.task_id', $task->id)
            ->assertJsonPath('row.entry_date', $date)
            ->assertJsonPath('missing_fields', []);

        $this->assertEqualsWithDelta($hours, (float) $response->json('row.hours'), 0.001);
    }

    public static function naturalLanguageEntryPhrases(): array
    {
        return [
            'worked on project doing task' => [
                'Ari Wijaya worked on Platform Build on 01/01/2026 doing Development for 4 hours.',
                '2026-01-01',
                4.0,
            ],
            'logged hours on project for task' => [
                'Ari Wijaya logged 3.5 hours on Platform Build for Development on 01/02/2026.',
                '2026-01-02',
                3.5,
            ],
            'spent hours on task for project' => [
                'Ari Wijaya spent 2 hours on Development for Platform Build on 01/03/2026.',
                '2026-01-03',
                2.0,
            ],
            'date first sentence' => [
                'On 01/04/2026, Ari Wijaya spent 6 hours on Platform Build doing Development.',
                '2026-01-04',
                6.0,
            ],
            'did task for hours' => [
                'Ari Wijaya did Development for 1.25 hours on Platform Build on 01/05/2026.',
                '2026-01-05',
                1.25,
            ],
            'worked hours on project for task' => [
                'Ari Wijaya worked 7 hours on Platform Build on 2026-01-06 for Development.',
                '2026-01-06',
                7.0,
            ],
            'colon compact format' => [
                'Ari Wijaya: 5 hours, Platform Build, Development, 01/07/2026.',
                '2026-01-07',
                5.0,
            ],
            'dash separated format' => [
                '01/08/2026 - Ari Wijaya - Platform Build - Development - 4h.',
                '2026-01-08',
                4.0,
            ],
            'completed task on project' => [
                'Ari Wijaya completed Development on Platform Build at 01/09/2026 for 2.75 hours.',
                '2026-01-09',
                2.75,
            ],
            'booked hours to project slash task' => [
                'Ari Wijaya booked 8 hours to Platform Build / Development on 01/10/2026.',
                '2026-01-10',
                8.0,
            ],
        ];
    }

    private function validLookups(): array
    {
        $company = Company::create(['name' => 'Acme']);
        $employee = Employee::create(['name' => 'Ari Wijaya', 'email' => 'ari@example.com']);
        $project = Project::create(['company_id' => $company->id, 'name' => 'Platform Build']);
        $task = Task::create(['company_id' => $company->id, 'name' => 'Development']);

        $company->employees()->attach($employee);
        $employee->projects()->attach($project);

        return [$company, $employee, $project, $task];
    }
}
