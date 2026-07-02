<?php

declare(strict_types=1);

use StageGate\ChangeClass;
use StageGate\Classifier;
use StageGate\FieldGroup;
use StageGate\Row;

function fixtureFieldGroups(): array
{
    return [
        new FieldGroup('schedule', ['match_date', 'round_number']),
        new FieldGroup('reference', ['home_team_code', 'away_team_code']),
        new FieldGroup('result', ['home_score', 'away_score', 'status'], isRisk: true),
    ];
}

it('classifies a row with no existing counterpart as new', function () {
    $staged = new Row('LEAGUE-R01-M01', ['home_score' => null, 'away_score' => null]);

    $classified = Classifier::classifyRow($staged, null, fixtureFieldGroups());

    expect($classified->changeClass)->toBe(ChangeClass::New);
});

it('classifies identical rows as unchanged', function () {
    $data = ['match_date' => '2026-09-20', 'round_number' => 1, 'home_team_code' => 'T_A', 'away_team_code' => 'T_B', 'home_score' => null, 'away_score' => null, 'status' => 'scheduled'];
    $staged = new Row('LEAGUE-R01-M01', $data);
    $existing = new Row('LEAGUE-R01-M01', $data);

    $classified = Classifier::classifyRow($staged, $existing, fixtureFieldGroups());

    expect($classified->changeClass)->toBe(ChangeClass::Unchanged)
        ->and($classified->fieldChanges)->toBeEmpty();
});

it('classifies a schedule-only change as updated, not overwrite risk', function () {
    $existing = new Row('LEAGUE-R01-M01', ['match_date' => '2026-09-20', 'round_number' => 1, 'home_team_code' => 'T_A', 'away_team_code' => 'T_B', 'home_score' => null, 'away_score' => null, 'status' => 'scheduled']);
    $staged = new Row('LEAGUE-R01-M01', ['match_date' => '2026-10-01', 'round_number' => 1, 'home_team_code' => 'T_A', 'away_team_code' => 'T_B', 'home_score' => null, 'away_score' => null, 'status' => 'scheduled']);

    $classified = Classifier::classifyRow($staged, $existing, fixtureFieldGroups());

    expect($classified->changeClass)->toBe(ChangeClass::Updated)
        ->and($classified->fieldChanges)->toHaveCount(1)
        ->and($classified->fieldChanges[0]->field)->toBe('match_date');
});

it('classifies a result change as overwrite risk even alongside a schedule change', function () {
    $existing = new Row('LEAGUE-R01-M01', ['match_date' => '2026-09-20', 'round_number' => 1, 'home_team_code' => 'T_A', 'away_team_code' => 'T_B', 'home_score' => null, 'away_score' => null, 'status' => 'scheduled']);
    $staged = new Row('LEAGUE-R01-M01', ['match_date' => '2026-10-01', 'round_number' => 1, 'home_team_code' => 'T_A', 'away_team_code' => 'T_B', 'home_score' => 3, 'away_score' => 1, 'status' => 'played']);

    $classified = Classifier::classifyRow($staged, $existing, fixtureFieldGroups());

    expect($classified->changeClass)->toBe(ChangeClass::OverwriteRisk);
});

it('classifies a whole staged set against existing rows by key', function () {
    $existingRows = [
        new Row('LEAGUE-R01-M01', ['match_date' => '2026-09-20', 'round_number' => 1, 'home_team_code' => 'T_A', 'away_team_code' => 'T_B', 'home_score' => null, 'away_score' => null, 'status' => 'scheduled']),
    ];
    $stagedRows = [
        new Row('LEAGUE-R01-M01', ['match_date' => '2026-09-20', 'round_number' => 1, 'home_team_code' => 'T_A', 'away_team_code' => 'T_B', 'home_score' => null, 'away_score' => null, 'status' => 'scheduled']),
        new Row('LEAGUE-R01-M02', ['match_date' => '2026-09-27', 'round_number' => 2, 'home_team_code' => 'T_C', 'away_team_code' => 'T_D', 'home_score' => null, 'away_score' => null, 'status' => 'scheduled']),
    ];

    $classified = Classifier::classifyAll($stagedRows, $existingRows, fixtureFieldGroups());

    expect($classified)->toHaveCount(2)
        ->and($classified[0]->changeClass)->toBe(ChangeClass::Unchanged)
        ->and($classified[1]->changeClass)->toBe(ChangeClass::New);
});
