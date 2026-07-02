<?php

declare(strict_types=1);

namespace StageGate;

final class Schema
{
    /** @param Field[] $fields */
    public function __construct(
        public readonly string $key,
        public readonly array $fields,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return string[] validation error messages, empty when the row is valid
     */
    public function validate(array $data): array
    {
        $errors = [];

        foreach ($this->fields as $field) {
            $present = array_key_exists($field->name, $data);

            if (! $present) {
                if ($field->required) {
                    $errors[] = "Missing required field '{$field->name}'.";
                }
                continue;
            }

            if ($field->validate !== null && ! ($field->validate)($data[$field->name])) {
                $errors[] = "Field '{$field->name}' failed validation.";
            }
        }

        if (! array_key_exists($this->key, $data)) {
            $errors[] = "Missing row key field '{$this->key}'.";
        }

        return $errors;
    }
}
