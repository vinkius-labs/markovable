<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use VinkiusLabs\Markovable\Events\ChurnRiskIdentified;
use VinkiusLabs\Markovable\Events\HighLtvCustomerIdentified;
use VinkiusLabs\Markovable\Events\RecommendationGenerated;
use VinkiusLabs\Markovable\Events\SeasonalForecastReady;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Test\TestCase;

class PredictiveIntelligenceTest extends TestCase
{
    public function test_predictive_builder_evaluates_churn_ltv_and_next_best_actions(): void
    {
        Event::fake();

        $dataset = $this->predictiveDataset();
        $manager = app(MarkovableManager::class);
        $baselineKey = 'analytics::predictive-baseline';

        $manager->chain('analytics')
            ->cache($baselineKey)
            ->train($dataset);

        $builder = $manager->predictive($baselineKey, [
            'churn' => ['include_recommendations' => true],
            'forecast' => [
                'metric' => 'monthly_recurring_revenue',
                'window' => 'weekly',
                'horizon' => 4,
                'components' => ['day_of_week'],
                'confidence' => 0.9,
            ],
        ])->dataset($dataset);

        $churn = $builder->churnScore()->get();
        $ltv = $builder->ltv()->includeHistoricalComparison()->get();
        $nba = $builder->nextBestAction()->includeContext(true)->topN(2)->get();
        $forecast = $builder->seasonalForecast()->get();

        $this->assertNotEmpty($churn);
        $this->assertArrayHasKey('recommended_actions', $churn[0]);
        $this->assertArrayHasKey('churn_score', $churn[0]);
        $this->assertGreaterThanOrEqual(0.0, $churn[0]['churn_score']);

        $this->assertNotEmpty($ltv);
        $this->assertArrayHasKey('ltv_score', $ltv[0]);
        $this->assertArrayHasKey('ltv_segment', $ltv[0]);
        $this->assertArrayHasKey('cohort_comparison', $ltv[0]);

        $this->assertNotEmpty($nba);
        $this->assertSame(2, count($nba));
        $this->assertArrayHasKey('recommended_action', $nba[0]);
        $this->assertArrayHasKey('context', $nba[0]);

        $this->assertNotEmpty($forecast['forecast']);
        $this->assertSame('weekly', $forecast['forecast_period']['window']);
        $this->assertCount(4, $forecast['forecast']);
        $this->assertArrayHasKey('forecasted_value', $forecast['forecast'][0]);

        Event::assertDispatched(ChurnRiskIdentified::class);
        Event::assertDispatched(HighLtvCustomerIdentified::class);
        Event::assertDispatched(RecommendationGenerated::class);
        Event::assertDispatched(SeasonalForecastReady::class);
    }

    public function test_predictive_builder_accessible_via_facade(): void
    {
        $dataset = $this->predictiveDataset();
        $baselineKey = 'analytics::predictive-facade';

        Markovable::chain('analytics')
            ->cache($baselineKey)
            ->train($dataset);

        $forecast = Markovable::predictive($baselineKey)
            ->dataset($dataset)
            ->seasonalForecast()
            ->metric('monthly_recurring_revenue')
            ->horizon(3)
            ->includeConfidenceIntervals(0.85)
            ->get();

        $this->assertNotEmpty($forecast['forecast']);
        $this->assertCount(3, $forecast['forecast']);
    }

