<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_gate_import_batch_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')
                ->constrained(config('stage-gate.tables.import_batches'))
                ->cascadeOnDelete();
            $table->string('row_key');
            $table->json('data');
            $table->timestamps();

            $table->unique(['import_batch_id', 'row_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_gate_import_batch_rows');
    }
};
