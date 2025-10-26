<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use Illuminate\Support\Facades\File;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Models\PageRankSnapshot;
use VinkiusLabs\Markovable\Test\TestCase;

class CalculatePageRankCommandTest extends TestCase
{
    public function test_command_renders_table_output(): void
    {
        $this->prepareBaseline('table-baseline');

        $this->artisan('markovable:pagerank', [
            'baseline' => 'table-baseline',
            '--context' => 'navigation',
            '--top' => '2',
        ])->expectsOutputToContain('Node')
            ->expectsOutputToContain('/home')
            ->assertExitCode(0);
    }

    public function test_command_can_export_and_snapshot(): void
    {
        $this->prepareBaseline('export-baseline');

        $exportPath = storage_path('app/pagerank_export.json');

        if (File::exists($exportPath)) {
            File::delete($exportPath);
        }

        $this->artisan('markovable:pagerank', [
            'baseline' => 'export-baseline',
            '--context' => 'navigation',
            '--metadata' => true,
            '--export' => $exportPath,
            '--snapshot' => true,
            '--tag' => 'weekly',
        ])->expectsOutputToContain('PageRank snapshot stored successfully.')
            ->expectsOutputToContain('PageRank data exported')
            ->assertExitCode(0);

        $this->assertFileExists($exportPath);
    $this->assertSame(1, PageRankSnapshot::query()->count());

    /** @var PageRankSnapshot $snapshot */
    $snapshot = PageRankSnapshot::query()->first();
        $this->assertSame('export-baseline', $snapshot->model_key);
        $this->assertSame('weekly', $snapshot->tag);
    }

    private function prepareBaseline(string $key): void
    {
        Markovable::chain('navigation')
            ->train([
                '/home /products /checkout',
                '/home /support /pricing',
                '/landing /signup /home',
                '/home /products /cart',
            ])
            ->cache($key);
    }
}
