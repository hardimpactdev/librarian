<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Domains\DomainRepository;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Linting\Rule;

final readonly class DomainLandingPageRule implements GroupedRule
{
    private const string RULE = 'librarian.domain_landing_page';

    public function __construct(
        private DomainRepository $domains,
    ) {}

    public function group(): string
    {
        return 'structure';
    }

    public function check(): array
    {
        $findings = [];

        foreach ($this->domains->all() as $domain) {
            $relativePath = "domains/{$domain->directoryName}/{$domain->landingPageName()}";

            if (! is_file($domain->path.'/'.$domain->landingPageName())) {
                $findings[] = new Finding(
                    path: 'docs/'.$relativePath,
                    line: null,
                    severity: FindingSeverity::Error,
                    rule: self::RULE,
                    message: "Domain landing page [docs/{$relativePath}] is missing.",
                );

                continue;
            }

            $content = file_get_contents($domain->path.'/'.$domain->landingPageName()) ?: '';
            $headings = $this->headings($content);
            $expected = [
                ['level' => 1, 'text' => $domain->title()],
                ['level' => 2, 'text' => 'Purpose'],
                ['level' => 2, 'text' => 'Responsibilities'],
                ['level' => 2, 'text' => 'Boundaries'],
            ];

            if (! $this->matchesRequiredSequence($headings, $expected)) {
                $findings[] = new Finding(
                    path: 'docs/'.$relativePath,
                    line: $this->firstDifferenceLine($headings, $expected),
                    severity: FindingSeverity::Error,
                    rule: self::RULE,
                    message: sprintf(
                        'Expected heading sequence: %s.',
                        implode(' -> ', array_map(static fn (array $heading): string => str_repeat('#', $heading['level']).' '.$heading['text'], $expected)),
                    ),
                );

                continue;
            }

            foreach ([1, 2, 3] as $headingIndex) {
                $heading = $headings[$headingIndex];

                if (! $this->sectionIsEmpty($content, $headings, $headingIndex)) {
                    continue;
                }

                $findings[] = new Finding(
                    path: 'docs/'.$relativePath,
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
     * @return list<array{level: int, text: string, line: int}>
     */
    private function headings(string $content): array
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
     * @param  list<array{level: int, text: string, line: int}>  $actual
     * @param  list<array{level: int, text: string}>  $expected
     */
    private function matchesRequiredSequence(array $actual, array $expected): bool
    {
        if (count($actual) < count($expected)) {
            return false;
        }

        foreach ($expected as $index => $heading) {
            if ($actual[$index]['level'] !== $heading['level']) {
                return false;
            }

            if ($actual[$index]['text'] !== $heading['text']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{level: int, text: string, line: int}>  $actual
     * @param  list<array{level: int, text: string}>  $expected
     */
    private function firstDifferenceLine(array $actual, array $expected): ?int
    {
        foreach ($expected as $index => $heading) {
            $actualHeading = $actual[$index] ?? null;

            if ($actualHeading === null) {
                return null;
            }

            if ($actualHeading['level'] !== $heading['level'] || $actualHeading['text'] !== $heading['text']) {
                return $actualHeading['line'];
            }
        }

        return null;
    }

    /**
     * @param  list<array{level: int, text: string, line: int}>  $headings
     */
    private function sectionIsEmpty(string $content, array $headings, int $headingIndex): bool
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $startLine = ($headings[$headingIndex]['line'] ?? 0) + 1;
        $endLine = ($headings[$headingIndex + 1]['line'] ?? (count($lines) + 1)) - 1;

        for ($line = $startLine; $line <= $endLine; $line++) {
            if (trim($lines[$line - 1] ?? '') !== '') {
                return false;
            }
        }

        return true;
    }
}
