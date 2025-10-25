<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markovable_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('context')->default('text');
            $table->morphs('markovable');
            $table->longText('payload');
            $table->unsignedInteger('ttl')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('context');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markovable_models');
    }
};
