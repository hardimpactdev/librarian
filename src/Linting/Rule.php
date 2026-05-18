<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting;

interface Rule
{
    /**
     * @return list<Finding>
     */
    public function check(): array;
}
