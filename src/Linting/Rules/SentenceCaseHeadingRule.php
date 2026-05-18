<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Docs\MarkdownSnapshot;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;

final readonly class SentenceCaseHeadingRule implements GroupedRule
{
    private const string RULE = 'librarian.sentence_case_heading';

    private const array FUNCTION_WORDS = [
        'And',
        'Or',
        'The',
        'A',
        'An',
        'To',
        'For',
        'From',
        'With',
        'In',
        'On',
        'Of',
        'As',
        'By',
        'Is',
        'Are',
        'Be',
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

            if ($inFence) {
                continue;
            }

            if (preg_match('/^(?<level>#{2,4})\s+(?<heading>.+?)\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $offenders = $this->capitalizedFunctionWords($matches['heading']);

            if ($offenders === []) {
                continue;
            }

            $heading = trim($matches['heading']);

            $findings[] = new Finding(
                path: "docs/{$relativePath}",
                line: $index + 1,
                severity: FindingSeverity::Warning,
                rule: self::RULE,
                message: sprintf(
                    'Heading "%s" uses title case (capitalized: %s). Prefer sentence case so headings read like sentences.',
                    $heading,
                    implode(', ', $offenders),
                ),
            );
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function capitalizedFunctionWords(string $heading): array
    {
        $tokens = preg_split('/\s+/', trim($heading)) ?: [];
        $offenders = [];

        foreach ($tokens as $position => $token) {
            if ($position === 0) {
                continue;
            }

            if ($this->shouldIgnoreToken($token)) {
                continue;
            }

            if (in_array($token, self::FUNCTION_WORDS, true)) {
                $offenders[] = $token;
            }
        }

        return array_values(array_unique($offenders));
    }

    private function shouldIgnoreToken(string $token): bool
    {
        if ($token === '') {
            return true;
        }

        if (preg_match('/[`\[\]()]/', $token) === 1) {
            return true;
        }

        if (preg_match('/[\/\\\\_]/', $token) === 1) {
            return true;
        }

        if (preg_match('/\d/', $token) === 1) {
            return true;
        }

        if (str_contains($token, '-')) {
            return true;
        }

        $stripped = trim($token, '.,:;!?()');

        if ($stripped === '') {
            return true;
        }

        return ctype_upper($stripped);
    }
}
