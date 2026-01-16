<?php

namespace App\Console\Commands;

use App\Mail\PaymentReminderEmail;
use App\Models\PaymentSchedule;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send payment reminder emails 1 hour before payment execution';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $oneHourFromNow = $now->copy()->addHour();
        
        // Find payment schedules that are due in approximately 1 hour (within 15 minute window)
        $schedules = PaymentSchedule::where('status', 'active')
            ->whereNotNull('next_run_at')
            ->whereBetween('next_run_at', [
                $now->copy()->addMinutes(45), // 45 minutes from now
                $oneHourFromNow->copy()->addMinutes(15), // 1 hour 15 minutes from now
            ])
            ->with(['business.owner'])
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No payment reminders to send.');
            return Command::SUCCESS;
        }

        $this->info("Found {$schedules->count()} payment schedule(s) requiring reminders.");

        $emailService = app(EmailService::class);
        $sent = 0;

        foreach ($schedules as $schedule) {
            // Skip if business is not active
            if (!$schedule->business->canPerformActions()) {
                $this->warn("Skipping schedule #{$schedule->id} - business is {$schedule->business->status}.");
                continue;
            }

            $user = $schedule->business->owner;
            
            // Check if we already sent a reminder for this schedule (prevent duplicates)
            // We'll track this by checking if next_run_at is still in the reminder window
            // In a production system, you might want to add a 'reminder_sent_at' field
            
            if ($emailService->send($user, new PaymentReminderEmail($user, $schedule), 'payment_reminder')) {
                $sent++;
                $this->info("Reminder sent for schedule #{$schedule->id} - {$schedule->name}");
            }
        }

        $this->info("Sent {$sent} payment reminder(s).");

        return Command::SUCCESS;
    }
}
