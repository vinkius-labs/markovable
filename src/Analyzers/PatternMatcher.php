<?php

namespace VinkiusLabs\Markovable\Analyzers;

use VinkiusLabs\Markovable\Support\DetectionContext;

class PatternMatcher
{
    /**
     * @return array<string, int>
     */
    public function frequent(DetectionContext $context, int $minFrequency = 5): array
    {
        $sequences = [];

        foreach ($context->getCurrentSequences() as $sequence => $count) {
            if ($count < $minFrequency) {
                continue;
            }

            $sequences[$sequence] = $count;
        }

        arsort($sequences);

        return $sequences;
    }
}
