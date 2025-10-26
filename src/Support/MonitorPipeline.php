<?php

namespace VinkiusLabs\Markovable\Support;

use Illuminate\Support\Carbon;
use VinkiusLabs\Markovable\Analyzers\AnomalyDetector;
use VinkiusLabs\Markovable\MarkovableChain;

class MonitorPipeline
{
    private MarkovableChain $chain;

    private string $baselineKey;

    private ?string $storageName;

    /** @var array<string, array<string, mixed>> */
    private array $detectorConfig = [];

    /** @var array<string, array<string, string>> */
    private array $alerts = [];

    private string $interval = '5 minutes';

    public function __construct(MarkovableChain $chain, string $baselineKey, ?string $storageName = null)
    {
        $this->chain = $chain;
        $this->baselineKey = $baselineKey;
        $this->storageName = $storageName;
    }

    /**
     * @param array<string, array<string, mixed>> $config
     */
    public function detectAnomalies(array $config): self
    {
        $this->detectorConfig = $config;

        return $this;
    }

    /**
     * @param array<string, array<string, string>> $alerts
     */
    public function alerts(array $alerts): self
    {
        $this->alerts = $alerts;

        return $this;
    }

    public function checkInterval(string $interval): self
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function start(): array
    {
        $detector = new AnomalyDetector($this->chain, $this->baselineKey, $this->storageName);
        $overrides = [];

        foreach ($this->detectorConfig as $name => $config) {
            $this->configureDetector($detector, $name, $config, $overrides);
        }

        $anomalies = $detector->get($overrides);
        $alerts = $this->determineAlerts($anomalies);

        return [
            'interval' => $this->interval,
            'alerts' => $alerts,
            'anomalies' => $anomalies,
            'checked_at' => Carbon::now(),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $overrides
     */
    private function configureDetector(AnomalyDetector $detector, string $name, array $config, array &$overrides): void
    {
        switch ($name) {
            case 'unseenSequences':
                $detector->unseenSequences();
                $this->mergeOverrides($overrides, $config, ['threshold', 'minLength']);
                break;
            case 'emergingPatterns':
                $detector->emergingPatterns();
                $this->mergeOverrides($overrides, $config, ['minFrequency', 'growth', 'confidence']);
                break;
            case 'seasonality':
                $detector->detectSeasonality();
                $this->mergeOverrides($overrides, $config, ['seasonality_threshold', 'metrics']);
                break;
            case 'drift':
                $detector->drift();
                $this->mergeOverrides($overrides, $config, ['drift_threshold']);
                break;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $anomalies
     * @return array<string, array<string, string>>
     */
    private function determineAlerts(array $anomalies): array
    {
        $alerts = [];

        foreach ($anomalies as $anomaly) {
            $severity = $anomaly['severity'] ?? 'info';

            if (! isset($this->alerts[$severity])) {
                continue;
            }

            $alerts[$severity] = $this->alerts[$severity];
        }

        return $alerts;
    }

    /**
     * @param array<string, mixed> $overrides
     * @param array<string, mixed> $config
     * @param array<int, string> $keys
     */
    private function mergeOverrides(array &$overrides, array $config, array $keys): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $config)) {
                $overrides[$key] = $config[$key];
            }
        }
    }
}
