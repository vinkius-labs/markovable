<?php

namespace VinkiusLabs\Markovable\Test\Feature\Console;

use Illuminate\Support\Carbon;
use VinkiusLabs\Markovable\Models\MarkovableSchedule;
use VinkiusLabs\Markovable\Test\TestCase;

class ScheduleCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        MarkovableSchedule::query()->delete();

        parent::tearDown();
    }

    public function test_schedule_command_creates_schedule(): void
    {
    $this->withFrozenTime(function (): void {
            $this->artisan('markovable:schedule', [
                'action' => 'train',
                '--model' => 'user-navigation',
                '--frequency' => 'daily',
                '--time' => '02:00',
                '--callback' => 'markovable:report user-navigation --email',
                '--enable' => true,
            ])->assertSuccessful();
        });

        $schedule = MarkovableSchedule::first();

        $this->assertNotNull($schedule);
        $this->assertSame('train', $schedule->action);
        $this->assertSame('user-navigation', $schedule->model_key);
        $this->assertTrue($schedule->enabled);
        $this->assertSame('daily', $schedule->frequency);
        $this->assertNotNull($schedule->next_run_at);
    }

    public function test_schedule_command_lists_schedules(): void
    {
        MarkovableSchedule::create([
            'uuid' => 'test-uuid',
            'action' => 'report',
            'model_key' => 'analytics',
            'frequency' => 'hourly',
            'time' => '15',
            'enabled' => false,
        ]);

        $this->artisan('markovable:schedule', [
            '--list' => true,
        ])->expectsTable(
            ['ID', 'Action', 'Model', 'Frequency', 'Time', 'Next Run', 'Status'],
            [[
                'test-uuid',
                'report',
                'analytics',
                'hourly',
                '15',
                MarkovableSchedule::first()->next_run_at?->toDateTimeString() ?? 'n/a',
                '❌ Inactive',
            ]]
        )->assertSuccessful();
    }

    public function test_schedule_command_allows_disabling_schedule(): void
    {
        $this->artisan('markovable:schedule', [
            'action' => 'detect',
            '--model' => 'churn',
            '--frequency' => 'hourly',
            '--time' => '30',
            '--disable' => true,
        ])->assertSuccessful();

        $schedule = MarkovableSchedule::where('action', 'detect')->first();

        $this->assertNotNull($schedule);
        $this->assertFalse($schedule->enabled);
    }

    private function withFrozenTime(callable $callback): void
    {
        $now = Carbon::create(2025, 10, 26, 1, 0, 0);

        Carbon::setTestNow($now);

        try {
            $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
