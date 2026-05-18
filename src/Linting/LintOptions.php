<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting;

final readonly class LintOptions
{
    public const array GROUPS = [
        'structure',
        'contracts',
        'references',
        'complexity',
        'prose',
    ];

    public function __construct(
        public ?string $path = null,
        public ?string $group = null,
    ) {}

    public static function supportsGroup(string $group): bool
    {
        return in_array($group, self::GROUPS, true);
    }

    public static function supportedGroups(): string
    {
        return implode(', ', self::GROUPS);
    }

    public function includesRule(Rule $rule): bool
    {
        if ($this->group === null) {
            return true;
        }

        return $rule instanceof GroupedRule && $rule->group() === $this->group;
    }

    public function includesFinding(Finding $finding): bool
    {
        if ($this->path === null) {
            return true;
        }

        return $finding->path === $this->path || str_starts_with($finding->path, "{$this->path}/");
    }
}
