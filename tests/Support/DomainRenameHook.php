<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Tests\Support;

final class DomainRenameHook
{
    /**
     * @var (callable(string, string): bool)|null
     */
    public static $callback = null;

    public static function reset(): void
    {
        self::$callback = null;
    }
}
