<?php

namespace VinkiusLabs\Markovable\Test\Feature\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use VinkiusLabs\Markovable\Models\MarkovableModel;
use VinkiusLabs\Markovable\Test\TestCase;
use VinkiusLabs\Markovable\Traits\HasMarkovableScopes;

class HasMarkovableScopesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tracked_entities', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tracked_entities');

        parent::tearDown();
    }

    public function test_scopes_filter_and_eager_load_markovable_data(): void
    {
        $modelClass = new class extends Model {
            use HasMarkovableScopes;

            protected $table = 'tracked_entities';
            protected $guarded = [];
            public $timestamps = false;
        };

        $modelClass->newQuery()->insert(['name' => 'First']);
        $modelClass->newQuery()->insert(['name' => 'Second']);

        MarkovableModel::create([
            'name' => 'markovable::tracked_entities:1',
            'context' => 'text',
            'markovable_type' => get_class($modelClass),
            'markovable_id' => 1,
            'payload' => ['model' => []],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trained = $modelClass->newQuery()->markovableTrained()->get();
        $withData = $modelClass->newQuery()->withMarkovableData()->first();

        $this->assertCount(1, $trained);
        $this->assertTrue($withData->relationLoaded('markovableData'));
        $this->assertInstanceOf(MarkovableModel::class, $withData->markovableData);
    }
}
