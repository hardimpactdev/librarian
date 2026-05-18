<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Docs\MarkdownSnapshot;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Markdown\DocProfile;
use HardImpact\Librarian\Markdown\ProseSegmenter;

final readonly class BulletComplexityRule implements GroupedRule
{
    private const string RULE = 'librarian.bullet_complexity';

    private const int MULTI_CLAUSE_WORD_THRESHOLD = 25;

    private const int MULTI_CLAUSE_SEPARATOR_THRESHOLD = 2;

    private const int MAX_CONSECUTIVE_BULLETS = 8;

    private const array CONDITIONAL_WORDS = ['if', 'when', 'unless', 'whenever', 'while'];

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
            $paragraphs = ProseSegmenter::segment($contents);

            array_push($findings, ...$this->bulletFindings($relativePath, $paragraphs));

            if (DocProfile::fromPath($relativePath) !== DocProfile::Technical) {
                array_push($findings, ...$this->consecutiveBulletFindings($relativePath, $paragraphs));
            }
        }

        return $findings;
    }

    /**
     * @param  list<array{kind: string, text: string, line: int}>  $paragraphs
     * @return list<Finding>
     */
    private function bulletFindings(string $relativePath, array $paragraphs): array
    {
        $findings = [];

        foreach ($paragraphs as $paragraph) {
            if ($paragraph['kind'] !== ProseSegmenter::KIND_BULLET) {
                continue;
            }

            $wordCount = count(ProseSegmenter::words($paragraph['text']));
            $separatorCount = substr_count($paragraph['text'], ',') + substr_count($paragraph['text'], ';');
            $hasConditional = $this->containsConditional($paragraph['text']);

            $multiClause = $wordCount >= self::MULTI_CLAUSE_WORD_THRESHOLD
                && $separatorCount >= self::MULTI_CLAUSE_SEPARATOR_THRESHOLD;

            if (! $multiClause && ! $hasConditional) {
                continue;
            }

            if ($hasConditional && $wordCount < 15) {
                continue;
            }

            $reason = $multiClause
                ? sprintf('%d words with %d clause separators', $wordCount, $separatorCount)
                : sprintf('contains an embedded conditional in %d words', $wordCount);

            $findings[] = new Finding(
                path: "docs/{$relativePath}",
                line: $paragraph['line'],
                severity: FindingSeverity::Warning,
                rule: self::RULE,
                message: "Bullet is multi-clause ({$reason}). Split into separate bullets or rewrite as prose with explicit subordination.",
            );
        }

        return $findings;
    }

    /**
     * @param  list<array{kind: string, text: string, line: int}>  $paragraphs
     * @return list<Finding>
     */
    private function consecutiveBulletFindings(string $relativePath, array $paragraphs): array
    {
        $findings = [];
        $streak = 0;
        $streakLine = null;

        foreach ($paragraphs as $paragraph) {
            if ($paragraph['kind'] !== ProseSegmenter::KIND_BULLET) {
                $streak = 0;
                $streakLine = null;

                continue;
            }

            $streak++;

            if ($streakLine === null) {
                $streakLine = $paragraph['line'];
            }

            if ($streak !== self::MAX_CONSECUTIVE_BULLETS + 1) {
                continue;
            }

            $findings[] = new Finding(
                path: "docs/{$relativePath}",
                line: $streakLine,
                severity: FindingSeverity::Warning,
                rule: self::RULE,
                message: sprintf(
                    'More than %d consecutive bullets without intervening prose or a subheading. Break the list with subheadings or convert to a table.',
                    self::MAX_CONSECUTIVE_BULLETS,
                ),
            );
        }

        return $findings;
    }

    private function containsConditional(string $text): bool
    {
        $tokens = preg_split('/\s+/', strtolower($text)) ?: [];
        $cleaned = array_map(static fn (string $token): string => trim($token, " \t.,;:()`"), $tokens);

        foreach ($cleaned as $position => $token) {
            if (! in_array($token, self::CONDITIONAL_WORDS, true)) {
                continue;
            }

            if ($position === 0) {
                continue;
            }

            return true;
        }

        return false;
    }
}
