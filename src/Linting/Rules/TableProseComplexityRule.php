<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Docs\MarkdownSnapshot;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Markdown\ProseSegmenter;

final readonly class TableProseComplexityRule implements GroupedRule
{
    private const string RULE = 'librarian.table_prose_complexity';

    private const int MAX_CELL_WORDS = 30;

    private const int MAX_CELL_SENTENCES = 3;

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

            if ($inFence) {
                continue;
            }

            if (! ProseSegmenter::isTableLine($line) || ProseSegmenter::isTableSeparator($line)) {
                continue;
            }

            foreach (ProseSegmenter::tableCellTexts($line) as $cell) {
                $words = ProseSegmenter::words($cell);
                $sentences = ProseSegmenter::sentences($cell);

                if (count($words) > self::MAX_CELL_WORDS) {
                    $findings[] = new Finding(
                        path: "docs/{$relativePath}",
                        line: $index + 1,
                        severity: FindingSeverity::Warning,
                        rule: self::RULE,
                        message: sprintf(
                            'Table cell has %d prose words, above threshold %d. Split the cell, move guidance to surrounding prose, or use a nested list.',
                            count($words),
                            self::MAX_CELL_WORDS,
                        ),
                    );
                }

                if (count($sentences) <= self::MAX_CELL_SENTENCES) {
                    continue;
                }

                $findings[] = new Finding(
                    path: "docs/{$relativePath}",
                    line: $index + 1,
                    severity: FindingSeverity::Warning,
                    rule: self::RULE,
                    message: sprintf(
                        'Table cell has %d sentences, above threshold %d. Move long guidance out of the table.',
                        count($sentences),
                        self::MAX_CELL_SENTENCES,
                    ),
                );
            }
        }

        return $findings;
    }
}
