<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use Illuminate\Support\Facades\Event;
use VinkiusLabs\Markovable\Events\AnomalyDetected;
use VinkiusLabs\Markovable\Events\PatternEmerged;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Models\AnomalyRecord;
use VinkiusLabs\Markovable\Test\TestCase;

class AnomalyDetectionTest extends TestCase
{
    public function test_pipeline_detects_unseen_sequences_and_emerging_patterns(): void
    {
        Event::fake([AnomalyDetected::class, PatternEmerged::class]);

        $baseline = [
            '/home /products /checkout',
            '/home /pricing',
            '/blog /home',
            '/home /products /checkout',
        ];

        Markovable::train($baseline)
            ->option('meta', [
                'pattern_history' => [
                    '/home /blog /pricing' => [2, 3, 4],
                ],
                'seasonality_profile' => [
                    'weekday' => [
                        'baseline' => ['weekday' => 0.7, 'weekend' => 0.3],
                        'current' => ['weekday' => 0.4, 'weekend' => 0.6],
                        'description' => 'Weekday behaviour changing',
                    ],
                ],
            ])
            ->cache('nav-baseline');

        $current = [
            '/home /admin /settings /users',
            '/home /admin /settings /users',
            '/home /blog /pricing',
            '/home /blog /pricing',
            '/home /blog /pricing',
            '/landing /pricing',
            '/landing /pricing /checkout',
        ];

        $detector = Markovable::train($current)
            ->detect('nav-baseline')
            ->unseenSequences()
            ->emergingPatterns()
            ->detectSeasonality()
            ->drift()
            ->threshold(0.15)
            ->minimumFrequency(2)
            ->metrics(['weekday'])
            ->seasonalityData([
                'weekday' => [
                    'baseline' => ['weekday' => 0.6, 'weekend' => 0.4],
                    'current' => ['weekday' => 0.3, 'weekend' => 0.7],
                    'description' => 'Weekend spike',
                ],
            ]);

        $results = $detector->get();

        $this->assertNotEmpty($results);
        $types = collect($results)->pluck('type')->unique()->all();

        $context = $detector->getContext();
        $this->assertSame('nav-baseline', $context->getBaselineKey());

        $this->assertContains('unseenSequence', $types);
        $this->assertContains('emergingPattern', $types);
        $this->assertContains('seasonality', $types);
        $this->assertContains('drift', $types);

        $this->assertGreaterThan(0, AnomalyRecord::count());

        Event::assertDispatched(AnomalyDetected::class);
        Event::assertDispatched(PatternEmerged::class);
    }

    public function test_detector_can_disable_persistence_and_events(): void
    {
        Event::fake([AnomalyDetected::class]);

        $baseline = [
            '/home /products',
            '/home /pricing',
        ];

        Markovable::train($baseline)->cache('no-persist-baseline');

        $current = [
            '/home /hidden /admin',
            '/home /hidden /admin',
        ];

        $results = Markovable::train($current)
            ->detect('no-persist-baseline')
            ->unseenSequences()
            ->comparedTo('previous-week')
            ->confidenceLevel(0.9)
            ->orderBy('probability')
            ->window('weekly')
            ->withoutPersistence()
            ->withoutEvents()
            ->threshold(0.2)
            ->get();

        $this->assertNotEmpty($results);
        $this->assertSame(0, AnomalyRecord::count());

        Event::assertNothingDispatched();
    }
}
