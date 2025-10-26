<?php

namespace VinkiusLabs\Markovable\Console\Concerns;

trait FormatsBytes
{
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = (int) floor(log($bytes, 1024));
        $base = max(0, min($base, count($units) - 1));
        $value = $bytes / (1024 ** $base);

        return number_format($value, $precision)." {$units[$base]}";
    }
}
