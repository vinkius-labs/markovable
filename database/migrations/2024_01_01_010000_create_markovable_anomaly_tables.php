<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markovable_anomalies', function (Blueprint $table) {
            $table->id();
            $table->string('model_key');
            $table->string('type');
            $table->json('sequence')->nullable();
            $table->float('score')->default(0);
            $table->unsignedInteger('count')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['model_key', 'type', 'detected_at']);
        });

        Schema::create('markovable_pattern_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('model_key');
            $table->json('pattern');
            $table->string('severity')->default('info');
            $table->json('metadata')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('markovable_cluster_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('model_key');
            $table->unsignedInteger('cluster_id');
            $table->string('profile');
            $table->unsignedInteger('size');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['model_key', 'cluster_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markovable_cluster_profiles');
        Schema::dropIfExists('markovable_pattern_alerts');
        Schema::dropIfExists('markovable_anomalies');
    }
};
