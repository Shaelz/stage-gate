<?php

declare(strict_types=1);

namespace StageGate;

final class Proof
{
    /**
     * @param iterable<int, array<string, mixed>> $rawRows keyed by source row number
     */
    public static function analyze(iterable $rawRows, Schema $schema): ProofResult
    {
        $rows = [];
        $errors = [];

        foreach ($rawRows as $sourceRow => $data) {
            $rowErrors = $schema->validate($data);

            if ($rowErrors !== []) {
                foreach ($rowErrors as $message) {
                    $errors[] = new ProofError((int) $sourceRow, $message);
                }
                continue;
            }

            $rows[] = new Row((string) $data[$schema->key], $data);
        }

        return new ProofResult($rows, $errors);
    }
}
