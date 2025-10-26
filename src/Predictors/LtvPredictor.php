<?php

namespace VinkiusLabs\Markovable\Predictors;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use VinkiusLabs\Markovable\Contracts\Predictor;
use VinkiusLabs\Markovable\Events\HighLtvCustomerIdentified;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\Support\Dataset;
use VinkiusLabs\Markovable\Support\Statistics;
use function data_get;
use function event;
use function collect;

class LtvPredictor implements Predictor
{
    private MarkovableChain $baseline;

    /** @var array<int, array<string, mixed>> */
    private array $dataset;

    private int $firstDays = 7;

    /** @var array<int, string> */
    private array $segments = ['high', 'medium', 'at_risk'];

    private bool $includeHistoricalComparison = false;

    public function __construct(MarkovableChain $baseline, array $dataset = [])
    {
        $this->baseline = $baseline;
        $this->dataset = $dataset ?: $baseline->getRecords();
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $records
     */
    public function dataset(iterable $records): self
    {
        $this->dataset = Dataset::normalize($records);
        $this->baseline->withRecords($this->dataset);

        return $this;
    }

    public function fromFirstDays(int $days): self
    {
        $this->firstDays = max(1, $days);

        return $this;
    }

    /**
     * @param  array<int, string>  $segments
     */
    public function segments(array $segments): self
    {
        $segments = array_values(array_filter($segments, static fn ($segment) => is_string($segment) && $segment !== ''));

        if (! empty($segments)) {
            $this->segments = $segments;
        }

        return $this;
    }

    public function includeHistoricalComparison(bool $flag = true): self
    {
        $this->includeHistoricalComparison = $flag;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $customers = $this->resolveCustomers();

        if (empty($customers)) {
            return [];
        }

        $predictions = [];
        $values = [];

        foreach ($customers as $customer) {
            $ltv = $this->predictLtv($customer);

            $predictions[] = [
                'customer' => $customer,
                'ltv' => $ltv,
            ];

            $values[] = $ltv['predicted_value'];
        }

        $thresholds = $this->resolveThresholds($values);

        foreach ($predictions as $index => $prediction) {
            $segment = $this->assignSegment($prediction['ltv']['predicted_value'], $thresholds);
            $predictions[$index]['segment'] = $segment;
        }

        $segmentStats = $this->summarizeSegments($predictions);

        $results = [];

        foreach ($predictions as $prediction) {
            $customer = $prediction['customer'];
            $ltv = $prediction['ltv'];
            $segment = $prediction['segment'];

            $result = [
                'customer_id' => data_get($customer, 'customer_id', data_get($customer, 'id')),
                'email' => data_get($customer, 'email'),
                'ltv_score' => round($ltv['predicted_value'], 2),
                'ltv_segment' => $segment,
                'confidence' => round($ltv['confidence'], 2),
                'prediction_basis' => $ltv['basis'],
                'segment_characteristics' => $segmentStats[$segment]['characteristics'] ?? $this->defaultSegmentCharacteristics($segment),
                'recommended_actions' => $this->generateLtvOptimizationActions($customer, $segment),
            ];

            if ($this->includeHistoricalComparison) {
                $result['cohort_comparison'] = $this->compareToCohort($ltv['predicted_value'], $values);
            }

            if ($segment === ($this->segments[0] ?? 'high')) {
                event(new HighLtvCustomerIdentified($customer, $ltv['predicted_value'], $ltv['confidence'], $result['segment_characteristics']));
            }

            $results[] = $result;
        }

        usort($results, static fn ($left, $right) => $right['ltv_score'] <=> $left['ltv_score']);

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCustomers(): array
    {
        $dataset = $this->dataset ?: $this->baseline->getRecords();

        $records = array_filter($dataset, function (array $record) {
            $days = (int) data_get($record, 'days_since_signup', data_get($record, 'days_to_first_purchase', $this->firstDays));

            if ($days === 0) {
                $createdAt = data_get($record, 'created_at');

                if ($createdAt) {
                    try {
                        $days = Carbon::now()->diffInDays(Carbon::parse($createdAt));
                    } catch (\Throwable $exception) {
                        $days = $this->firstDays;
                    }
                }
            }

            return $days <= $this->firstDays || $this->firstDays === 0;
        });

        return array_values($records);
    }

    /**
     * @return array{predicted_value: float, confidence: float, basis: array<string, mixed>}
     */
    private function predictLtv(array $customer): array
    {
        $daysToPurchase = (int) data_get($customer, 'days_to_first_purchase', $this->firstDays);
        $initialValue = (float) data_get($customer, 'first_purchase_value', data_get($customer, 'initial_purchase_value', 100));
        $engagement = $this->clamp((float) data_get($customer, 'engagement_score', 0.6));
        $featuresUsed = (float) data_get($customer, 'features_used', data_get($customer, 'feature_usage_breadth', 6));
        $referralLikelihood = $this->clamp((float) data_get($customer, 'referral_probability', 0.2));
        $averageOrderValue = (float) data_get($customer, 'average_order_value', max($initialValue, 80));

        $baseLtv = max(120.0, $averageOrderValue * (1 + $engagement));
        $earlyAdoptionMultiplier = $daysToPurchase <= $this->firstDays ? 1.8 : max(1.0, 1.2 - ($daysToPurchase / 90));
        $featureMultiplier = 1 + min($featuresUsed / 12, 1.5);
        $engagementMultiplier = 1 + ($engagement * 0.8);
        $referralMultiplier = 1 + ($referralLikelihood * 0.5);

        $predictedValue = $baseLtv * $earlyAdoptionMultiplier * $featureMultiplier * $engagementMultiplier * $referralMultiplier;
        $confidence = $this->clamp(0.55 + ($engagement * 0.35) + min(0.1, $featureMultiplier / 20) - min(0.15, $daysToPurchase / 120));

        $basis = [
            'signup_to_first_purchase_days' => $daysToPurchase,
            'initial_purchase_value' => round($initialValue, 2),
            'engagement_score' => round($engagement, 2),
            'feature_usage_breadth' => (int) round($featuresUsed),
            'referral_likelihood' => round($referralLikelihood, 2),
        ];

        return [
            'predicted_value' => $predictedValue,
            'confidence' => $confidence,
            'basis' => $basis,
        ];
    }

    /**
     * @param  array<int, float>  $values
     * @return array<string, float>
     */
    private function resolveThresholds(array $values): array
    {
        if (empty($values)) {
            return [];
        }

        $count = max(1, count($this->segments));
        $step = 100 / $count;
        $thresholds = [];

        foreach ($this->segments as $index => $segment) {
            if ($index === $count - 1) {
                break;
            }

            $percentile = max(0.0, 100 - (($index + 1) * $step));
            $thresholds[$segment] = Statistics::percentile($values, $percentile);
        }

        return $thresholds;
    }

    private function assignSegment(float $value, array $thresholds): string
    {
        $fallback = Arr::last($this->segments) ?? 'default';

        foreach ($this->segments as $segment) {
            if (! isset($thresholds[$segment])) {
                continue;
            }

            if ($value >= $thresholds[$segment]) {
                return $segment;
            }
        }

        return $fallback;
    }

    /**
     * @param  array<int, array{customer: array<string, mixed>, ltv: array<string, mixed>, segment?: string}>  $predictions
     * @return array<string, array{characteristics: array<string, mixed>}>  $stats
     */
    private function summarizeSegments(array $predictions): array
    {
        $grouped = [];

        foreach ($predictions as $prediction) {
            $segment = $prediction['segment'] ?? end($this->segments);
            $grouped[$segment][] = $prediction;
        }

        $stats = [];

        foreach ($grouped as $segment => $items) {
            $ltvValues = array_map(static fn ($item) => $item['ltv']['predicted_value'], $items);

            $churnRates = array_map(function ($item) use ($segment) {
                $customer = $item['customer'];
                $churn = data_get($customer, 'churn_rate');

                if ($churn === null) {
                    $retention = data_get($customer, 'retention_rate');
                    $churn = $retention !== null ? 1 - $retention : $this->defaultChurnForSegment($segment);
                }

                return $churn;
            }, $items);

            $mrrValues = array_map(function ($item) use ($segment) {
                $customer = $item['customer'];

                return data_get($customer, 'mrr', data_get($customer, 'revenue.mrr', $this->defaultMrrForSegment($segment)));
            }, $items);

            $expansionProbabilities = array_map(function ($item) use ($segment) {
                $customer = $item['customer'];

                return data_get($customer, 'expansion_probability', data_get($customer, 'upsell_likelihood', $this->defaultExpansionForSegment($segment)));
            }, $items);

            $stats[$segment] = [
                'characteristics' => [
                    'typical_churn_rate' => round($this->clamp(Statistics::mean($churnRates)), 2),
                    'average_mrr' => round(Statistics::mean($mrrValues), 2),
                    'expansion_probability' => round($this->clamp(Statistics::mean($expansionProbabilities)), 2),
                    'average_ltv' => round(Statistics::mean($ltvValues), 2),
                ],
            ];
        }

        return $stats;
    }

    /**
     * @param  array<int, float>  $values
     * @return array<string, mixed>
     */
    private function compareToCohort(float $value, array $values): array
    {
        $collection = collect($values)->sort()->values();
        $index = $collection->search(function ($item) use ($value) {
            return $item >= $value;
        });

        if ($index === false) {
            $index = $collection->count() - 1;
        }

        $percentile = $collection->count() > 1 ? $index / ($collection->count() - 1) : 1.0;
        $average = Statistics::mean($collection);
        $median = Statistics::median($collection);

        return [
            'ltv_percentile' => round($this->clamp($percentile), 2),
            'vs_average_segment' => $this->formatCurrencyDifference($value - $average),
            'vs_median_segment' => $this->formatCurrencyDifference($value - $median),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generateLtvOptimizationActions(array $customer, string $segment): array
    {
        $actions = [];

        if ($segment === ($this->segments[0] ?? 'high')) {
            $actions[] = [
                'action' => 'upsell_premium_tier',
                'current_plan' => data_get($customer, 'current_plan'),
                'recommended_plan' => 'professional',
                'estimated_ltv_increase' => round(data_get($customer, 'estimated_upsell_value', 1200), 2),
                'success_probability' => $this->clamp((float) data_get($customer, 'upsell_success_probability', 0.48)),
            ];
        }

        if ($segment !== (Arr::last($this->segments) ?? $segment)) {
            $actions[] = [
                'action' => 'invite_to_success_program',
                'program' => 'executive_briefing',
                'estimated_ltv_increase' => round(max(300, data_get($customer, 'program_value', 450)), 2),
                'success_probability' => $this->clamp((float) data_get($customer, 'program_success_probability', 0.36)),
            ];
        }

        return $actions;
    }

    private function defaultSegmentCharacteristics(string $segment): array
    {
        return [
            'typical_churn_rate' => match ($segment) {
                'high' => 0.08,
                'medium' => 0.18,
                default => 0.31,
            },
            'average_mrr' => match ($segment) {
                'high' => 89.0,
                'medium' => 54.0,
                default => 32.0,
            },
            'expansion_probability' => match ($segment) {
                'high' => 0.56,
                'medium' => 0.32,
                default => 0.18,
            },
            'average_ltv' => 0.0,
        ];
    }

    private function defaultChurnForSegment(string $segment): float
    {
        return $this->defaultSegmentCharacteristics($segment)['typical_churn_rate'];
    }

    private function defaultMrrForSegment(string $segment): float
    {
        return $this->defaultSegmentCharacteristics($segment)['average_mrr'];
    }

    private function defaultExpansionForSegment(string $segment): float
    {
        return $this->defaultSegmentCharacteristics($segment)['expansion_probability'];
    }

    private function formatCurrencyDifference(float $value): string
    {
        $absolute = round(abs($value));
        $prefix = $value >= 0 ? '+' : '-';

        return sprintf('%s$%s', $prefix, number_format($absolute, 0));
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
