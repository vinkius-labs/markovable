<?php

namespace VinkiusLabs\Markovable\Predictors;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use VinkiusLabs\Markovable\Contracts\Predictor;
use VinkiusLabs\Markovable\Events\SeasonalForecastReady;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\Support\Statistics;
use function data_get;
use function event;
use function preg_match;
use function preg_quote;

class SeasonalForecaster implements Predictor
{
    private MarkovableChain $baseline;

    /** @var array<int, array<string, mixed>> */
    private array $dataset;

    private ?string $metric = null;

    private string $window = 'daily';

    private int $horizon = 30;

    /** @var array<int, string> */
    private array $components = [];

    private float $confidenceLevel = 0.95;

    /** @var array<int, array<string, mixed>> */
    private array $series = [];

    public function __construct(MarkovableChain $baseline, array $dataset = [])
    {
        $this->baseline = $baseline;
        $this->dataset = $dataset ?: $baseline->getRecords();
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $series
     */
    public function series(iterable $series): self
    {
        $this->series = $this->normalizeSeries($series);

        return $this;
    }

    public function metric(string $metric): self
    {
        $this->metric = $metric;

        return $this;
    }

    public function window(string $window): self
    {
        $this->window = $window;

        return $this;
    }

    public function horizon(int $days): self
    {
        $this->horizon = max(1, $days);

        return $this;
    }

    /**
     * @param  array<int, string>  $components
     */
    public function decompose(array $components): self
    {
        $this->components = array_values(array_filter($components, static fn ($component) => is_string($component) && $component !== ''));

        return $this;
    }

    public function includeConfidenceIntervals(float $level): self
    {
        $this->confidenceLevel = $this->clamp($level);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $series = $this->series;

        if (empty($series)) {
            $series = $this->extractSeriesFromDataset();
        }

        if (empty($series)) {
            return [
                'metric' => $this->metric,
                'forecast_period' => $this->forecastPeriod(Carbon::now()),
                'seasonal_patterns' => [],
                'forecast' => [],
                'actionable_insights' => [],
            ];
        }

    $patterns = $this->identifySeasonalPatterns($series);
        $forecast = $this->generateForecast($series, $patterns);
        $insights = $this->generateInsights($patterns, $forecast);
    $last = end($series) ?: ['timestamp' => Carbon::now()];
    $period = $this->forecastPeriod($last['timestamp']);

        event(new SeasonalForecastReady($this->metric ?? 'metric', $forecast, $patterns));

        return [
            'metric' => $this->metric,
            'forecast_period' => $period,
            'seasonal_patterns' => $patterns,
            'forecast' => $forecast,
            'actionable_insights' => $insights,
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $series
     * @return array<int, array{timestamp: Carbon, value: float}>
     */
    private function normalizeSeries(iterable $series): array
    {
        $normalized = [];

        foreach ($series as $entry) {
            $timestamp = data_get($entry, 'timestamp', data_get($entry, 'date'));
            $value = data_get($entry, 'value', $this->metric ? data_get($entry, $this->metric) : data_get($entry, 'value'));

            if ($timestamp === null || $value === null) {
                continue;
            }

            try {
                $normalized[] = [
                    'timestamp' => Carbon::parse($timestamp),
                    'value' => (float) $value,
                ];
            } catch (\Throwable $exception) {
                continue;
            }
        }

        usort($normalized, static fn ($left, $right) => $left['timestamp'] <=> $right['timestamp']);

        return $normalized;
    }

    /**
     * @return array<int, array{timestamp: Carbon, value: float}>
     */
    private function extractSeriesFromDataset(): array
    {
        $series = [];

        foreach ($this->dataset as $record) {
            $timestamp = $this->extractTimestamp($record);
            $value = $this->metric ? $this->extractMetricValue($record) : null;

            if ($timestamp && $value !== null) {
                $series[] = [
                    'timestamp' => $timestamp,
                    'value' => (float) $value,
                ];
            }

            foreach ($this->extractHistoricalSeries($record) as $historical) {
                $series[] = $historical;
            }
        }

        if (empty($series)) {
            foreach ($this->baseline->getSequenceFrequencies() as $sequence => $count) {
                $series[] = [
                    'timestamp' => Carbon::now()->subDays($count % 30),
                    'value' => min(1.0, strlen($sequence) / 120),
                ];
            }
        }

        usort($series, static fn ($left, $right) => $left['timestamp'] <=> $right['timestamp']);

        return $series;
    }

    /**
     * @param  array<int, array{timestamp: Carbon, value: float}>  $series
     * @return array<int, array<string, mixed>>
     */
    private function identifySeasonalPatterns(array $series): array
    {
        $overallMean = Statistics::mean(array_column($series, 'value'));

        $byDay = $this->aggregateByDayOfWeek($series);
        $dayStrength = $this->calculateSeasonalStrength($byDay, $overallMean);

        $patterns = [];

        $allowDayPattern = empty($this->components) || in_array('day_of_week', $this->components, true);

        if ($allowDayPattern && ! empty($byDay) && $dayStrength > 0.15) {
            $patterns[] = [
                'pattern_type' => 'day_of_week',
                'strength' => round($dayStrength, 2),
                'description' => $this->describeDayPattern($byDay),
                'details' => $this->formatDayDetails($byDay),
            ];
        }

    $byHour = $this->aggregateByHourOfDay($series);
        $hourStrength = $this->calculateSeasonalStrength($byHour, $overallMean);

        $allowHourPattern = empty($this->components) || in_array('hour_of_day', $this->components, true);

        if ($allowHourPattern && ! empty($byHour) && $hourStrength > 0.15) {
            $patterns[] = [
                'pattern_type' => 'hour_of_day',
                'strength' => round($hourStrength, 2),
                'description' => $this->describeHourPattern($byHour),
                'details' => $this->formatHourDetails($byHour),
            ];
        }

        return $patterns;
    }

    /**
     * @param  array<int, array{timestamp: Carbon, value: float}>  $series
     * @param  array<int, array<string, mixed>>  $patterns
     * @return array<int, array<string, mixed>>
     */
    private function generateForecast(array $series, array $patterns): array
    {
        $forecast = [];
        $baseline = Statistics::mean(array_column($series, 'value'));
        $trend = $this->estimateTrend($series);
        $lastDate = end($series)['timestamp'];

        for ($i = 1; $i <= $this->horizon; $i++) {
            $date = $this->advanceDate($lastDate, $i);
            $seasonal = $this->seasonalComponentForDate($patterns, $date, $baseline);
            $trendComponent = $baseline + ($trend * $i);
            $forecasted = max(0.0, $trendComponent + $seasonal['delta']);
            $confidenceInterval = $forecasted * (1 - $this->confidenceLevel);

            $forecast[] = [
                'date' => $date->toDateString(),
                'day_of_week' => $date->format('l'),
                'forecasted_value' => round($forecasted, 4),
                'lower_bound_'.$this->confidenceLabel() => round(max(0.0, $forecasted - $confidenceInterval), 4),
                'upper_bound_'.$this->confidenceLabel() => round($forecasted + $confidenceInterval, 4),
                'trend_component' => round($trendComponent, 4),
                'seasonal_component' => round($seasonal['value'], 4),
                'confidence' => round(max(0.0, $this->confidenceLevel - ($confidenceInterval / max(0.0001, $forecasted + 0.0001))), 2),
            ];
        }

        return $forecast;
    }

    /**
     * @param  array<int, array<string, mixed>>  $patterns
     * @param  array<int, array<string, mixed>>  $forecast
     * @return array<int, array<string, string>>
     */
    private function generateInsights(array $patterns, array $forecast): array
    {
        if (empty($patterns)) {
            return [];
        }

        $insights = [];

    foreach ($patterns as $pattern) {
            if ($pattern['pattern_type'] === 'day_of_week') {
        $averages = Arr::pluck($pattern['details'], 'average');
        $peakAverage = max($averages);
        $lowestAverage = min($averages);
        $peak = Arr::first($pattern['details'], fn ($detail) => $detail['average'] === $peakAverage);
        $lowest = Arr::first($pattern['details'], fn ($detail) => $detail['average'] === $lowestAverage);

                if ($peak) {
                    $insights[] = [
                        'insight' => 'Plan marketing campaigns for '.$peak['label'].' peak',
                        'expected_lift' => sprintf('%s%%', round(($peak['multiplier'] - 1) * 100, 0)),
                        'action' => 'Allocate more budget on '.$peak['label'].'s',
                    ];
                }

                if ($lowest) {
                    $insights[] = [
                        'insight' => $lowest['label'].' shows lower performance',
                        'action' => 'Consider maintenance or reduced spend on '.$lowest['label'].'s',
                    ];
                }
            }

            if ($pattern['pattern_type'] === 'hour_of_day') {
                $averages = Arr::pluck($pattern['details'], 'average');
                $peakAverage = max($averages);
                $peak = Arr::first($pattern['details'], fn ($detail) => $detail['average'] === $peakAverage);

                if ($peak) {
                    $insights[] = [
                        'insight' => 'Engagement peaks around '.$peak['label'],
                        'action' => 'Schedule campaigns near '.$peak['label'].' window',
                    ];
                }
            }
        }

        return $insights;
    }

    /**
     * @param  array<int, array{timestamp: Carbon, value: float}>  $series
     * @param  float  $overallMean
     * @return array<string, array<int, float>>
     */
    private function aggregateByDayOfWeek(array $series): array
    {
        $groups = [];

        foreach ($series as $entry) {
            $day = $entry['timestamp']->format('l');
            $groups[$day][] = $entry['value'];
        }

        return $groups;
    }

    /**
     * @param  array<int, array{timestamp: Carbon, value: float}>  $series
     * @param  float  $overallMean
     * @return array<string, array<int, float>>
     */
    private function aggregateByHourOfDay(array $series): array
    {
        $groups = [];

        foreach ($series as $entry) {
            $hour = $entry['timestamp']->format('H:00');
            $groups[$hour][] = $entry['value'];
        }

        return $groups;
    }

    /**
     * @param  array<string, array<int, float>>  $groups
     */
    private function calculateSeasonalStrength(array $groups, float $overallMean): float
    {
        if (empty($groups)) {
            return 0.0;
        }

        $between = 0.0;
        $within = 0.0;

        foreach ($groups as $values) {
            $mean = Statistics::mean($values);
            $between += pow($mean - $overallMean, 2) * count($values);
            $within += Statistics::variance($values) * max(1, count($values) - 1);
        }

        if (($between + $within) <= 0) {
            return 0.0;
        }

        return $this->clamp($between / ($between + $within));
    }

    /**
     * @param  array<string, array<int, float>>  $groups
     * @return string
     */
    private function describeDayPattern(array $groups): string
    {
        $averages = [];

        foreach ($groups as $day => $values) {
            $averages[$day] = Statistics::mean($values);
        }

        arsort($averages);
        $best = array_key_first($averages);
        $worst = array_key_last($averages);

        if ($best === null || $worst === null) {
            return 'Stable day-of-week performance';
        }

        $lift = $averages[$best] - $averages[$worst];

        return sprintf('%s performs %.0f%% better than %s', $best, $lift * 100, $worst);
    }

    /**
     * @param  array<string, array<int, float>>  $groups
     * @return array<string, array<string, mixed>>
     */
    private function formatDayDetails(array $groups): array
    {
        $details = [];
    $overall = Statistics::mean($this->flattenGroupedValues($groups));

        foreach ($groups as $day => $values) {
            $average = Statistics::mean($values);
            $details[strtolower($day)] = [
                'label' => $day,
                'multiplier' => $overall > 0 ? round($average / $overall, 2) : 1.0,
                'average' => round($average, 4),
            ];
        }

        return $details;
    }

    /**
     * @param  array<string, array<int, float>>  $groups
     * @return string
     */
    private function describeHourPattern(array $groups): string
    {
        $averages = [];

        foreach ($groups as $hour => $values) {
            $averages[$hour] = Statistics::mean($values);
        }

        arsort($averages);
        $top = array_slice($averages, 0, 2, true);

        $hours = implode(', ', array_map(static fn ($hour) => $hour, array_keys($top)));

        return 'Peak performance around '.$hours;
    }

    /**
     * @param  array<string, array<int, float>>  $groups
     * @return array<string, array<string, mixed>>
     */
    private function formatHourDetails(array $groups): array
    {
        $details = [];
    $overall = Statistics::mean($this->flattenGroupedValues($groups));

        foreach ($groups as $hour => $values) {
            $average = Statistics::mean($values);
            $details[$hour] = [
                'label' => $hour,
                'multiplier' => $overall > 0 ? round($average / $overall, 2) : 1.0,
                'average' => round($average, 4),
            ];
        }

        return $details;
    }

    /**
     * @param  array<int, array{timestamp: Carbon, value: float}>  $series
     */
    private function estimateTrend(array $series): float
    {
        if (count($series) < 2) {
            return 0.0;
        }


    $first = $series[array_key_first($series)];
    $last = $series[array_key_last($series)];

        $days = max(1, $first['timestamp']->diffInDays($last['timestamp']) ?: count($series));

        return ($last['value'] - $first['value']) / $days;
    }

    /**
     * @param  array<int, array<string, mixed>>  $patterns
     * @return array{value: float, delta: float}
     */
    private function seasonalComponentForDate(array $patterns, Carbon $date, float $baseline): array
    {
        $value = $baseline;

        foreach ($patterns as $pattern) {
            if ($pattern['pattern_type'] === 'day_of_week') {
                $key = strtolower($date->format('l'));
                $detail = $pattern['details'][$key] ?? null;
                $value = $detail['average'] ?? $value;
            }

            if ($pattern['pattern_type'] === 'hour_of_day') {
                $hour = sprintf('%02d:00', (int) $date->format('H'));
                $detail = $pattern['details'][$hour] ?? null;

                if ($detail) {
                    $value = $value * ($detail['multiplier'] ?? 1.0);
                }
            }
        }

        return [
            'value' => $value,
            'delta' => $value - $baseline,
        ];
    }

    /**
     * @param  array<string, array<int, float>>  $groups
     * @return array<int, float>
     */
    private function flattenGroupedValues(array $groups): array
    {
        $merged = [];

        foreach ($groups as $values) {
            foreach ($values as $value) {
                $merged[] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function extractTimestamp(array $record): ?Carbon
    {
        $candidates = [
            'timestamp',
            'date',
            'created_at',
            'metrics.timestamp',
            'metrics.date',
            'metrics.created_at',
        ];

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $record) && $record[$candidate]) {
                return $this->parseCarbon($record[$candidate]);
            }
        }

        foreach ($record as $key => $value) {
            if (! is_string($key) || $value === null) {
                continue;
            }

            if (preg_match('/timestamp|date/i', $key)) {
                $parsed = $this->parseCarbon($value);

                if ($parsed) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function extractMetricValue(array $record): ?float
    {
        if ($this->metric === null) {
            return null;
        }

        $candidates = [
            $this->metric,
            "metrics.{$this->metric}",
            "metrics_{$this->metric}",
        ];

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $record) && $record[$candidate] !== null) {
                return (float) $record[$candidate];
            }
        }

        foreach ($record as $key => $value) {
            if (! is_string($key) || $value === null || ! is_numeric($value)) {
                continue;
            }

            if (preg_match('/'.preg_quote($this->metric, '/').'$/', $key)) {
                return (float) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<int, array{timestamp: Carbon, value: float}>
     */
    private function extractHistoricalSeries(array $record): array
    {
        $timestamps = [];
        $values = [];

        foreach ($record as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (preg_match('/(?:history|series)\.(\d+)\.(?:timestamp|date)$/', $key, $matches) && $value) {
                $index = (int) $matches[1];
                $parsed = $this->parseCarbon($value);

                if ($parsed) {
                    $timestamps[$index] = $parsed;
                }

                continue;
            }

            if ($this->metric && preg_match('/(?:history|series)\.(\d+)\.'.preg_quote($this->metric, '/').'$/', $key, $matches) && is_numeric($value)) {
                $values[(int) $matches[1]] = (float) $value;
                continue;
            }

            if (preg_match('/(?:history|series)\.(\d+)\.value$/', $key, $matches) && is_numeric($value)) {
                $values[(int) $matches[1]] = (float) $value;
            }
        }

        $series = [];

        foreach ($timestamps as $index => $timestamp) {
            if (! isset($values[$index])) {
                continue;
            }

            $series[] = [
                'timestamp' => $timestamp,
                'value' => $values[$index],
            ];
        }

        return $series;
    }

    private function parseCarbon($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable $exception) {
                return null;
            }
        }

        if (is_int($value)) {
            return Carbon::createFromTimestamp($value);
        }

        return null;
    }

    private function forecastPeriod(Carbon $start): array
    {
        $from = $start->copy()->addDay();
        $to = $this->advanceDate($from, $this->horizon - 1);

        return [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
            'intervals' => $this->horizon,
            'window' => strtolower($this->window),
        ];
    }

    private function advanceDate(Carbon $date, int $steps): Carbon
    {
        return match (strtolower($this->window)) {
            'weekly' => $date->copy()->addWeeks($steps),
            'monthly' => $date->copy()->addMonthsNoOverflow($steps),
            default => $date->copy()->addDays($steps),
        };
    }

    private function confidenceLabel(): string
    {
        return (string) round($this->confidenceLevel * 100);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
