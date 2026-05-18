<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Docs\DocsFilesystem;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Linting\Rule;

final readonly class CoreDocsStructureRule implements GroupedRule
{
    private const string RULE = 'librarian.core_docs_structure';

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

        foreach ($this->requirements() as $path => $headings) {
            $absolutePath = $this->filesystem->docsPath($path);

            if (! is_file($absolutePath)) {
                continue;
            }

            $content = file_get_contents($absolutePath) ?: '';
            $actualHeadings = $this->markdownHeadings($content);
            $requiredHeadingIndexes = $this->requiredHeadingIndexes($actualHeadings, $headings);

            if ($requiredHeadingIndexes === null) {
                $findings[] = new Finding(
                    path: 'docs/'.$path,
                    line: $this->firstDifferenceLine($content, $headings),
                    severity: FindingSeverity::Error,
                    rule: self::RULE,
                    message: sprintf(
                        'Expected heading sequence: %s.',
                        implode(' -> ', array_map(static fn (array $heading): string => str_repeat('#', $heading['level']).' '.$heading['text'], $headings)),
                    ),
                );

                continue;
            }

            if ($path === 'mission.md' && $this->missionSummaryIsEmpty($content, $actualHeadings, $requiredHeadingIndexes)) {
                $findings[] = new Finding(
                    path: 'docs/'.$path,
                    line: 1,
                    severity: FindingSeverity::Error,
                    rule: self::RULE,
                    message: 'Mission must include a summary between the H1 and the Why section.',
                );
            }

            foreach (array_slice($requiredHeadingIndexes, 1) as $offset => $headingIndex) {
                $nextHeadingIndex = $requiredHeadingIndexes[$offset + 2] ?? null;

                if (! $this->sectionIsEmpty($content, $actualHeadings, $headingIndex, $nextHeadingIndex)) {
                    continue;
                }

                $heading = $actualHeadings[$headingIndex];

                $findings[] = new Finding(
                    path: 'docs/'.$path,
                    line: $heading['line'],
                    severity: FindingSeverity::Error,
                    rule: self::RULE,
                    message: "The required section [{$heading['text']}] is empty.",
                );
            }
        }

        return $findings;
    }

    /**
     * @return array<string, list<array{level: int, text: string}>>
     */
    private function requirements(): array
    {
        return [
            'mission.md' => [
                ['level' => 1, 'text' => 'Mission'],
                ['level' => 2, 'text' => 'Why'],
                ['level' => 2, 'text' => 'How'],
                ['level' => 2, 'text' => 'What'],
                ['level' => 2, 'text' => 'Boundaries'],
            ],
            'architecture.md' => [
                ['level' => 1, 'text' => 'Architecture'],
                ['level' => 2, 'text' => 'Components'],
                ['level' => 2, 'text' => 'Relationships'],
                ['level' => 2, 'text' => 'State'],
                ['level' => 2, 'text' => 'Boundaries'],
            ],
            'tech-stack.md' => [
                ['level' => 1, 'text' => 'Tech Stack'],
                ['level' => 2, 'text' => 'Runtime'],
                ['level' => 2, 'text' => 'Frameworks'],
                ['level' => 2, 'text' => 'Storage'],
                ['level' => 2, 'text' => 'Infrastructure'],
            ],
        ];
    }

    /**
     * @return list<array{level: int, text: string, line: int}>
     */
    private function markdownHeadings(string $content): array
    {
        $headings = [];

        foreach (preg_split('/\R/', $content) ?: [] as $index => $line) {
            if (! preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/u', $line, $matches)) {
                continue;
            }

            $headings[] = [
                'level' => strlen($matches[1]),
                'text' => trim($matches[2]),
                'line' => $index + 1,
            ];
        }

        return $headings;
    }

    /**
     * @param  list<array{level: int, text: string}>  $expected
     */
    private function firstDifferenceLine(string $content, array $expected): ?int
    {
        $actual = $this->markdownHeadings($content);
        $cursor = 0;

        foreach ($expected as $expectedHeading) {
            while (isset($actual[$cursor])) {
                $actualHeading = $actual[$cursor];

                if ($actualHeading['level'] === $expectedHeading['level'] && $actualHeading['text'] === $expectedHeading['text']) {
                    $cursor++;

                    continue 2;
                }

                $cursor++;
            }

            return $actual[$cursor]['line'] ?? $actual[count($actual) - 1]['line'] ?? null;
        }

        return null;
    }

    /**
     * @param  list<array{level: int, text: string, line: int}>  $actual
     * @param  list<array{level: int, text: string}>  $expected
     */
    private function requiredHeadingIndexes(array $actual, array $expected): ?array
    {
        $indexes = [];
        $cursor = 0;

        foreach ($expected as $heading) {
            while (isset($actual[$cursor])) {
                if ($actual[$cursor]['level'] === $heading['level'] && $actual[$cursor]['text'] === $heading['text']) {
                    $indexes[] = $cursor;
                    $cursor++;

                    continue 2;
                }

                $cursor++;
            }

            return null;
        }

        return $indexes;
    }

    /**
     * @param  list<array{level: int, text: string, line: int}>  $headings
     * @param  list<int>  $requiredHeadingIndexes
     */
    private function missionSummaryIsEmpty(string $content, array $headings, array $requiredHeadingIndexes): bool
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $startLine = ($headings[$requiredHeadingIndexes[0]]['line'] ?? 0) + 1;
        $endLine = ($headings[$requiredHeadingIndexes[1]]['line'] ?? (count($lines) + 1)) - 1;

        for ($line = $startLine; $line <= $endLine; $line++) {
            if (trim($lines[$line - 1] ?? '') !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{level: int, text: string, line: int}>  $headings
     */
    private function sectionIsEmpty(string $content, array $headings, int $headingIndex, ?int $nextHeadingIndex): bool
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $startLine = ($headings[$headingIndex]['line'] ?? 0) + 1;
        $endLine = ($headings[$nextHeadingIndex]['line'] ?? (count($lines) + 1)) - 1;

        for ($line = $startLine; $line <= $endLine; $line++) {
            if (trim($lines[$line - 1] ?? '') !== '') {
                return false;
            }
        }

        return true;
    }
}
