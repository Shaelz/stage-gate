<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('stage-gate.tables.audit_logs'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')
                ->nullable()
                ->constrained(config('stage-gate.tables.import_batches'))
                ->nullOnDelete();
            $table->string('source');
            $table->string('approved_by');
            $table->json('change_counts');
            $table->json('published_row_keys');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('stage-gate.tables.audit_logs'));
    }
};
