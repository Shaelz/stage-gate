<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('stage-gate.tables.import_batches'), function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('status');
            $table->string('approved_by')->nullable();
            $table->json('approved_row_keys')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('stage-gate.tables.import_batches'));
    }
};
