<?php

namespace App\Services\CircuitBreaker;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseStorage implements StorageInterface
{
    protected string $table = 'circuit_breaker_states';

    public function __construct()
    {
        // Ensure table exists (should be created via migration)
        if (! Schema::hasTable($this->table)) {
            $this->createTable();
        }
    }

    /**
     * Create circuit breaker table if it doesn't exist
     */
    protected function createTable(): void
    {
        Schema::create($this->table, function ($table) {
            $table->string('key', 128)->primary();
            $table->string('state', 16)->default('closed');
            $table->integer('failure_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index('state');
            $table->index('last_failure_at');
        });
    }

    public function getState(string $key): ?array
    {
        $record = DB::table($this->table)->where('key', $key)->first();

        if (! $record) {
            return null;
        }

        return [
            'state' => State::from($record->state),
            'failure_count' => $record->failure_count,
            'success_count' => $record->success_count,
            'last_failure_at' => $record->last_failure_at ? new \DateTime($record->last_failure_at) : null,
            'opened_at' => $record->opened_at ? new \DateTime($record->opened_at) : null,
        ];
    }

    public function setState(string $key, State $state, int $failureCount, ?int $lastFailureTime = null): void
    {
        $data = [
            'state' => $state->value,
            'failure_count' => $failureCount,
            'updated_at' => now(),
        ];

        if ($lastFailureTime) {
            $data['last_failure_at'] = date('Y-m-d H:i:s', $lastFailureTime);
        }

        if ($state === State::OPEN) {
            $data['opened_at'] = now();
        } elseif ($state === State::CLOSED) {
            $data['opened_at'] = null;
            $data['success_count'] = 0;
        }

        DB::table($this->table)->updateOrInsert(
            ['key' => $key],
            $data
        );
    }

    public function incrementFailure(string $key): int
    {
        $record = DB::table($this->table)->where('key', $key)->first();

        if ($record) {
            $newCount = $record->failure_count + 1;
            DB::table($this->table)
                ->where('key', $key)
                ->update([
                    'failure_count' => $newCount,
                    'last_failure_at' => now(),
                    'updated_at' => now(),
                ]);

            return $newCount;
        }

        // Create new record
        DB::table($this->table)->insert([
            'key' => $key,
            'state' => State::CLOSED->value,
            'failure_count' => 1,
            'last_failure_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 1;
    }

    public function resetFailure(string $key): void
    {
        DB::table($this->table)
            ->where('key', $key)
            ->update([
                'failure_count' => 0,
                'success_count' => 0,
                'last_failure_at' => null,
                'opened_at' => null,
                'state' => State::CLOSED->value,
                'updated_at' => now(),
            ]);
    }

    public function recordSuccess(string $key): void
    {
        $record = DB::table($this->table)->where('key', $key)->first();

        if ($record) {
            $newCount = $record->success_count + 1;
            DB::table($this->table)
                ->where('key', $key)
                ->update([
                    'success_count' => $newCount,
                    'updated_at' => now(),
                ]);
        } else {
            // Create new record
            DB::table($this->table)->insert([
                'key' => $key,
                'state' => State::CLOSED->value,
                'success_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
