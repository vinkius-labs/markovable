<?php

namespace VinkiusLabs\Markovable\Test\Unit\Predictors;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Events\HighLtvCustomerIdentified;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Predictors\LtvPredictor;
use VinkiusLabs\Markovable\Support\Dataset;
use VinkiusLabs\Markovable\Test\TestCase;

class LtvPredictorTest extends TestCase
{
    public function test_predicts_ltv_segments_and_dispatches_event(): void
    {
        Event::fake();

        $raw = [
            [
                'customer_id' => 'gold-1',
                'email' => 'vip@example.com',
                'days_since_signup' => 4,
                'first_purchase_value' => 520,
                'engagement_score' => 0.76,
                'features_used' => 9,
                'referral_probability' => 0.31,
                'average_order_value' => 240,
                'mrr' => 510,
                'retention_rate' => 0.94,
                'expansion_probability' => 0.52,
            ],
            [
                'customer_id' => 'silver-1',
                'email' => 'steady@example.com',
                'days_since_signup' => 5,
                'first_purchase_value' => 220,
                'engagement_score' => 0.42,
                'features_used' => 4,
                'referral_probability' => 0.12,
                'average_order_value' => 120,
                'mrr' => 210,
                'retention_rate' => 0.78,
                'expansion_probability' => 0.19,
                'churn_rate' => 0.21,
            ],
        ];

        $records = Dataset::normalize($raw);
        $baseline = $this->trainBaseline($records, 'ltv-predictor-'.Str::uuid());

        $predictor = (new LtvPredictor($baseline, $records))
            ->segments(['platinum', 'growth', 'retain'])
            ->includeHistoricalComparison();

        $predictions = $predictor->get();

        $this->assertCount(2, $predictions);
        $this->assertSame('platinum', $predictions[0]['ltv_segment']);
        $this->assertArrayHasKey('cohort_comparison', $predictions[0]);
        $this->assertGreaterThan($predictions[1]['ltv_score'], $predictions[0]['ltv_score']);
        $this->assertNotEmpty($predictions[0]['recommended_actions']);

        Event::assertDispatched(HighLtvCustomerIdentified::class, static function ($event) {
            return $event->customer['customer_id'] === 'gold-1';
        });
    }

    public function test_dataset_filtering_and_default_segments(): void
    {
        $raw = [
            [
                'customer_id' => 'late-1',
                'created_at' => Carbon::now()->subDays(2)->toAtomString(),
                'days_since_signup' => 0,
                'first_purchase_value' => 160,
                'engagement_score' => 0.30,
                'feature_usage_breadth' => 3,
                'total_features' => 12,
                'referral_probability' => 0.05,
                'average_order_value' => 80,
                'program_value' => 320,
            ],
            [
                'customer_id' => 'late-2',
                'days_since_signup' => 14,
                'first_purchase_value' => 90,
                'engagement_score' => 0.22,
                'features_used' => 2,
                'referral_probability' => 0.03,
                'average_order_value' => 60,
            ],
        ];

        $records = Dataset::normalize($raw);
        $baseline = $this->trainBaseline($records, 'ltv-filtering');

        $predictor = (new LtvPredictor($baseline, $records))
            ->fromFirstDays(5);

        $predictions = $predictor->get();

        $this->assertCount(1, $predictions, 'Records outside the first-days window should be filtered out.');
        $this->assertSame('high', $predictions[0]['ltv_segment']);
        $this->assertSame(2, count($predictions[0]['recommended_actions']));
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
