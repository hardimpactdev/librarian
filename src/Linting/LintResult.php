<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting;

final readonly class LintResult
{
    /**
     * @param  list<Finding>  $findings
     */
    public function __construct(
        public array $findings,
    ) {}

    public function passed(bool $strict = false): bool
    {
        if ($this->errors() !== []) {
            return false;
        }

        return ! $strict || $this->findings === [];
    }

    /**
     * @return list<Finding>
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (Finding $finding): bool => $finding->severity === FindingSeverity::Error,
        ));
    }

    /**
     * @return list<Finding>
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (Finding $finding): bool => $finding->severity === FindingSeverity::Warning,
        ));
    }
}
