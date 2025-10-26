<?php

namespace VinkiusLabs\Markovable\Predictors;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use VinkiusLabs\Markovable\Contracts\Predictor;
use VinkiusLabs\Markovable\Events\ChurnRiskIdentified;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\Support\Dataset;
use function data_get;
use function event;

class ChurnScorer implements Predictor
{
    private MarkovableChain $baseline;

    /** @var array<int, array<string, mixed>> */
    private array $dataset;

    /** @var array<int, string> */
    private array $features = [];

    /** @var array<string, float> */
    private array $thresholds = [
        'high' => 0.70,
        'medium' => 0.40,
    ];

    private bool $includeRecommendations = false;

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

        return $this;
    }

    /**
     * @param  array<int, string>  $features
     */
    public function features(array $features): self
    {
        $this->features = array_values(array_filter($features, static fn ($feature) => is_string($feature)));

        return $this;
    }

    public function riskThreshold(string $level, float $score): self
    {
        $this->thresholds[$level] = max(0.0, min(1.0, $score));

        return $this;
    }

    public function includeRecommendations(bool $flag = true): self
    {
        $this->includeRecommendations = $flag;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $customers = $this->resolveCustomers();

        $results = [];

        foreach ($customers as $customer) {
            $scorePayload = $this->calculateChurnScore($customer);
            $score = $scorePayload['score'];
            $riskLevel = $this->determineRiskLevel($score);

            $result = [
                'customer_id' => data_get($customer, 'customer_id', data_get($customer, 'id')),
                'email' => data_get($customer, 'email'),
                'churn_score' => round($score, 2),
                'risk_level' => $riskLevel,
                'days_inactive' => $scorePayload['days_inactive'],
                'usage_trend' => $scorePayload['usage_trend'],
                'risk_factors' => $this->formatRiskFactors($scorePayload['contributions'], $score),
            ];

            if ($this->includeRecommendations) {
                $result['recommended_actions'] = $this->generateChurnMitigationActions($customer, $riskLevel, $score);
            }

            if ($riskLevel === 'high') {
                event(new ChurnRiskIdentified($customer, $score, $riskLevel, $result['recommended_actions'] ?? []));
            }

            $results[] = $result;
        }

        usort($results, static fn ($left, $right) => $right['churn_score'] <=> $left['churn_score']);

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCustomers(): array
    {
        if (! empty($this->dataset)) {
            return $this->filterDataset($this->dataset);
        }

        $fallback = [];

        foreach ($this->baseline->getSequenceFrequencies() as $sequence => $count) {
            $fallback[] = [
                'customer_id' => substr(hash('sha256', $sequence), 0, 12),
                'email' => null,
                'days_since_last_login' => max(0, 30 - $count),
                'usage_trend' => $count > 1 ? 'growing' : 'declining',
                'feature_adoption_rate' => min(1.0, strlen($sequence) / 120),
                'support_tickets' => max(0, (int) round($count / 2)),
            ];
        }

        return $fallback;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function filterDataset(array $records): array
    {
        if (empty($this->features)) {
            return $records;
        }

        return array_values(array_filter($records, function (array $record) {
            foreach ($this->features as $feature) {
                if (! Arr::has($record, $feature)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  array<string, float>  $contributions
     * @return array<string, float>
     */
    private function formatRiskFactors(array $contributions, float $score): array
    {
        if ($score <= 0) {
            return [];
        }

        $factors = [];

        foreach ($contributions as $key => $value) {
            if ($key === 'days_inactive') {
                continue;
            }

            $label = match ($key) {
                'inactivity' => 'no_login_'.$contributions['days_inactive'].'_days',
                'usage' => 'usage_trend_decline',
                'adoption' => 'feature_adoption_decline',
                'support' => 'support_contact_increase',
                default => $key,
            };

            $factors[$label] = round($value / $score, 2);
        }

        return $factors;
    }

    /**
     * @return array{score: float, usage_trend: string, days_inactive: int, contributions: array<string, float|int>}
     */
    private function calculateChurnScore(array $customer): array
    {
        $weights = [
            'inactivity' => 0.40,
            'usage' => 0.30,
            'adoption' => 0.20,
            'support' => 0.10,
        ];

        $daysInactive = $this->getDaysInactive($customer);
        $usageTrend = $this->analyzeUsageTrend($customer);
        $featureAdoption = $this->getFeatureAdoptionRate($customer);
        $supportBurden = $this->getSupportTicketTrend($customer);

        $usageScores = [
            'declining' => 1.0,
            'flat' => 0.5,
            'growing' => 0.0,
        ];

        $contributions = [
            'days_inactive' => $daysInactive,
            'inactivity' => $weights['inactivity'] * min($daysInactive / 60, 1.0),
            'usage' => $weights['usage'] * ($usageScores[$usageTrend] ?? 0.5),
            'adoption' => $weights['adoption'] * (1.0 - min(max($featureAdoption, 0.0), 1.0)),
            'support' => $weights['support'] * min($supportBurden / 5, 1.0),
        ];

        $score = $contributions['inactivity'] + $contributions['usage'] + $contributions['adoption'] + $contributions['support'];

        return [
            'score' => max(0.0, min(1.0, $score)),
            'usage_trend' => $usageTrend,
            'days_inactive' => $daysInactive,
            'contributions' => $contributions,
        ];
    }

    private function determineRiskLevel(float $score): string
    {
        arsort($this->thresholds);

        foreach ($this->thresholds as $level => $threshold) {
            if ($score >= $threshold) {
                return $level;
            }
        }

        return 'low';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generateChurnMitigationActions(array $customer, string $riskLevel, float $score): array
    {
        $actions = [];
        $discount = $riskLevel === 'high' ? 20 : 15;

        $actions[] = [
            'action' => 'send_engagement_email',
            'template' => 'win_back_special_offer',
            'offer_discount' => $discount,
            'expected_recovery_rate' => $riskLevel === 'high' ? round(min(0.6, $score + 0.25), 2) : round(min(0.5, $score + 0.18), 2),
        ];

        if ($riskLevel === 'high') {
            $actions[] = [
                'action' => 'priority_support_outreach',
                'message' => 'Personal check-in from success team',
                'expected_recovery_rate' => round(min(0.7, $score + 0.30), 2),
            ];
        }

        if ($riskLevel !== 'low' && (int) $this->getSupportTicketTrend($customer) === 0) {
            $actions[] = [
                'action' => 'schedule_success_call',
                'message' => 'Offer a 20-minute strategy call',
                'expected_recovery_rate' => round(min(0.55, $score + 0.22), 2),
            ];
        }

        return $actions;
    }

    private function getDaysInactive(array $customer): int
    {
        $days = (int) data_get($customer, 'days_since_last_login', 0);

        if ($days === 0) {
            $lastLogin = data_get($customer, 'last_login_at') ?? data_get($customer, 'activity.last_login_at');

            if ($lastLogin) {
                try {
                    $days = Carbon::parse($lastLogin)->diffInDays(now());
                } catch (\Throwable $exception) {
                    $days = 0;
                }
            }
        }

        return max(0, $days);
    }

    private function analyzeUsageTrend(array $customer): string
    {
        $trend = strtolower((string) data_get($customer, 'usage_trend', ''));

        if ($trend !== '') {
            return $trend;
        }

        $trendScore = (float) data_get($customer, 'usage_trend_score', data_get($customer, 'analytics.usage_trend_score', 0.5));

        return match (true) {
            $trendScore >= 0.66 => 'growing',
            $trendScore <= 0.33 => 'declining',
            default => 'flat',
        };
    }

    private function getFeatureAdoptionRate(array $customer): float
    {
        $rate = data_get($customer, 'feature_adoption_rate');

        if ($rate === null) {
            $breadth = (float) data_get($customer, 'feature_usage_breadth', data_get($customer, 'analytics.feature_usage_breadth', 0));
            $availableFeatures = (float) data_get($customer, 'total_features', 12);
            $rate = $availableFeatures > 0 ? min(1.0, $breadth / $availableFeatures) : 0.0;
        }

        return max(0.0, min(1.0, (float) $rate));
    }

    private function getSupportTicketTrend(array $customer): int
    {
        $tickets = data_get($customer, 'support_tickets');

        if ($tickets === null) {
            $tickets = data_get($customer, 'support_ticket_count', data_get($customer, 'support.open_tickets', 0));
        }

        return max(0, (int) $tickets);
    }
}
