<?php

declare(strict_types=1);

namespace StageGate\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use StageGate\Laravel\BatchRepository;
use StageGate\Laravel\ImportDefinition;
use StageGate\Proof;
use StageGate\Stage;

final class ProofAndStageJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param class-string<ImportDefinition> $importDefinitionClass resolved
     *     from the container at handle() time, never stored as job state, so
     *     a Schema's validator closures never need to survive serialization
     * @param iterable<int, array<string, mixed>> $rawRows keyed by source row number
     */
    public function __construct(
        public readonly string $importDefinitionClass,
        public readonly string $batchKey,
        public readonly iterable $rawRows,
    ) {
    }

    public function handle(): void
    {
        $definition = app($this->importDefinitionClass);

        $proof = Proof::analyze($this->rawRows, $definition->schema());

        if (! $proof->isValid()) {
            BatchRepository::saveProofFailure($this->batchKey, $proof->errors);
            return;
        }

        BatchRepository::save(Stage::stage($this->batchKey, $proof->rows));
    }
}
