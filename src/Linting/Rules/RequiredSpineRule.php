<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Docs\DocsFilesystem;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Linting\Rule;

final readonly class RequiredSpineRule implements GroupedRule
{
    private const string RULE = 'librarian.spine';

    public function __construct(
        private DocsFilesystem $filesystem,
    ) {}

    public function group(): string
    {
        return 'structure';
    }

    public function check(): array
    {
        $findings = [];

        foreach ([
            'README.md' => false,
            'mission.md' => false,
            'architecture.md' => false,
            'tech-stack.md' => false,
            'concepts.md' => false,
            'domains' => true,
        ] as $path => $directory) {
            $absolutePath = $this->filesystem->docsPath($path);
            $exists = $directory ? is_dir($absolutePath) : is_file($absolutePath);

            if ($exists) {
                continue;
            }

            $findings[] = new Finding(
                path: 'docs/'.$path,
                line: null,
                severity: FindingSeverity::Error,
                rule: self::RULE,
                message: "Required path [docs/{$path}] is missing.",
            );
        }

        return $findings;
    }
}