    public function test_predictive_builder_handles_sparse_and_corrupted_inputs(): void
    {
        Event::fake();

        $manager = app(MarkovableManager::class);
        $baselineKey = 'analytics::predictive-chaos';

        $chaoticBaseline = $this->predictiveDataset();
        $chaoticBaseline[] = 'orphan-record';
        $chaoticBaseline[] = [
            'customer_id' => 'cust-chaotic',
            'journey_sequence' => 'signup drift abandoned_cart',
            'timestamp' => 'bad-date',
            'metrics' => [
                'history' => [
                    ['timestamp' => 'not-a-date', 'monthly_recurring_revenue' => ''],
                ],
            ],
        ];

        $manager->chain('analytics')
            ->cache($baselineKey)
            ->train($chaoticBaseline);

        $referenceRecord = $this->predictiveDataset()[0];

        $builder = $manager->predictive($baselineKey)
            ->dataset([
                [
                    'customer_id' => 'cust-chaos',
                    'support_tickets' => 2,
                    'journey_sequence' => null,
                    'metric_history' => [
                        ['timestamp' => 'invalid-format', 'value' => null],
                    ],
                ],
                ['unstructured' => 'value'],
                $referenceRecord,
            ]);

        $seasonal = $builder->seasonalForecast()
            ->metric('monthly_recurring_revenue')
            ->horizon(2)
            ->includeConfidenceIntervals(2.0)
            ->get();

        $this->assertCount(2, $seasonal['forecast']);
        $this->assertArrayHasKey('lower_bound_100', $seasonal['forecast'][0]);

        $nba = $builder->nextBestAction()->forCustomer('ghost')->get();
        $this->assertSame([], $nba);

        $churn = $builder->churnScore()
            ->includeRecommendations()
            ->get();

        $this->assertNotEmpty($churn);
        $this->assertArrayHasKey('customer_id', $churn[0]);
        $this->assertArrayHasKey('recommended_actions', $churn[0]);
    Event::assertNotDispatched(ChurnRiskIdentified::class);

        $ltv = $builder->ltv()->get();
        $this->assertNotEmpty($ltv);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function predictiveDataset(): array
    {
        $baseDate = Carbon::create(2024, 10, 1); 

        return [
            [
                'customer_id' => 'cust-100',
                'email' => 'vip@example.com',
                'days_since_last_login' => 28,
                'usage_trend' => 'declining',
                'feature_adoption_rate' => 0.32,
                'support_tickets' => 3,
                'days_since_signup' => 5,
                'first_purchase_value' => 480,
                'engagement_score' => 0.78,
                'features_used' => 9,
                'referral_probability' => 0.34,
                'average_order_value' => 210,
                'mrr' => 420,
                'retention_rate' => 0.92,
                'expansion_probability' => 0.51,
                'journey_sequence' => 'signup onboarding explore_reports automate_workflows',
                'last_action' => 'automate_workflows',
                'preferred_channel' => 'email',
                'monthly_recurring_revenue' => 8200,
                'timestamp' => $baseDate->copy()->addDays(5)->toDateString(),
                'metric_history' => $this->metricHistory($baseDate, 7200, 160),
            ],
            [
                'customer_id' => 'cust-101',
                'email' => 'growth@example.com',
                'days_since_last_login' => 6,
                'usage_trend' => 'growing',
                'feature_adoption_rate' => 0.68,
                'support_tickets' => 1,
                'days_since_signup' => 4,
                'first_purchase_value' => 320,
                'engagement_score' => 0.66,
                'features_used' => 7,
                'referral_probability' => 0.27,
                'average_order_value' => 165,
                'mrr' => 315,
                'retention_rate' => 0.88,
                'expansion_probability' => 0.38,
                'journey_sequence' => 'signup onboarding explore_reports invite_team',
                'last_action' => 'invite_team',
                'preferred_channel' => 'in_app',
                'monthly_recurring_revenue' => 6400,
                'timestamp' => $baseDate->copy()->addDays(6)->toDateString(),
                'metric_history' => $this->metricHistory($baseDate, 6000, 120),
            ],
            [
                'customer_id' => 'cust-102',
                'email' => 'steady@example.com',
                'days_since_last_login' => 12,
                'usage_trend' => 'flat',
                'feature_adoption_rate' => 0.44,
                'support_tickets' => 2,
                'days_since_signup' => 6,
                'first_purchase_value' => 260,
                'engagement_score' => 0.58,
                'features_used' => 5,
                'referral_probability' => 0.18,
                'average_order_value' => 140,
                'mrr' => 255,
                'retention_rate' => 0.81,
                'expansion_probability' => 0.29,
                'journey_sequence' => 'signup watch_demo explore_reports create_dashboard',
                'last_action' => 'create_dashboard',
                'preferred_channel' => 'email',
                'monthly_recurring_revenue' => 5400,
                'timestamp' => $baseDate->copy()->addDays(7)->toDateString(),
                'metric_history' => $this->metricHistory($baseDate, 5150, 90),
            ],
            [
                'customer_id' => 'cust-103',
                'email' => 'at-risk@example.com',
                'days_since_last_login' => 34,
                'usage_trend' => 'declining',
                'feature_adoption_rate' => 0.24,
                'support_tickets' => 4,
                'days_since_signup' => 5,
                'first_purchase_value' => 210,
                'engagement_score' => 0.42,
                'features_used' => 4,
                'referral_probability' => 0.12,
                'average_order_value' => 120,
                'mrr' => 180,
                'retention_rate' => 0.72,
                'expansion_probability' => 0.18,
                'journey_sequence' => 'signup onboarding explore_reports request_support',
                'last_action' => 'request_support',
                'preferred_channel' => 'email',
                'monthly_recurring_revenue' => 4100,
                'timestamp' => $baseDate->copy()->addDays(8)->toDateString(),
                'metric_history' => $this->metricHistory($baseDate, 3900, 60),
            ],
            [
                'customer_id' => 'cust-104',
                'email' => 'scaler@example.com',
                'days_since_last_login' => 4,
                'usage_trend' => 'growing',
                'feature_adoption_rate' => 0.72,
                'support_tickets' => 0,
                'days_since_signup' => 3,
                'first_purchase_value' => 360,
                'engagement_score' => 0.83,
                'features_used' => 10,
                'referral_probability' => 0.41,
                'average_order_value' => 240,
                'mrr' => 510,
                'retention_rate' => 0.95,
                'expansion_probability' => 0.57,
                'journey_sequence' => 'signup onboarding activate_automation invite_team',
                'last_action' => 'activate_automation',
                'preferred_channel' => 'in_app',
                'monthly_recurring_revenue' => 9100,
                'timestamp' => $baseDate->copy()->addDays(9)->toDateString(),
                'metric_history' => $this->metricHistory($baseDate, 8600, 190),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function metricHistory(Carbon $baseDate, float $start, float $increment): array
    {
        $history = [];

        for ($i = 0; $i < 8; $i++) {
            $history[] = [
                'timestamp' => $baseDate->copy()->addDays($i)->toDateString(),
                'monthly_recurring_revenue' => $start + ($increment * $i),
            ];
        }

        return $history;
    }
}
