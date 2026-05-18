<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting;

enum FindingSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
