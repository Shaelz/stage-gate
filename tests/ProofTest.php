<?php

declare(strict_types=1);

use StageGate\Field;
use StageGate\Proof;
use StageGate\Schema;

function fixtureSchema(): Schema
{
    return new Schema('fixture_key', [
        new Field('fixture_key'),
        new Field('home_score', required: false, validate: fn ($value) => $value === null || is_int($value)),
    ]);
}

it('accepts rows that satisfy the schema', function () {
    $result = Proof::analyze([
        1 => ['fixture_key' => 'LEAGUE-R01-M01', 'home_score' => 3],
    ], fixtureSchema());

    expect($result->isValid())->toBeTrue()
        ->and($result->rows)->toHaveCount(1)
        ->and($result->rows[0]->key)->toBe('LEAGUE-R01-M01');
});

it('fails malformed rows before anything else runs', function () {
    $result = Proof::analyze([
        1 => ['fixture_key' => 'LEAGUE-R01-M01', 'home_score' => 3],
        2 => ['fixture_key' => 'LEAGUE-R01-M02', 'home_score' => 'nine'],
    ], fixtureSchema());

    expect($result->isValid())->toBeFalse()
        ->and($result->rows)->toHaveCount(1)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->sourceRow)->toBe(2);
});

it('requires the row key field', function () {
    $result = Proof::analyze([
        1 => ['home_score' => 3],
    ], fixtureSchema());

    expect($result->isValid())->toBeFalse()
        ->and($result->errors[0]->message)->toContain('fixture_key');
});
