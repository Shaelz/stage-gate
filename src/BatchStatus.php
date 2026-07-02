<?php

declare(strict_types=1);

namespace StageGate;

enum BatchStatus
{
    case Staged;
    case Approved;
    case Published;
}
