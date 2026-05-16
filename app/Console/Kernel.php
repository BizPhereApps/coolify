<?php

namespace App\Console;

use App\Jobs\ApiTokenExpirationWarningJob;
use App\Jobs\CheckForUpdatesJob;
use App\Jobs\CheckHelperImageJob;
use App\Jobs\CheckTraefikVersionJob;
use App\Jobs\CleanupInstanceStuffsJob;
use App\Jobs\CleanupOrphanedPreviewContainersJob;
use App\Jobs\EndExpiredTrialsJob;
use App\Jobs\PullChangelog;
use App\Jobs\PullTemplatesFromCDN;
use App\Jobs\RegenerateSslCertJob;
use App\Jobs\ScheduledJobManager;
use App\Jobs\ServerManagerJob;
use App\Jobs\UpdateCoolifyJob;
use App\Models\InstanceSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    private Schedule $scheduleInstance;

    private InstanceSettings $settings;

    private string $updateCheckFrequency;

    private string $instanceTimezone;

    protected function schedule(Schedule $schedule): void
    {
        $this->scheduleInstance = $schedule;
        $this->settings = instanceSettings();
        $this->updateCheckFrequency = $this->settings->update_check_frequency ?: '0 * * * *';

        $this->instanceTimezone = $this->settings->instance_timezone ?: config('app.timezone');

        if (validate_timezone($this->instanceTimezone) === false) {
            $this->instanceTimezone = config('app.timezone');
        }

        // $this->scheduleInstance->job(new CleanupStaleMultiplexedConnections)->hourly();
        $this->scheduleInstance->command('cleanup:redis --clear-locks')->daily();
        $this->scheduleInstance->command('sanctum:prune-expired --hours=1')->hourly()->onOneServer();
        $this->scheduleInstance->job(new ApiTokenExpirationWarningJob)->hourly()->onOneServer();

        // Nolbase: downgrade expired Pro trials to Free at 00:05 UTC daily.
        if (isCloud()) {
            $this->scheduleInstance->job(new EndExpiredTrialsJob)->dailyAt('00:05')->onOneServer();

            // Trial-ends-soon reminders: daily at 09:00 UTC. The job's own
            // 2-3 day window ensures each subscription is notified once.
            $this->scheduleInstance->job(new \App\Jobs\SendTrialEndingSoonRemindersJob)
                ->dailyAt('09:00')->onOneServer();

            // Marketplace payouts: every Monday 09:00 Africa/Lagos sweep all
            // Developers with verified payout accounts and a pending balance
            // >= ₦5,000, send them a Paystack Transfer for the total.
            $this->scheduleInstance->job(new \App\Jobs\RunPayoutBatchJob)
                ->weeklyOn(\Illuminate\Console\Scheduling\Schedule::MONDAY, '09:00')
                ->timezone('Africa/Lagos')
                ->onOneServer();

            // Nolbase-managed monthly billing: 1st of every month at 02:00 UTC.
            // Idempotent via unique (team_id, billing_month).
            $this->scheduleInstance->job(new \App\Jobs\BillManagedServersJob)
                ->monthlyOn(1, '02:00')
                ->onOneServer();
        }

        if (isDev()) {
            // Instance Jobs
            $this->scheduleInstance->command('horizon:snapshot')->everyMinute();
            $this->scheduleInstance->job(new CleanupInstanceStuffsJob)->everyMinute()->onOneServer();
            $this->scheduleInstance->job(new CheckHelperImageJob)->everyTenMinutes()->onOneServer();

            // Server Jobs
            $this->scheduleInstance->job(new ServerManagerJob)->everyMinute()->onOneServer();

            // Scheduled Jobs (Backups & Tasks)
            $this->scheduleInstance->job(new ScheduledJobManager)->everyMinute()->onOneServer();

            $this->scheduleInstance->command('uploads:clear')->everyTwoMinutes();

        } else {
            // Instance Jobs
            $this->scheduleInstance->command('horizon:snapshot')->everyFiveMinutes();
            $this->scheduleInstance->command('cleanup:unreachable-servers')->daily()->onOneServer();

            $this->scheduleInstance->job(new PullTemplatesFromCDN)->cron($this->updateCheckFrequency)->timezone($this->instanceTimezone)->onOneServer();
            $this->scheduleInstance->job(new PullChangelog)->cron($this->updateCheckFrequency)->timezone($this->instanceTimezone)->onOneServer();

            $this->scheduleInstance->job(new CleanupInstanceStuffsJob)->everyTwoMinutes()->onOneServer();
            $this->scheduleUpdates();

            // Server Jobs
            $this->scheduleInstance->job(new ServerManagerJob)->everyMinute()->onOneServer();

            $this->pullImages();

            // Scheduled Jobs (Backups & Tasks)
            $this->scheduleInstance->job(new ScheduledJobManager)->everyMinute()->onOneServer();

            $this->scheduleInstance->job(new RegenerateSslCertJob)->twiceDaily();

            $this->scheduleInstance->job(new CheckTraefikVersionJob)->weekly()->sundays()->at('00:00')->timezone($this->instanceTimezone)->onOneServer();

            $this->scheduleInstance->command('cleanup:database --yes')->daily();
            $this->scheduleInstance->command('uploads:clear')->everyTwoMinutes();

            // Cleanup orphaned PR preview containers daily
            $this->scheduleInstance->job(new CleanupOrphanedPreviewContainersJob)->daily()->onOneServer();
        }
    }

    private function pullImages(): void
    {
        $this->scheduleInstance->job(new CheckHelperImageJob)
            ->cron($this->updateCheckFrequency)
            ->timezone($this->instanceTimezone)
            ->onOneServer();
    }

    private function scheduleUpdates(): void
    {
        $this->scheduleInstance->job(new CheckForUpdatesJob)
            ->cron($this->updateCheckFrequency)
            ->timezone($this->instanceTimezone)
            ->onOneServer();

        if ($this->settings->is_auto_update_enabled) {
            $autoUpdateFrequency = $this->settings->auto_update_frequency;
            $this->scheduleInstance->job(new UpdateCoolifyJob)
                ->cron($autoUpdateFrequency)
                ->timezone($this->instanceTimezone)
                ->onOneServer();
        }
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
