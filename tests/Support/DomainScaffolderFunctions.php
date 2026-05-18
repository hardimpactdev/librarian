<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Domains;

use HardImpact\Librarian\Tests\Support\DomainScaffolderFileWriteHook;

function file_put_contents(string $filename, mixed $data, int $flags = 0, $context = null): int|false
{
    if (is_callable(DomainScaffolderFileWriteHook::$callback)) {
        return (DomainScaffolderFileWriteHook::$callback)($filename, (string) $data);
    }

    if ($context !== null) {
        return \file_put_contents($filename, $data, $flags, $context);
    }

    return \file_put_contents($filename, $data, $flags);
}
