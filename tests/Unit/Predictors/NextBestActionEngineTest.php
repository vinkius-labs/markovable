<?php

namespace VinkiusLabs\Markovable\Test\Unit\Predictors;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Events\RecommendationGenerated;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Predictors\NextBestActionEngine;
use VinkiusLabs\Markovable\Support\Dataset;
use VinkiusLabs\Markovable\Test\TestCase;

class NextBestActionEngineTest extends TestCase
{
    public function test_recommends_next_actions_from_baseline_predictions(): void
    {
        Event::fake();

        $baseline = $this->trainBaseline(['view_dashboard upgrade_plan view_feature_guide', 'upgrade_plan finalize_checkout'], 'nba-engine-'.Str::uuid());

        $records = Dataset::normalize([
            [
                'customer_id' => 'cust-100',
                'last_action' => 'upgrade_plan',
                'preferred_channel' => 'email',
                'engagement_score' => 0.65,
                'journey_sequence' => 'view_dashboard upgrade_plan',
                'usage_peak_hour' => 10,
                'usage_peak_day' => 'Tuesday',
            ],
        ]);

        $engine = (new NextBestActionEngine($baseline, $records))
            ->forCustomer('cust-100')
            ->includeContext(true)
            ->topN(2);

        $recommendations = $engine->get();

        $this->assertCount(2, $recommendations);
        $this->assertSame('cust-100', $recommendations[0]['customer_id']);
        $this->assertNotEmpty($recommendations[0]['context']);
        $this->assertNotEmpty(Arr::pluck($recommendations, 'expected_impact'));

        Event::assertDispatched(RecommendationGenerated::class, static function ($event) {
            return $event->customerId === 'cust-100';
        });
    }

    public function test_falls_back_to_library_when_no_predictions(): void
    {
        $baseline = $this->trainBaseline(['view_dashboard invite_team'], 'nba-engine-fallback');

        $records = Dataset::normalize([
            [
                'customer_id' => 'cust-200',
                'last_action' => 'non_existing',
                'preferred_channel' => 'in_app',
                'engagement_score' => 0.22,
            ],
        ]);

        $engine = (new NextBestActionEngine($baseline, $records))
            ->forCustomer('cust-200')
            ->excludeActions(['view_dashboard', 'view_feature_guide'])
            ->topN(1);

        $recommendations = $engine->get();

        $this->assertCount(1, $recommendations);
        $this->assertSame('schedule_success_call', $recommendations[0]['recommended_action']);
    }

    public function test_returns_empty_result_when_customer_missing(): void
    {
        $baseline = $this->trainBaseline(['view_dashboard invite_team'], 'nba-engine-missing');

        $records = Dataset::normalize([
            [
                'customer_id' => 'cust-present',
                'last_action' => 'view_dashboard',
            ],
        ]);

        $engine = (new NextBestActionEngine($baseline, $records))
            ->forCustomer('ghost-user')
            ->includeContext(true);

        $this->assertSame([], $engine->get());
    }

    private function trainBaseline(array $corpus, string $key)
    {
        $manager = app(MarkovableManager::class);

        return $manager->chain('text')
            ->order(1)
            ->cache($key)
            ->train($corpus);
    }
}
