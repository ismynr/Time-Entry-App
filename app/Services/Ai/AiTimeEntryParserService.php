<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AiTimeEntryParserService
{
    public function __construct(private readonly ParsedTimeEntryMatcher $matcher) {}

    public function parse(string $text, ?int $companyId = null): array
    {
        $parsed = match (config('ai.provider')) {
            'openai' => $this->parseWithOpenAi($text),
            'anthropic' => $this->parseWithAnthropic($text),
            default => $this->parseWithFakeProvider($text),
        };

        $matched = $this->matcher->match($parsed, $companyId);

        return [
            'provider' => config('ai.provider'),
            'parsed' => $parsed,
            ...$matched,
        ];
    }

    private function parseWithFakeProvider(string $text): array
    {
        $patterns = [
            '/^(?<employee>.+?)\s+worked\s+on\s+(?<project>.+?)\s+on\s+(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})\s+doing\s+(?<task>.+?)\s+for\s+(?<hours>\d+(?:\.\d+)?)\s+hours?\.?$/i',
            '/^(?<employee>.+?)\s+logged\s+(?<hours>\d+(?:\.\d+)?)\s+hours?\s+on\s+(?<project>.+?)\s+for\s+(?<task>.+?)\s+on\s+(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})\.?$/i',
            '/^(?<employee>.+?)\s+spent\s+(?<hours>\d+(?:\.\d+)?)\s+hours?\s+on\s+(?<task>.+?)\s+for\s+(?<project>.+?)\s+on\s+(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})\.?$/i',
            '/^on\s+(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2}),?\s+(?<employee>.+?)\s+spent\s+(?<hours>\d+(?:\.\d+)?)\s+hours?\s+on\s+(?<project>.+?)\s+doing\s+(?<task>.+?)\.?$/i',
            '/^(?<employee>.+?)\s+did\s+(?<task>.+?)\s+for\s+(?<hours>\d+(?:\.\d+)?)\s+hours?\s+on\s+(?<project>.+?)\s+on\s+(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})\.?$/i',
            '/^(?<employee>.+?)\s+worked\s+(?<hours>\d+(?:\.\d+)?)\s+hours?\s+on\s+(?<project>.+?)\s+on\s+(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})\s+for\s+(?<task>.+?)\.?$/i',
            '/^(?<employee>.+?):\s*(?<hours>\d+(?:\.\d+)?)\s+hours?,\s*(?<project>.+?),\s*(?<task>.+?),\s*(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})\.?$/i',
            '/^(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})\s+-\s+(?<employee>.+?)\s+-\s+(?<project>.+?)\s+-\s+(?<task>.+?)\s+-\s+(?<hours>\d+(?:\.\d+)?)h(?:ours?)?\.?$/i',
            '/^(?<employee>.+?)\s+completed\s+(?<task>.+?)\s+on\s+(?<project>.+?)\s+at\s+(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})\s+for\s+(?<hours>\d+(?:\.\d+)?)\s+hours?\.?$/i',
            '/^(?<employee>.+?)\s+booked\s+(?<hours>\d+(?:\.\d+)?)\s+hours?\s+to\s+(?<project>.+?)\s*\/\s*(?<task>.+?)\s+on\s+(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})\.?$/i',
        ];
        $matches = null;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($text), $matches)) {
                break;
            }
        }

        if (! $matches) {
            return [
                'employee_name' => null,
                'company_name' => null,
                'project_name' => null,
                'task_name' => null,
                'entry_date' => null,
                'hours' => null,
                'confidence' => 0.2,
                'missing_fields' => ['employee', 'project', 'task', 'entry_date', 'hours'],
                'notes' => 'Fake parser supports common time-entry sentence patterns for local demos.',
            ];
        }

        return [
            'employee_name' => trim($matches['employee']),
            'company_name' => null,
            'project_name' => trim($matches['project']),
            'task_name' => trim($matches['task']),
            'entry_date' => str_replace('-', '/', trim($matches['date'])),
            'hours' => (float) $matches['hours'],
            'confidence' => 0.78,
            'missing_fields' => [],
            'notes' => 'Parsed locally without external AI.',
        ];
    }

    private function parseWithOpenAi(string $text): array
    {
        $apiKey = config('ai.openai.api_key');

        if (! $apiKey) {
            throw ValidationException::withMessages(['text' => ['OPENAI_API_KEY is not configured.']]);
        }

        $response = Http::withToken($apiKey)
            ->timeout(20)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('ai.openai.model'),
                'input' => $this->prompt($text),
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'time_entry_parse',
                        'schema' => $this->schema(),
                        'strict' => true,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages(['text' => ['OpenAI parsing failed.']]);
        }

        return $this->decodeJson($response->json('output.0.content.0.text'));
    }

    private function parseWithAnthropic(string $text): array
    {
        $apiKey = config('ai.anthropic.api_key');

        if (! $apiKey) {
            throw ValidationException::withMessages(['text' => ['ANTHROPIC_API_KEY is not configured.']]);
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(20)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('ai.anthropic.model'),
                'max_tokens' => 500,
                'tools' => [[
                    'name' => 'parse_time_entry',
                    'description' => 'Extract one time entry from natural language.',
                    'input_schema' => $this->schema(),
                ]],
                'tool_choice' => ['type' => 'tool', 'name' => 'parse_time_entry'],
                'messages' => [[
                    'role' => 'user',
                    'content' => $this->prompt($text),
                ]],
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages(['text' => ['Anthropic parsing failed.']]);
        }

        return $response->collect('content')->firstWhere('type', 'tool_use')['input'] ?? [];
    }

    private function prompt(string $text): string
    {
        return "Parse this into exactly one time entry. If a field is missing, return null and list it in missing_fields.\n\nText: {$text}";
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['employee_name', 'company_name', 'project_name', 'task_name', 'entry_date', 'hours', 'confidence', 'missing_fields', 'notes'],
            'properties' => [
                'employee_name' => ['type' => ['string', 'null']],
                'company_name' => ['type' => ['string', 'null']],
                'project_name' => ['type' => ['string', 'null']],
                'task_name' => ['type' => ['string', 'null']],
                'entry_date' => ['type' => ['string', 'null'], 'description' => 'ISO date preferred, YYYY-MM-DD.'],
                'hours' => ['type' => ['number', 'null']],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'missing_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
                'notes' => ['type' => ['string', 'null']],
            ],
        ];
    }

    private function decodeJson(?string $json): array
    {
        $decoded = json_decode($json ?? '', true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['text' => ['AI returned an unreadable response.']]);
        }

        return $decoded;
    }
}
