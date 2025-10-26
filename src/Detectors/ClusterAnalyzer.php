<?php

namespace VinkiusLabs\Markovable\Detectors;

use Illuminate\Support\Facades\Event;
use RuntimeException;
use VinkiusLabs\Markovable\Events\ClusterShifted;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\Support\DetectionContext;
use VinkiusLabs\Markovable\Support\Tokenizer;

class ClusterAnalyzer
{
    private MarkovableChain $chain;

    private ?DetectionContext $baseline = null;

    private string $algorithm = 'kmeans';

    private int $clusters = 3;

    /** @var array<int, string> */
    private array $features = ['length', 'frequency'];

    public function __construct(MarkovableChain $chain, ?string $baselineKey = null, ?string $storageName = null)
    {
        $this->chain = $chain;

        if ($baselineKey) {
            $manager = $chain->getManager();
            $payload = $manager->storage($storageName)->get($baselineKey);

            if (! $payload) {
                throw new RuntimeException("Baseline [{$baselineKey}] could not be found for clustering.");
            }

            $this->baseline = new DetectionContext($chain, $payload, $baselineKey, $storageName);
        }
    }

    public function algorithm(string $algorithm): self
    {
        $this->algorithm = strtolower($algorithm);

        return $this;
    }

    public function numberOfClusters(int $clusters): self
    {
        $this->clusters = max(1, $clusters);

        return $this;
    }

    public function features(array $features): self
    {
        $this->features = array_values($features);

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function analyze(): array
    {
        $dataset = $this->buildDataset();

        if (empty($dataset)) {
            return [];
        }

        $clusters = $this->clusterDataset($dataset);
        $profiles = $this->profilesFromClusters($clusters, count($dataset));

        if ($this->baseline) {
            $baselineProfiles = $this->profilesFromClusters($this->clusterDataset($this->buildBaselineDataset()), count($dataset));
            if ($this->clustersChanged($baselineProfiles, $profiles)) {
                Event::dispatch(new ClusterShifted($baselineProfiles, $profiles));
            }
        }

        return $profiles;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDataset(): array
    {
        $sequences = [];

        foreach ($this->chain->getSequenceFrequencies() as $sequence => $count) {
            $tokens = Tokenizer::tokenize($sequence);

            if (empty($tokens)) {
                continue;
            }

            $sequences[] = [
                'sequence' => $tokens,
                'count' => $count,
                'length' => count($tokens),
                'frequency' => $count,
            ];
        }

        return $sequences;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildBaselineDataset(): array
    {
        if (! $this->baseline) {
            return [];
        }

        $sequences = [];

        foreach ($this->baseline->getBaselineMeta()['sequence_frequencies'] ?? [] as $sequence => $count) {
            $tokens = Tokenizer::tokenize(is_string($sequence) ? $sequence : implode(' ', (array) $sequence));
            $sequences[] = [
                'sequence' => $tokens,
                'count' => $count,
                'length' => count($tokens),
                'frequency' => $count,
            ];
        }

        return $sequences;
    }

    /**
     * @param array<int, array<string, mixed>> $dataset
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function clusterDataset(array $dataset): array
    {
        $clusters = array_fill(1, $this->clusters, []);

        if ($this->algorithm === 'dbscan') {
            return $this->clusterDbscan($dataset);
        }

        $index = 0;

        foreach ($dataset as $item) {
            $clusterId = ($index % $this->clusters) + 1;
            $clusters[$clusterId][] = $item;
            $index++;
        }

        return $clusters;
    }

    /**
     * @param array<int, array<string, mixed>> $dataset
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function clusterDbscan(array $dataset): array
    {
        // Simple density-based grouping: high frequency vs low frequency vs medium.
        $clusters = [1 => [], 2 => [], 3 => []];

        foreach ($dataset as $item) {
            if ($item['frequency'] >= 10) {
                $clusters[1][] = $item;
            } elseif ($item['frequency'] <= 3) {
                $clusters[3][] = $item;
            } else {
                $clusters[2][] = $item;
            }
        }

        return $clusters;
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $clusters
     * @return array<int, array<string, mixed>>
     */
    private function profilesFromClusters(array $clusters, int $datasetSize): array
    {
        $profiles = [];

        foreach ($clusters as $id => $members) {
            if (empty($members)) {
                continue;
            }

            $characteristics = $this->characteristicsFor($members);
            $profiles[] = [
                'cluster_id' => $id,
                'size' => count($members),
                'percentage' => $this->formatPercentage(count($members), $datasetSize),
                'profile' => $this->nameCluster($characteristics),
                'characteristics' => $characteristics,
                'description' => $this->describeCluster($characteristics),
            ];
        }

        return $profiles;
    }

    /**
     * @param array<int, array<string, mixed>> $members
     * @return array<string, mixed>
     */
    private function characteristicsFor(array $members): array
    {
        $size = count($members);
        $totalLength = array_sum(array_map(static fn($member) => $member['length'], $members));
        $totalFrequency = array_sum(array_map(static fn($member) => $member['frequency'], $members));

        $favorite = collect($members)->sortByDesc('frequency')->first();
        $avgSession = $size > 0 ? $totalLength / $size : 0.0;
        $conversionRate = $totalFrequency > 0 ? min(1.0, $totalFrequency / ($size * 10)) : 0.0;

        return [
            'avg_session_length' => round($avgSession, 2),
            'favorite_path' => $favorite['sequence'] ?? [],
            'conversion_rate' => round($conversionRate, 2),
            'frequency_sum' => $totalFrequency,
        ];
    }

    private function nameCluster(array $characteristics): string
    {
        if (($characteristics['conversion_rate'] ?? 0) >= 0.6) {
            return 'power_users';
        }

        if (($characteristics['avg_session_length'] ?? 0) < 3) {
            return 'quick_browsers';
        }

        if (($characteristics['frequency_sum'] ?? 0) >= 20) {
            return 'loyal';
        }

        return 'general';
    }

    private function describeCluster(array $characteristics): string
    {
        $profile = $this->nameCluster($characteristics);

        return match ($profile) {
            'power_users' => 'Users with elevated conversion behaviour and deep sessions',
            'quick_browsers' => 'Short-lived sessions with rapid navigation',
            'loyal' => 'Frequent users repeatedly engaging with flows',
            default => 'Balanced usage pattern without strong deviations',
        };
    }

    private function formatPercentage(int $count, int $total): string
    {
        if ($total <= 0) {
            return '0%';
        }

        return number_format(($count / $total) * 100, 1) . '%';
    }

    /**
     * @param array<int, array<string, mixed>> $baseline
     * @param array<int, array<string, mixed>> $current
     */
    private function clustersChanged(array $baseline, array $current): bool
    {
        if (count($baseline) !== count($current)) {
            return true;
        }

        foreach ($current as $index => $profile) {
            $baselineProfile = $baseline[$index] ?? null;

            if (! $baselineProfile) {
                return true;
            }

            if ($profile['profile'] !== $baselineProfile['profile']) {
                return true;
            }
        }

        return false;
    }
}
