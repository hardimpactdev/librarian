<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting;

interface GroupedRule extends Rule
{
    public function group(): string;
}
