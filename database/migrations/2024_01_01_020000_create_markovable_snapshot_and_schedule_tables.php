<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markovable_model_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('model_key');
            $table->string('tag')->nullable();
            $table->string('storage')->default('database');
            $table->string('description')->nullable();
            $table->boolean('compressed')->default(false);
            $table->boolean('encrypted')->default(false);
            $table->unsignedBigInteger('original_size')->default(0);
            $table->unsignedBigInteger('stored_size')->default(0);
            $table->longText('payload');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['model_key', 'tag']);
            $table->index('model_key');
        });

        Schema::create('markovable_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('action');
            $table->string('model_key')->nullable();
            $table->string('frequency');
            $table->string('time')->nullable();
            $table->string('cron_expression')->nullable();
            $table->string('callback')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['action', 'model_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markovable_schedules');
        Schema::dropIfExists('markovable_model_snapshots');
    }
};
