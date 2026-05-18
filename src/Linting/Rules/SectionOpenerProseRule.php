<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Docs\MarkdownSnapshot;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Markdown\DocProfile;

final readonly class SectionOpenerProseRule implements GroupedRule
{
    private const string RULE = 'librarian.section_opener_prose';

    private const array SELF_DESCRIBING_HEADINGS = [
        'usage',
        'examples',
        'example',
        'signature',
        'related',
        'see also',
        'arguments',
        'arguments and options',
        'options',
        'requirements',
        'flags',
        'next',
        'next steps',
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
            if (DocProfile::fromPath($relativePath) === DocProfile::Technical) {
                continue;
            }

            array_push($findings, ...$this->findingsForFile($relativePath, $contents));
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function findingsForFile(string $relativePath, string $contents): array
    {
        $findings = [];
        $lines = explode("\n", $contents);
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $line = $lines[$index];

            if (preg_match('/^(?<level>#{2,3})\s+(?<heading>.+?)\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $heading = trim($matches['heading']);

            if (in_array(strtolower($heading), self::SELF_DESCRIBING_HEADINGS, true)) {
                continue;
            }

            $next = $this->firstSubstantiveContent($lines, $index + 1);

            if ($next === null) {
                continue;
            }

            if ($next['kind'] === 'prose' || $next['kind'] === 'subheading') {
                continue;
            }

            $findings[] = new Finding(
                path: "docs/{$relativePath}",
                line: $index + 1,
                severity: FindingSeverity::Warning,
                rule: self::RULE,
                message: sprintf(
                    'Section "%s" opens with %s before any prose. Add one prose sentence explaining what this is and when to use it.',
                    $heading,
                    $next['kind'],
                ),
            );
        }

        return $findings;
    }

    /**
     * @param  list<string>  $lines
     * @return array{kind: string, line: int}|null
     */
    private function firstSubstantiveContent(array $lines, int $startIndex): ?array
    {
        $count = count($lines);

        for ($index = $startIndex; $index < $count; $index++) {
            $line = $lines[$index];
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^#{2,6}\s+/', $line) === 1) {
                return ['kind' => 'subheading', 'line' => $index + 1];
            }

            if (preg_match('/^\s*```/', $line) === 1) {
                return ['kind' => 'a code block', 'line' => $index + 1];
            }

            if (str_starts_with($trimmed, '|')) {
                return ['kind' => 'a table', 'line' => $index + 1];
            }

            if (preg_match('/^\s*(?:[-*+]|\d+\.)\s+/', $line) === 1) {
                return ['kind' => 'a list', 'line' => $index + 1];
            }

            if (str_starts_with($trimmed, '>')) {
                return ['kind' => 'prose', 'line' => $index + 1];
            }

            if (preg_match('/^\s*<!--/', $line) === 1) {
                continue;
            }

            return ['kind' => 'prose', 'line' => $index + 1];
        }

        return null;
    }
}
