<?php

declare(strict_types=1);

namespace StageGate\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use StageGate\Approve;
use StageGate\Classifier;
use StageGate\Laravel\BatchRepository;
use StageGate\Laravel\ImportDefinition;
use StageGate\Laravel\PublishExecutor;
use StageGate\Publish;
use StageGate\PublishBlockedException;

final class PublishJob implements ShouldQueue
{
    use Queueable;

    /** @param class-string<ImportDefinition> $importDefinitionClass */
    public function __construct(
        public readonly string $importDefinitionClass,
        public readonly string $batchKey,
        public readonly string $source,
    ) {
    }

    /**
     * Re-derives the diff and re-checks approval against fresh data before
     * publishing — the same safety re-check biljartv2 relies on, so a
     * canonical row edited after approval still blocks an unapproved
     * overwrite instead of silently publishing.
     *
     * @throws PublishBlockedException
     */
    public function handle(): void
    {
        $definition = app($this->importDefinitionClass);

        $batch = BatchRepository::find($this->batchKey);

        if ($batch === null) {
            throw new PublishBlockedException([]);
        }

        $existingRows = $definition->existingRowsProvider()->existingRows($batch);
        $classifiedRows = Classifier::classifyAll($batch->rows, $existingRows, $definition->fieldGroups());

        $priorApproval = BatchRepository::loadApproval($this->batchKey);
        $approval = Approve::approve(
            $batch,
            $classifiedRows,
            $priorApproval['approvedRowKeys'],
            $priorApproval['approvedBy'],
        );

        $plan = Publish::plan($classifiedRows, $approval, $this->source);

        PublishExecutor::execute($plan, $definition->publishWriter(), $this->batchKey);
    }
}
