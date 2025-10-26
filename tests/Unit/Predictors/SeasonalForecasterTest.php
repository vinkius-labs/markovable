<?php

namespace VinkiusLabs\Markovable\Test\Unit\Predictors;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Events\SeasonalForecastReady;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Predictors\SeasonalForecaster;
use VinkiusLabs\Markovable\Support\Dataset;
use VinkiusLabs\Markovable\Test\TestCase;

class SeasonalForecasterTest extends TestCase
{
    public function test_generates_forecast_from_dataset(): void
    {
        Event::fake();

        $rawRecords = $this->revenueSeries();
        $records = Dataset::normalize($rawRecords);
        $baseline = $this->trainBaseline($records, 'seasonal-'.Str::uuid());

        $forecaster = (new SeasonalForecaster($baseline, $records))
            ->metric('monthly_recurring_revenue')
            ->window('daily')
            ->horizon(5)
            ->includeConfidenceIntervals(0.92)
            ->decompose(['day_of_week', '', 'hour_of_day']);

        $forecast = $forecaster->get();

        $this->assertNotEmpty($forecast['forecast']);
        $this->assertSame('daily', $forecast['forecast_period']['window']);
        $this->assertCount(5, $forecast['forecast']);
        $this->assertNotEmpty($forecast['seasonal_patterns']);

        Event::assertDispatched(SeasonalForecastReady::class, static function ($event) {
            return $event->metric === 'monthly_recurring_revenue'
                && ! empty($event->forecast);
        });
    }

    public function test_manual_series_and_window_adjustments(): void
    {
        $baseline = $this->trainBaseline([], 'seasonal-manual');
        $series = [];

        for ($i = 0; $i < 4; $i++) {
            $series[] = [
                'timestamp' => Carbon::create(2024, 1, 1, 9)->addMonths($i),
                'value' => 1000 + ($i * 150),
            ];
        }

        $forecaster = (new SeasonalForecaster($baseline))
            ->series($series)
            ->window('monthly')
            ->horizon(2)
            ->includeConfidenceIntervals(1.2);

        $forecast = $forecaster->get();

        $this->assertSame('monthly', $forecast['forecast_period']['window']);
        $this->assertSame(2, $forecast['forecast_period']['intervals']);
        $this->assertCount(2, $forecast['forecast']);
    }

    public function test_falls_back_to_sequence_frequencies_when_dataset_empty(): void
    {
        $baseline = $this->trainBaseline(['view_dashboard upgrade_plan'], 'seasonal-fallback');

    $forecaster = new SeasonalForecaster($baseline, []);

    $forecast = $forecaster->metric('value')->window('weekly')->horizon(0)->get();

    $this->assertCount(1, $forecast['forecast']);
    $this->assertSame('weekly', $forecast['forecast_period']['window']);
    }

    public function test_series_with_invalid_entries_falls_back_to_sequences(): void
    {
        $baseline = $this->trainBaseline(['view_dashboard upgrade_plan'], 'seasonal-invalid');

        $forecaster = (new SeasonalForecaster($baseline))
            ->series([
                ['timestamp' => 'not-a-date', 'value' => 10],
                ['timestamp' => null, 'value' => 20],
            ])
            ->metric('value')
            ->includeConfidenceIntervals(-0.5)
            ->horizon(1);

        $forecast = $forecaster->get();

        $this->assertNotEmpty($forecast['forecast']);
        $this->assertArrayHasKey('lower_bound_0', $forecast['forecast'][0]);
    }

    private function revenueSeries(): array
    {
        $base = Carbon::create(2024, 5, 1, 9);
        $records = [];

        for ($day = 0; $day < 14; $day++) {
            $records[] = [
                'timestamp' => $base->copy()->addDays($day)->toDateString(),
                'metrics' => [
                    'timestamp' => $base->copy()->addDays($day)->toDateTimeString(),
                    'monthly_recurring_revenue' => 5000 + (200 * sin($day)) + ($day * 75),
                    'history' => [
                        [
                            'timestamp' => $base->copy()->addDays($day)->timestamp,
                            'monthly_recurring_revenue' => 4800 + ($day * 60),
                        ],
                        [
                            'timestamp' => $base->copy()->addDays($day)->addHours(12)->toIso8601String(),
                            'monthly_recurring_revenue' => 4900 + ($day * 65),
                        ],
                    ],
                ],
                'metric_history' => [
                    [
                        'timestamp' => $base->copy()->addDays($day)->addHours(8),
                        'monthly_recurring_revenue' => 4700 + ($day * 55),
                    ],
                ],
            ];
        }

        return $records;
    }

    private function trainBaseline($records, string $key)
    {
        $manager = app(MarkovableManager::class);

        return $manager->chain('analytics')
            ->order(1)
            ->cache($key)
            ->train($records);
    }
}
