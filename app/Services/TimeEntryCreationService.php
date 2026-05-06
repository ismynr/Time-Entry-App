<?php

namespace App\Services;

use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeEntryCreationService
{
    public function __construct(private readonly TimeEntryValidationService $validator) {}

    public function create(array $entries): array
    {
        return DB::transaction(function () use ($entries): array {
            $locks = $this->acquireEmployeeDateLocks($entries);

            try {
                $errors = $this->validator->validate($entries);

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }

                return collect($entries)
                    ->map(fn (array $entry) => TimeEntry::create($entry)->load(['company', 'employee', 'project', 'task']))
                    ->all();
            } finally {
                $this->releaseEmployeeDateLocks($locks);
            }
        });
    }

    public function update(TimeEntry $timeEntry, array $entry): TimeEntry
    {
        return DB::transaction(function () use ($timeEntry, $entry): TimeEntry {
            $lockEntries = [$timeEntry->only(['employee_id', 'entry_date']), $entry];
            $locks = $this->acquireEmployeeDateLocks($lockEntries);

            try {
                $errors = $this->validator->validate([$entry], [$timeEntry->id]);

                if ($errors !== []) {
                    throw ValidationException::withMessages(
                        collect($errors)
                            ->mapWithKeys(fn (array $messages, string $key) => [str_replace('entries.0.', '', $key) => $messages])
                            ->all()
                    );
                }

                $timeEntry->update($entry);

                return $timeEntry->refresh()->load(['company', 'employee', 'project', 'task']);
            } finally {
                $this->releaseEmployeeDateLocks($locks);
            }
        });
    }

    private function acquireEmployeeDateLocks(array $entries): array
    {
        if (DB::getDriverName() !== 'mysql') {
            return [];
        }

        $locks = collect($entries)
            ->map(fn (array $entry) => 'time-entry:'.sha1($entry['employee_id'].'|'.$entry['entry_date']))
            ->unique()
            ->sort()
            ->values()
            ->all();

        foreach ($locks as $lock) {
            $result = DB::selectOne('SELECT GET_LOCK(?, 10) as acquired', [$lock]);

            if ((int) $result->acquired !== 1) {
                throw ValidationException::withMessages([
                    'entries' => ['Could not reserve employee/date for saving. Please retry.'],
                ]);
            }
        }

        return $locks;
    }

    private function releaseEmployeeDateLocks(array $locks): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_reverse($locks) as $lock) {
            DB::selectOne('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }
}
