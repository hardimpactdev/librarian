<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Domains;

use HardImpact\Librarian\Tests\Support\DomainRenameHook;

function rename(string $from, string $to): bool
{
    if (is_callable(DomainRenameHook::$callback)) {
        return (DomainRenameHook::$callback)($from, $to);
    }

    return \rename($from, $to);
}
