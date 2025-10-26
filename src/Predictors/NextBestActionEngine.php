<?php

namespace VinkiusLabs\Markovable\Predictors;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Contracts\Predictor;
use VinkiusLabs\Markovable\Events\RecommendationGenerated;
use VinkiusLabs\Markovable\Handlers\ActionSuggestionHandler;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\Support\Dataset;
use VinkiusLabs\Markovable\Support\Statistics;
use function data_get;
use function event;
use function preg_split;

class NextBestActionEngine implements Predictor
{
    private MarkovableChain $baseline;

    /** @var array<int, array<string, mixed>> */
    private array $dataset;

    private ?string $customerId = null;

    private int $topN = 1;

    private bool $includeContext = false;

    /** @var array<int, string> */
    private array $excludeActions = [];

    private ActionSuggestionHandler $suggestionHandler;

    /**
     * Well known actions mapped to presentation metadata.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $actionLibrary = [
        'view_feature_guide' => [
            'action_type' => 'in_app_message',
            'primary_message' => 'Discover advanced reporting features',
            'description' => 'Learn how top users leverage analytics',
            'call_to_action' => 'View Guide',
            'target_url' => '/features/advanced-reporting',
            'preferred_channel' => 'in_app',
        ],
        'invite_to_webinar' => [
            'action_type' => 'email',
            'primary_message' => 'Exclusive Webinar: Growth Tactics',
            'description' => 'Reserve a seat for our upcoming success session.',
            'call_to_action' => 'RSVP Now',
            'target_url' => 'https://example.com/webinar',
            'preferred_channel' => 'email',
        ],
        'upgrade_plan' => [
            'action_type' => 'email',
            'primary_message' => 'Unlock premium automations',
            'description' => 'Upgrade to access AI playbooks and automation.',
            'call_to_action' => 'Compare Plans',
            'target_url' => '/pricing',
            'preferred_channel' => 'email',
        ],
        'schedule_success_call' => [
            'action_type' => 'calendar',
            'primary_message' => 'Plan a success session',
            'description' => 'Get strategic guidance from our experts.',
            'call_to_action' => 'Schedule Call',
            'target_url' => '/success/call',
            'preferred_channel' => 'email',
        ],
    ];

    public function __construct(MarkovableChain $baseline, array $dataset = [], ?ActionSuggestionHandler $suggestionHandler = null)
    {
        $this->baseline = $baseline;
        $this->dataset = $dataset ?: $baseline->getRecords();
        $this->suggestionHandler = $suggestionHandler ?? new ActionSuggestionHandler();
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

    public function forCustomer(string $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    public function topN(int $n): self
    {
        $this->topN = max(1, $n);

        return $this;
    }

    public function includeContext(bool $include): self
    {
        $this->includeContext = $include;

        return $this;
    }

    /**
     * @param  array<int, string>  $actions
     */
    public function excludeActions(array $actions): self
    {
        $this->excludeActions = array_values(array_filter($actions, static fn ($name) => is_string($name) && $name !== ''));

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $customer = $this->resolveCustomer();

        if ($customer === null) {
            return [];
        }

        $recommendations = $this->recommendForCustomer($customer);

        if (! empty($recommendations)) {
            event(new RecommendationGenerated(
                (string) data_get($customer, 'customer_id', data_get($customer, 'id', 'unknown')),
                array_map(static fn ($action) => Arr::except($action, ['context']), $recommendations),
                (float) ($recommendations[0]['probability'] ?? 0.0)
            ));
        }

        return $recommendations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recommendForCustomer(array $customer): array
    {
        $state = $this->extractCurrentState($customer);
        $transitions = $this->getPossibleTransitions($state, $customer);

        $recommendations = [];

        foreach ($transitions as $transition) {
            $token = $transition['token'];

            if (in_array($token, $this->excludeActions, true)) {
                continue;
            }

            $payload = $this->buildActionPayload($token, (float) $transition['probability'], $customer);

            if ($this->includeContext) {
                $payload['context'] = $this->contextPayload($customer);
            }

            $recommendations[] = $payload;

            if (count($recommendations) >= $this->topN) {
                break;
            }
        }

        if (empty($recommendations)) {
            $fallbackTokens = ['view_feature_guide', 'schedule_success_call', 'invite_to_webinar'];

            foreach ($fallbackTokens as $token) {
                if (in_array($token, $this->excludeActions, true)) {
                    continue;
                }

                $payload = $this->buildActionPayload($token, 0.35, $customer);

                if ($this->includeContext) {
                    $payload['context'] = $this->contextPayload($customer);
                }

                $recommendations[] = $payload;

                if (count($recommendations) >= $this->topN) {
                    break;
                }
            }
        }

        return array_values($recommendations);
    }

    /**
     * @return array<int, array{token: string, probability: float}>
     */
    private function getPossibleTransitions(string $state, array $customer): array
    {
        $limit = $this->topN + count($this->excludeActions) + 5;
        $this->baseline->withProbabilities(true);

        $prediction = $this->baseline->predict($state, $limit);
        $this->baseline->withProbabilities(false);

        $items = [];

        if (is_array($prediction) && isset($prediction['predictions'])) {
            foreach ($prediction['predictions'] as $item) {
                $items[] = [
                    'token' => (string) ($item['sequence'] ?? $item['token'] ?? ''),
                    'probability' => (float) ($item['probability'] ?? 0.0),
                ];
            }
        } elseif (is_array($prediction)) {
            foreach ($prediction as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $items[] = [
                    'token' => (string) ($item['sequence'] ?? $item['token'] ?? ''),
                    'probability' => (float) ($item['probability'] ?? 0.0),
                ];
            }
        }

        if (empty($items)) {
            $journeys = data_get($customer, 'journey_sequence');

            if ($journeys) {
                $tokens = preg_split('/\s+/u', (string) $journeys) ?: [];
                $tokens = array_values(array_unique(array_filter($tokens)));

                foreach ($tokens as $index => $token) {
                    $items[] = [
                        'token' => $token,
                        'probability' => max(0.05, 0.5 - ($index * 0.05)),
                    ];
                }
            }
        }

        if (empty($items)) {
            return [];
        }

        $normalized = Statistics::normalizeProbabilities(Arr::pluck($items, 'probability'));

        foreach ($items as $index => &$item) {
            $item['probability'] = $normalized[$index] ?? $item['probability'];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildActionPayload(string $token, float $probability, array $customer): array
    {
        $metadata = $this->actionLibrary[$token] ?? $this->makeGenericAction($token);
        $channel = data_get($customer, 'preferred_channel', $metadata['preferred_channel'] ?? 'email');
        $impact = $this->calculateImpact($probability, $customer);
        $scheduling = $this->determineScheduling($customer, $metadata);

        $payload = array_merge($metadata, [
            'action_id' => 'action_'.Str::orderedUuid()->toString(),
            'customer_id' => data_get($customer, 'customer_id', data_get($customer, 'id', 'unknown')),
            'recommended_action' => $token,
            'probability' => round($this->clamp($probability), 2),
            'expected_impact' => $impact,
            'notification_channel' => $channel,
            'scheduling' => $scheduling,
        ]);

        return $this->suggestionHandler->handle([$payload])[0]->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function determineScheduling(array $customer, array $metadata): array
    {
        $optimalHour = data_get($customer, 'preferred_contact_hour');

        if ($optimalHour === null) {
            $optimalHour = data_get($customer, 'usage_peak_hour');
        }

        $optimalDay = data_get($customer, 'preferred_contact_day');

        if ($optimalDay === null) {
            $optimalDay = data_get($customer, 'usage_peak_day');
        }

        return [
            'optimal_time' => $optimalHour !== null ? sprintf('%02d:00', (int) $optimalHour) : '14:30',
            'optimal_day' => $optimalDay !== null ? (string) $optimalDay : 'Wednesday',
            'frequency_cap' => data_get($metadata, 'frequency_cap', '1_per_week'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeGenericAction(string $token): array
    {
        $label = Str::of($token)->snake(' ')->title();

        return [
            'action_type' => 'in_app_message',
            'primary_message' => "Explore {$label}",
            'description' => 'Guided step to increase product adoption.',
            'call_to_action' => 'Open Now',
            'target_url' => '/app',
            'preferred_channel' => 'in_app',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateImpact(float $probability, array $customer): array
    {
        $engagement = $this->clamp((float) data_get($customer, 'engagement_score', 0.5));

        return [
            'engagement_lift' => round($this->clamp($probability * 0.6 + $engagement * 0.2), 2),
            'conversion_lift' => round($this->clamp($probability * 0.35), 2),
            'retention_lift' => round($this->clamp(($probability * 0.25) + ($engagement * 0.25)), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contextPayload(array $customer): array
    {
        $context = [
            'customer_state' => data_get($customer, 'customer_state', data_get($customer, 'state', 'unknown')),
            'days_since_signup' => data_get($customer, 'days_since_signup'),
            'last_action' => data_get($customer, 'last_action'),
            'session_duration' => data_get($customer, 'session_duration'),
            'feature_adoption' => data_get($customer, 'feature_adoption_rate', data_get($customer, 'feature_usage_breadth')),
        ];

        return array_filter($context, static fn ($value) => $value !== null && $value !== '');
    }

    private function extractCurrentState(array $customer): string
    {
        $state = (string) data_get($customer, 'last_action', '');

        if ($state !== '') {
            return $state;
        }

        $sequence = data_get($customer, 'journey_sequence');

        if ($sequence) {
            $tokens = preg_split('/\s+/u', (string) $sequence) ?: [];

            if (! empty($tokens)) {
                return (string) end($tokens);
            }
        }

        return (string) data_get($customer, 'customer_state', 'start');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCustomer(): ?array
    {
        if (! empty($this->dataset) && $this->customerId === null) {
            return $this->dataset[0];
        }

        if ($this->customerId === null) {
            return null;
        }

        foreach ($this->dataset as $record) {
            if ((string) data_get($record, 'customer_id', data_get($record, 'id')) === $this->customerId) {
                return $record;
            }
        }

        return null;
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
