<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Docs\MarkdownSnapshot;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Markdown\DocProfile;

final readonly class LongSectionStructureRule implements GroupedRule
{
    private const string RULE = 'librarian.long_section_structure';

    private const int MIN_PARAGRAPHS = 5;

    private const array DISCOURSE_MARKERS = [
        'If',
        'When',
        'By default',
        'However',
        'Otherwise',
        'Instead',
        'For example',
        'For instance',
        'Sometimes',
        'Once',
        'Before',
        'After',
        'In addition',
        'In other words',
        'Typically',
        'Note that',
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

            foreach ($this->sections($contents) as $section) {
                if (! $this->shouldFlag($section)) {
                    continue;
                }

                $findings[] = new Finding(
                    path: "docs/{$relativePath}",
                    line: $section['line'],
                    severity: FindingSeverity::Warning,
                    rule: self::RULE,
                    message: sprintf(
                        'Section "%s" is %d paragraphs of flat prose with no subheadings, lists, tables, code, or discourse markers (If/When/However/By default/For example). Split with a subheading, surface an example, or rewrite branching behavior with conditional openers.',
                        $section['heading'],
                        $section['paragraphs'],
                    ),
                );
            }
        }

        return $findings;
    }

    /**
     * @return list<array{heading: string, line: int, paragraphs: int, has_subheading: bool, has_list: bool, has_table: bool, has_code: bool, has_marker: bool}>
     */
    private function sections(string $contents): array
    {
        $sections = [];
        $current = null;
        $inFence = false;
        $hadBlank = true;

        foreach (explode("\n", $contents) as $index => $line) {
            $lineNumber = $index + 1;

            if (preg_match('/^\s*```/', $line) === 1) {
                $inFence = ! $inFence;

                if ($current !== null) {
                    $current['has_code'] = true;
                }

                $hadBlank = false;

                continue;
            }

            if ($inFence) {
                if ($current !== null) {
                    $current['has_code'] = true;
                }

                continue;
            }

            if (preg_match('/^(?<level>#{2})\s+(?<heading>.+?)\s*$/', $line, $matches) === 1) {
                if ($current !== null) {
                    $sections[] = $current;
                }

                $current = [
                    'heading' => trim($matches['heading']),
                    'line' => $lineNumber,
                    'paragraphs' => 0,
                    'has_subheading' => false,
                    'has_list' => false,
                    'has_table' => false,
                    'has_code' => false,
                    'has_marker' => false,
                ];
                $hadBlank = true;

                continue;
            }

            if (preg_match('/^#{3,6}\s+/', $line) === 1) {
                if ($current !== null) {
                    $current['has_subheading'] = true;
                }

                $hadBlank = true;

                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^\s*(?:[-*+]|\d+\.)\s+/', $line) === 1) {
                $current['has_list'] = true;
                $hadBlank = false;

                continue;
            }

            if (str_starts_with(trim($line), '|')) {
                $current['has_table'] = true;
                $hadBlank = false;

                continue;
            }

            if (trim($line) === '') {
                $hadBlank = true;

                continue;
            }

            if ($hadBlank) {
                $current['paragraphs']++;
                $hadBlank = false;
            }

            if ($this->startsWithMarker($line)) {
                $current['has_marker'] = true;
            }
        }

        if ($current !== null) {
            $sections[] = $current;
        }

        return $sections;
    }

    /**
     * @param  array{heading: string, line: int, paragraphs: int, has_subheading: bool, has_list: bool, has_table: bool, has_code: bool, has_marker: bool}  $section
     */
    private function shouldFlag(array $section): bool
    {
        if ($section['paragraphs'] < self::MIN_PARAGRAPHS) {
            return false;
        }

        return ! $section['has_subheading']
            && ! $section['has_list']
            && ! $section['has_table']
            && ! $section['has_code']
            && ! $section['has_marker'];
    }

    private function startsWithMarker(string $line): bool
    {
        $trimmed = ltrim($line);

        foreach (self::DISCOURSE_MARKERS as $marker) {
            if (! str_starts_with($trimmed, $marker)) {
                continue;
            }

            $next = substr($trimmed, strlen($marker), 1);

            if ($next === ',' || $next === ' ' || $next === ':' || $next === '') {
                return true;
            }
        }

        return false;
    }
}
