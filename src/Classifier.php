<?php

declare(strict_types=1);

namespace StageGate;

final class Classifier
{
    /**
     * @param Row[] $stagedRows
     * @param Row[] $existingRows keyed by row key
     * @param FieldGroup[] $fieldGroups
     * @return ClassifiedRow[]
     */
    public static function classifyAll(array $stagedRows, array $existingRows, array $fieldGroups): array
    {
        $existingByKey = [];
        foreach ($existingRows as $row) {
            $existingByKey[$row->key] = $row;
        }

        $stagedKeys = [];
        $classified = [];

        foreach ($stagedRows as $staged) {
            $stagedKeys[$staged->key] = true;
            $classified[] = self::classifyRow($staged, $existingByKey[$staged->key] ?? null, $fieldGroups);
        }

        foreach ($existingRows as $existing) {
            if (isset($stagedKeys[$existing->key])) {
                continue;
            }

            $fieldChanges = array_map(
                fn (string $field) => new FieldChange($field, $existing->get($field), null),
                array_keys($existing->data),
            );

            $classified[] = new ClassifiedRow($existing, ChangeClass::Removed, $fieldChanges);
        }

        return $classified;
    }

    /** @param FieldGroup[] $fieldGroups */
    public static function classifyRow(Row $staged, ?Row $existing, array $fieldGroups): ClassifiedRow
    {
        if ($existing === null) {
            $fieldChanges = array_map(
                fn (string $field) => new FieldChange($field, null, $staged->get($field)),
                array_keys($staged->data),
            );

            return new ClassifiedRow($staged, ChangeClass::New, $fieldChanges);
        }

        $fieldChanges = [];
        $riskChanged = false;
        $anyChanged = false;

        foreach ($fieldGroups as $group) {
            $groupChanged = false;

            foreach ($group->fields as $field) {
                $oldValue = $existing->get($field);
                $newValue = $staged->get($field);

                if ($oldValue === $newValue) {
                    continue;
                }

                $fieldChanges[] = new FieldChange($field, $oldValue, $newValue);
                $groupChanged = true;
            }

            if ($groupChanged) {
                $anyChanged = true;
                $riskChanged = $riskChanged || $group->isRisk;
            }
        }

        $changeClass = match (true) {
            $riskChanged => ChangeClass::OverwriteRisk,
            $anyChanged => ChangeClass::Updated,
            default => ChangeClass::Unchanged,
        };

        return new ClassifiedRow($staged, $changeClass, $fieldChanges);
    }
}
