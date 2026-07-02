<?php

use StageGate\ChangeClass;
use StageGate\ClassifiedRow;
use StageGate\Row;

function classifiedRow(string $key, ChangeClass $changeClass): ClassifiedRow
{
    return new ClassifiedRow(new Row($key, []), $changeClass, []);
}
