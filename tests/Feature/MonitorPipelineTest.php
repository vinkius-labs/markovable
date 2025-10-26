<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use Illuminate\Support\Facades\Event;
use VinkiusLabs\Markovable\Events\AnomalyDetected;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Test\TestCase;

class MonitorPipelineTest extends TestCase
{
    public function test_monitor_pipeline_runs_detectors_and_prepares_alerts(): void
    {
        Event::fake([AnomalyDetected::class]);

        $baseline = [
            '/home /pricing',
            '/home /pricing',
            '/home /support',
        ];

        Markovable::train($baseline)->cache('monitor-baseline');

        $current = [
            '/home /admin /billing',
            '/home /admin /billing',
            '/home /admin /billing',
            '/home /admin /billing',
        ];

        $summary = Markovable::train($current)
            ->monitor('monitor-baseline')
            ->detectAnomalies([
                'unseenSequences' => ['threshold' => 0.1, 'minLength' => 2],
                'drift' => ['drift_threshold' => 0.1],
            ])
            ->alerts([
                'critical' => ['email' => 'ops@example.com'],
                'high' => ['slack' => '#ops'],
            ])
            ->checkInterval('10 minutes')
            ->start();

        $this->assertSame('10 minutes', $summary['interval']);
        $this->assertNotEmpty($summary['anomalies']);
        $this->assertArrayHasKey('alerts', $summary);
        $this->assertArrayHasKey('critical', $summary['alerts']);

        Event::assertDispatched(AnomalyDetected::class);
    }
}
