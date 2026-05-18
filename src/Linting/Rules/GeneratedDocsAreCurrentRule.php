<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Generation\GeneratedDocs;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Linting\Rule;

final readonly class GeneratedDocsAreCurrentRule implements GroupedRule
{
    private const string RULE = 'librarian.generated_docs';

    public function __construct(
        private GeneratedDocs $generatedDocs,
    ) {}

    public function group(): string
    {
        return 'structure';
    }

    public function check(): array
    {
        if ($this->generatedDocs->isCurrent()) {
            return [];
        }

        return [
            new Finding(
                path: 'docs/README.md',
                line: null,
                severity: FindingSeverity::Error,
                rule: self::RULE,
                message: 'Generated docs are stale. Run `php artisan librarian:build`.',
            ),
        ];
    }
}
