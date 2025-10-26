<?php

namespace VinkiusLabs\Markovable\Commands;

use Illuminate\Console\Command;
use VinkiusLabs\Markovable\Models\MarkovableSchedule;

class ScheduleCommand extends Command
{
    protected $signature = 'markovable:schedule
        {action? : The action to schedule (train, detect, report, snapshot)}
        {--model= : Model key to associate with the schedule}
        {--frequency=daily : Frequency (daily, hourly, weekly, monthly, cron)}
        {--time=02:00 : Execution time or cron expression}
        {--callback= : Command or webhook executed after completion}
        {--enable : Enable the schedule}
        {--disable : Disable the schedule}
        {--list : List all schedules}';

    protected $description = 'Manage automated Markovable schedules.';

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->listSchedules();

            return Command::SUCCESS;
        }

        $action = $this->argument('action');

        if (! $action) {
            $this->error('Provide an action or use --list to view schedules.');

            return Command::FAILURE;
        }

        $modelKey = $this->option('model');
        $frequency = strtolower((string) $this->option('frequency'));
        $time = $this->option('time');
        $callback = $this->option('callback');
        $enabled = $this->option('disable') ? false : true;

        if ($this->option('enable')) {
            $enabled = true;
        }

        $schedule = MarkovableSchedule::updateOrCreate(
            [
                'action' => $action,
                'model_key' => $modelKey,
            ],
            [
                'frequency' => $frequency,
                'time' => $this->normalizeTimeOption($frequency, $time),
                'cron_expression' => $frequency === 'cron' ? $time : null,
                'callback' => $callback,
                'enabled' => $enabled,
            ]
        );

        $this->info('📅 Creating schedule: '.$action);

        if ($modelKey) {
            $this->info('🎯 Model: '.$modelKey);
        }

        $this->info('⏰ Frequency: '.$frequency.(($time && $frequency !== 'cron') ? ' at '.$time : ''));

        if ($callback) {
            $this->info('🔔 Callback: '.$callback);
        }

        $this->info($schedule->enabled ? '✅ Schedule enabled' : '❌ Schedule disabled');
        $this->newLine();
        $this->info('Schedule ID: '.$schedule->uuid);
        $this->info('Next run: '.($schedule->next_run_at?->toDateTimeString() ?? 'n/a'));
        $this->info('Status: '.($schedule->enabled ? 'active' : 'inactive'));
        $this->info('Registered in: app/Console/Kernel.php');

        return Command::SUCCESS;
    }

    private function normalizeTimeOption(string $frequency, ?string $time): ?string
    {
        if ($frequency === 'cron') {
            return null;
        }

        return $time;
    }

    private function listSchedules(): void
    {
        $schedules = MarkovableSchedule::orderBy('created_at', 'desc')->get();

        $this->table([
            'ID',
            'Action',
            'Model',
            'Frequency',
            'Time',
            'Next Run',
            'Status',
        ], $schedules->map(static function (MarkovableSchedule $schedule) {
            return [
                $schedule->uuid,
                $schedule->action,
                $schedule->model_key ?? '—',
                $schedule->frequency,
                $schedule->time,
                $schedule->next_run_at?->toDateTimeString() ?? 'n/a',
                $schedule->enabled ? '✅ Active' : '❌ Inactive',
            ];
        })->toArray());
    }
}
