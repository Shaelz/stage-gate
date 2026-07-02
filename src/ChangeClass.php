<?php

declare(strict_types=1);

namespace StageGate;

enum ChangeClass
{
    case New;
    case Unchanged;
    case Updated;
    case OverwriteRisk;
    case Removed;
}
