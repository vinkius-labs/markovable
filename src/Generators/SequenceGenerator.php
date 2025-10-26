<?php

namespace VinkiusLabs\Markovable\Generators;

class SequenceGenerator extends TextGenerator
{
    public function generate(array $model, int $length, array $options = []): string
    {
        $result = parent::generate($model, $length, $options);

        if (($options['as_array'] ?? false) === true) {
            return json_encode(preg_split('/\s+/u', trim($result)) ?: []);
        }

        return $result;
    }
}
