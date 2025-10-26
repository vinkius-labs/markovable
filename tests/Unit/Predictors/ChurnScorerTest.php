<?php

namespace VinkiusLabs\Markovable\Test\Unit\Predictors;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use VinkiusLabs\Markovable\Events\ChurnRiskIdentified;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Predictors\ChurnScorer;
use VinkiusLabs\Markovable\Support\Dataset;
use VinkiusLabs\Markovable\Test\TestCase;

class ChurnScorerTest extends TestCase
{
    public function test_calculates_risk_score_and_dispatches_event(): void
    {
        Event::fake();

        $records = Dataset::normalize([
            [
                'customer_id' => 'cust-high',
                'email' => 'vip@example.com',
                'days_since_last_login' => 0,
                'activity' => ['last_login_at' => Carbon::now()->subDays(21)->toAtomString()],
                'usage_trend' => null,
                'usage_trend_score' => 0.2,
                'analytics' => ['feature_usage_breadth' => 3],
                'total_features' => 12,
                'support' => ['open_tickets' => 4],
                'referral_probability' => 0.05,
                'feature_adoption_rate' => 0.15,
            ],
            [
                'customer_id' => 'cust-medium',
                'email' => 'steady@example.com',
                'days_since_last_login' => 9,
                'usage_trend' => 'flat',
                'feature_adoption_rate' => 0.48,
                'support_tickets' => 0,
                'retention_rate' => 0.84,
            ],
            [
                'customer_id' => 'cust-missing-feature',
                'days_since_last_login' => 12,
                'usage_trend' => 'growing',
                'support_tickets' => 1,
            ],
        ]);

        $baseline = $this->trainBaseline($records, 'churn-scorer-high');

        $scores = (new ChurnScorer($baseline, $records))
            ->features(['feature_adoption_rate'])
            ->includeRecommendations()
            ->riskThreshold('high', 0.45)
            ->riskThreshold('medium', 0.30)
            ->get();

        $this->assertCount(2, $scores);
        $byCustomer = Arr::keyBy($scores, 'customer_id');

        $this->assertSame('high', $byCustomer['cust-high']['risk_level']);
        $this->assertArrayHasKey('recommended_actions', $byCustomer['cust-high']);
        $this->assertGreaterThan(0, $byCustomer['cust-high']['risk_factors']['usage_trend_decline']);

        $this->assertSame('medium', $byCustomer['cust-medium']['risk_level']);
        $this->assertTrue(collect($byCustomer['cust-medium']['recommended_actions'])
            ->contains(fn ($action) => $action['action'] === 'schedule_success_call'));

        Event::assertDispatched(ChurnRiskIdentified::class, static function ($event) {
            return $event->riskLevel === 'high'
                && $event->customer['customer_id'] === 'cust-high';
        });
    }

    public function test_filters_records_and_falls_back_to_baseline_frequencies(): void
    {
        $baseline = app(MarkovableManager::class)
            ->chain('text')
            ->order(1)
            ->train(['retain upgrade renew']);

        $this->assertNotEmpty($baseline->getSequenceFrequencies());

        $scores = (new ChurnScorer($baseline, []))->get();

        $this->assertNotEmpty($scores, 'Fallback scores should be generated from sequence frequencies.');
        $this->assertArrayHasKey('customer_id', $scores[0]);
        $this->assertArrayHasKey('churn_score', $scores[0]);
    }

    private function trainBaseline(array $records, string $key)
    {
        $manager = app(MarkovableManager::class);

        return $manager->chain('analytics')
            ->order(1)
            ->cache($key)
            ->train($records);
    }
}
