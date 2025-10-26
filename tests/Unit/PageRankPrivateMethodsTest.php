<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use VinkiusLabs\Markovable\Analyzers\PageRankAnalyzer;
use VinkiusLabs\Markovable\Models\PageRankNode;
use VinkiusLabs\Markovable\Models\PageRankResult;

class PageRankPrivateMethodsTest extends TestCase
{
    public function test_normalize_scores_range_zero_uses_probability(): void
    {
        $analyzer = new PageRankAnalyzer;

        $ref = new ReflectionMethod(PageRankAnalyzer::class, 'normalizeScores');
        $ref->setAccessible(true);

        $scores = ['A' => 1.0, 'B' => 1.0, 'C' => 0.0];
        $normalized = $ref->invoke($analyzer, $scores);

        $this->assertArrayHasKey('A', $normalized);
        $this->assertArrayHasKey('B', $normalized);
        $this->assertArrayHasKey('C', $normalized);

        $sum = array_sum($normalized);
        // Values are percentages; sum should be approximately 200 (since C is zero)
        $this->assertGreaterThan(0.0, $sum);
    }

    public function test_percentiles_returns_expected_scale(): void
    {
        $analyzer = new PageRankAnalyzer;

        $ref = new ReflectionMethod(PageRankAnalyzer::class, 'percentiles');
        $ref->setAccessible(true);

        $scores = ['X' => 0.9, 'Y' => 0.5, 'Z' => 0.1];
        $p = $ref->invoke($analyzer, $scores);

        $this->assertCount(3, $p);
        $this->assertArrayHasKey('X', $p);
        $this->assertArrayHasKey('Y', $p);
        $this->assertArrayHasKey('Z', $p);
    }

    public function test_resolve_group_key_variants(): void
    {
        $analyzer = new PageRankAnalyzer;

        $ref = new ReflectionMethod(PageRankAnalyzer::class, 'resolveGroupKey');
        $ref->setAccessible(true);

        $v1 = $ref->invoke($analyzer, '/a/b/c', 'segment:1');
        $this->assertSame('b', $v1);

        $v2 = $ref->invoke($analyzer, 'https://example.com/path', 'domain');
        $this->assertStringContainsString('example.com', $v2);

        $v3 = $ref->invoke($analyzer, '/alpha/beta', 'prefix');
        $this->assertSame('alpha', $v3);
    }

    public function test_finalize_payload_returns_payload_with_result(): void
    {
        $analyzer = new PageRankAnalyzer;

        $node = new PageRankNode('n', 0.1, 10.0, 50.0);
        $result = new PageRankResult(['n' => $node], ['iterations' => 1]);

        $ref = new ReflectionMethod(PageRankAnalyzer::class, 'finalizePayload');
        $ref->setAccessible(true);

        $payload = $ref->invoke($analyzer, $result, true);

        $this->assertArrayHasKey('pagerank', $payload);
        $this->assertArrayHasKey('__result', $payload);
    }

    public function test_serialize_nodes_private_method_of_result(): void
    {
        $node = new PageRankNode('n', 0.2, 20.0, 60.0);
        $result = new PageRankResult(['n' => $node]);

        $ref = new ReflectionMethod(PageRankResult::class, 'serializeNodes');
        $ref->setAccessible(true);

        $serialized = $ref->invoke($result, ['n' => $node]);
        $this->assertArrayHasKey('n', $serialized);
        $this->assertIsArray($serialized['n']);
    }
}
