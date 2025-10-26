<?php

namespace VinkiusLabs\Markovable\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \VinkiusLabs\Markovable\MarkovableChain chain(?string $context = null)
 * @method static \VinkiusLabs\Markovable\MarkovableChain train($value)
 * @method static \VinkiusLabs\Markovable\MarkovableChain trainFrom($value)
 * @method static \VinkiusLabs\Markovable\MarkovableChain order(int $order)
 * @method static \VinkiusLabs\Markovable\MarkovableChain analyze($subject = null, array $options = [])
 * @method static \VinkiusLabs\Markovable\Builders\PageRankBuilder pageRank()
 * @method static \VinkiusLabs\Markovable\Analyzers\AnomalyDetector detect(string $baselineKey, $current = null)
 * @method static \VinkiusLabs\Markovable\Analyzers\AnomalyDetector detectSeasonality(?string $baselineKey = null)
 * @method static \VinkiusLabs\Markovable\Detectors\ClusterAnalyzer cluster(?string $baselineKey = null)
 * @method static \VinkiusLabs\Markovable\Support\MonitorPipeline monitor(string $baselineKey)
 * @method static \VinkiusLabs\Markovable\Builders\PredictiveBuilder predictive(string $baselineKey, array $options = [], ?string $context = null, ?string $storage = null)
 */
class Markovable extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'markovable';
    }
}
