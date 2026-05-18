<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Tests\Support;

final class DomainScaffolderFileWriteHook
{
    /**
     * @var (callable(string, string): int|false)|null
     */
    public static $callback = null;

    public static function reset(): void
    {
        self::$callback = null;
    }
}
