<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Docs\MarkdownSnapshot;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Markdown\ProseSegmenter;

final readonly class RequirementSmellRule implements GroupedRule
{
    private const string RULE = 'librarian.requirement_smell';

    private const array PHRASES = [
        'appropriate',
        'reasonable',
        'sufficient',
        'proper',
        'relevant',
        'as needed',
        'if possible',
        'where applicable',
        'etc.',
        'and/or',
    ];

    public function __construct(
        private MarkdownSnapshot $snapshot,
    ) {}

    public function group(): string
    {
        return 'prose';
    }

    public function check(): array
    {
        $findings = [];

        foreach ($this->snapshot->capture() as $relativePath => $contents) {
            array_push($findings, ...$this->fileFindings($relativePath, $contents));
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function fileFindings(string $relativePath, string $contents): array
    {
        $findings = [];
        $inFence = false;

        foreach (explode("\n", $contents) as $index => $line) {
            if (preg_match('/^\s*```/', $line) === 1) {
                $inFence = ! $inFence;

                continue;
            }

            if ($inFence || $this->isIgnoredLine($line)) {
                continue;
            }

            $prose = ProseSegmenter::cleanProse($line);

            if ($prose === '') {
                continue;
            }

            foreach (self::PHRASES as $phrase) {
                if (! $this->containsPhrase($prose, $phrase)) {
                    continue;
                }

                $findings[] = new Finding(
                    path: "docs/{$relativePath}",
                    line: $index + 1,
                    severity: FindingSeverity::Warning,
                    rule: self::RULE,
                    message: "Ambiguous phrase `{$phrase}`. Name the actor, condition, obligation, and observable result.",
                );
            }
        }

        return $findings;
    }

    private function isIgnoredLine(string $line): bool
    {
        $trimmed = trim($line);

        return $trimmed === ''
            || str_starts_with($trimmed, '#')
            || str_starts_with($trimmed, '|')
            || preg_match('/^\s*\|?\s*-{3,}/', $line) === 1;
    }

    private function containsPhrase(string $prose, string $phrase): bool
    {
        if ($phrase === 'etc.') {
            return preg_match('/\betc\./i', $prose) === 1;
        }

        if ($phrase === 'and/or') {
            return str_contains(strtolower($prose), 'and/or');
        }

        return preg_match('/\b'.preg_quote($phrase, '/').'\b/i', $prose) === 1;
    }
}
