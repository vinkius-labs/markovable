<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Models\AnomalyRecord;
use VinkiusLabs\Markovable\Test\TestCase;

class DetectAnomaliesCommandTest extends TestCase
{
    public function test_command_detects_anomalies_from_file_input(): void
    {
        $baseline = [
            '/home /pricing',
            '/home /support',
        ];

        Markovable::train($baseline)->cache('command-baseline');

        $file = tempnam(sys_get_temp_dir(), 'markovable');
        file_put_contents($file, "/home /admin /billing\n/home /admin /billing");

        $this->artisan('markovable:detect-anomalies', [
            '--model' => 'command-baseline',
            '--input' => $file,
        ])->assertSuccessful();

        $this->assertGreaterThan(0, AnomalyRecord::count());

        @unlink($file);
    }
}
