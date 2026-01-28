<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearOldMailJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:clear-mail-jobs {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear ErrorNotificationEmail and LoginNotificationEmail jobs from the queue';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queueTable = config('queue.connections.database.table', 'jobs');
        $queueName = config('queue.connections.database.queue', 'default');

        // Mail classes to clear
        $mailClasses = [
            'ErrorNotificationEmail',
            'LoginNotificationEmail',
        ];

        try {
            // Get all jobs from the queue
            $jobs = DB::table($queueTable)
                ->where('queue', $queueName)
                ->get();

            $deletedCount = 0;

            foreach ($jobs as $job) {
                $payload = json_decode($job->payload, true);

                // Check if this is a mail job
                if (! isset($payload['data']['commandName'])) {
                    continue;
                }

                // Check if it's one of the mail classes we want to clear
                foreach ($mailClasses as $mailClass) {
                    if (str_contains($payload['data']['commandName'], $mailClass)) {
                        // Delete this job
                        DB::table($queueTable)->where('id', $job->id)->delete();
                        $deletedCount++;
                        break;
                    }
                }
            }

            $this->info("Cleared {$deletedCount} mail job(s) from the queue.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to clear mail jobs: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
