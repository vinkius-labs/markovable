<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use Illuminate\Support\Facades\Event;
use VinkiusLabs\Markovable\Events\ClusterShifted;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Test\TestCase;

class ClusterAnalyzerTest extends TestCase
{
    public function test_cluster_analysis_builds_profiles_and_dispatches_shift_event(): void
    {
        Event::fake([ClusterShifted::class]);

        $baseline = [
            '/home /products /checkout',
            '/home /products /checkout',
            '/home /blog /articles',
            '/home /blog /articles',
            '/home /support /contact',
        ];

        Markovable::train($baseline)->cache('cluster-baseline');

        $current = [
            '/home /products /checkout',
            '/home /products /checkout',
            '/home /products /checkout /upsell',
            '/landing /trial /checkout',
            '/landing /trial /checkout',
            '/landing /trial /checkout',
            '/blog /newsletter',
        ];

        $profiles = Markovable::train($current)
            ->cluster('cluster-baseline')
            ->algorithm('kmeans')
            ->numberOfClusters(3)
            ->features(['frequency', 'length'])
            ->analyze();

        $this->assertNotEmpty($profiles);

        foreach ($profiles as $profile) {
            $this->assertArrayHasKey('cluster_id', $profile);
            $this->assertArrayHasKey('size', $profile);
            $this->assertArrayHasKey('percentage', $profile);
            $this->assertArrayHasKey('profile', $profile);
            $this->assertArrayHasKey('characteristics', $profile);
        }

        $dbscanProfiles = Markovable::train($current)
            ->cluster('cluster-baseline')
            ->algorithm('dbscan')
            ->analyze();

        $this->assertIsArray($dbscanProfiles);

        Event::assertDispatched(ClusterShifted::class);
    }
}
