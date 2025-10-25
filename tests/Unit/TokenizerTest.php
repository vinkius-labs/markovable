<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Support\Tokenizer;

class TokenizerTest extends TestCase
{
    public function test_corpus_can_handle_strings_arrays_and_collections(): void
    {
        $data = [
            'Laravel is elegant',
            Collection::make(['Markovable chains', 'generate text']),
            null,
        ];

        $tokens = Tokenizer::corpus($data);

        $this->assertSame([
            'Laravel is elegant',
            'Markovable chains',
            'generate text',
        ], $tokens);
    }

    public function test_corpus_extracts_Markovable_columns_from_model(): void
    {
        $model = new class extends Model
        {
            protected $guarded = [];

            protected $table = 'virtual';

            public $timestamps = false;

            public $markovableColumns = ['content'];
        };

        $model->setRawAttributes(['content' => 'Testing Markovable columns']);

        $tokens = Tokenizer::corpus($model);

        $this->assertSame(['Testing Markovable columns'], $tokens);
    }

    public function test_tokenize_normalizes_whitespace(): void
    {
        $tokens = Tokenizer::tokenize("Laravel\n\n makes\tPHP");

        $this->assertSame(['Laravel', 'makes', 'PHP'], $tokens);
    }
}
