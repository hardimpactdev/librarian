<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Docs\MarkdownSnapshot;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Markdown\ProseSegmenter;

final readonly class DocumentComplexityRule implements GroupedRule
{
    private const string RULE = 'librarian.document_complexity';

    private const array READER_FACING = [
        'max_sentence_words' => 40,
        'max_paragraph_words' => 100,
        'max_bullet_words' => 35,
        'max_lix' => 60.0,
        'max_long_sentence_words' => 25,
        'max_long_sentence_share' => 0.20,
    ];

    private const array TECHNICAL = [
        'max_sentence_words' => 50,
        'max_paragraph_words' => 130,
        'max_bullet_words' => 45,
        'max_lix' => 70.0,
        'max_long_sentence_words' => 30,
        'max_long_sentence_share' => 0.30,
    ];

    /**
     * @param  array{reader_facing?: array<string, int|float>, technical?: array<string, int|float>}  $options
     */
    public function __construct(
        private MarkdownSnapshot $snapshot,
        private array $options = [],
    ) {}

    public function group(): string
    {
        return 'complexity';
    }

    public function check(): array
    {
        $findings = [];

        foreach ($this->snapshot->capture() as $relativePath => $contents) {
            $thresholds = $this->thresholdsFor($relativePath);

            array_push(
                $findings,
                ...$this->duplicateHeadingFindings($relativePath, $contents),
                ...$this->proseFindings($relativePath, $contents, $thresholds),
            );
        }

        return $findings;
    }

    /**
     * @return array{max_sentence_words: int, max_paragraph_words: int, max_bullet_words: int, max_lix: float, max_long_sentence_words: int, max_long_sentence_share: float}
     */
    private function thresholdsFor(string $relativePath): array
    {
        $profile = str_contains($relativePath, '/technical/') ? 'technical' : 'reader_facing';
        $thresholds = $profile === 'technical' ? self::TECHNICAL : self::READER_FACING;

        if (! isset($this->options[$profile])) {
            return $thresholds;
        }

        return $this->mergeThresholds($thresholds, $this->options[$profile]);
    }

    /**
     * @param  array{max_sentence_words: int, max_paragraph_words: int, max_bullet_words: int, max_lix: float, max_long_sentence_words: int, max_long_sentence_share: float}  $thresholds
     * @param  array<string, int|float>  $overrides
     * @return array{max_sentence_words: int, max_paragraph_words: int, max_bullet_words: int, max_lix: float, max_long_sentence_words: int, max_long_sentence_share: float}
     */
    private function mergeThresholds(array $thresholds, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $thresholds)) {
                continue;
            }

            $thresholds[$key] = is_float($thresholds[$key])
                ? (float) $value
                : (int) $value;
        }

        return $thresholds;
    }

    /**
     * @return list<Finding>
     */
    private function duplicateHeadingFindings(string $relativePath, string $contents): array
    {
        $findings = [];
        $seen = [];

        foreach (explode("\n", $contents) as $index => $line) {
            if (preg_match('/^(?<level>#{1,6})\s+(?<heading>.+?)\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $heading = trim($matches['heading'], " \t`");
            $key = strtolower($heading);

            if (! isset($seen[$key])) {
                $seen[$key] = true;

                continue;
            }

            $findings[] = $this->warning(
                relativePath: $relativePath,
                line: $index + 1,
                message: "Duplicate heading label `{$heading}` in one document. Rename one heading so LLMs can reference the section unambiguously.",
            );
        }

        return $findings;
    }

    /**
     * @param  array{max_sentence_words: int, max_paragraph_words: int, max_bullet_words: int, max_lix: float, max_long_sentence_words: int, max_long_sentence_share: float}  $thresholds
     * @return list<Finding>
     */
    private function proseFindings(string $relativePath, string $contents, array $thresholds): array
    {
        $findings = [];
        $paragraphs = ProseSegmenter::segment($contents);
        $sentenceLengths = [];
        $wordCount = 0;
        $longWordCount = 0;

        foreach ($paragraphs as $paragraph) {
            if ($paragraph['kind'] === ProseSegmenter::KIND_BULLET) {
                $words = ProseSegmenter::words($paragraph['text']);

                if (count($words) > $thresholds['max_bullet_words']) {
                    $findings[] = $this->warning(
                        relativePath: $relativePath,
                        line: $paragraph['line'],
                        message: sprintf('Bullet item has %d prose words, above threshold %d. Split condition, actor, action, and result.', count($words), $thresholds['max_bullet_words']),
                    );
                }

                continue;
            }

            $paragraphWords = ProseSegmenter::words($paragraph['text']);

            if (count($paragraphWords) > $thresholds['max_paragraph_words']) {
                $findings[] = $this->warning(
                    relativePath: $relativePath,
                    line: $paragraph['line'],
                    message: sprintf('Paragraph has %d prose words, above threshold %d. Split the paragraph.', count($paragraphWords), $thresholds['max_paragraph_words']),
                );
            }

            foreach (ProseSegmenter::sentences($paragraph['text']) as $sentence) {
                $words = ProseSegmenter::words($sentence);

                if ($words === []) {
                    continue;
                }

                $sentenceLengths[] = count($words);
                $wordCount += count($words);
                $longWordCount += count(array_filter($words, static fn (string $word): bool => strlen($word) > 6));

                if (count($words) > $thresholds['max_sentence_words']) {
                    $findings[] = $this->warning(
                        relativePath: $relativePath,
                        line: $paragraph['line'],
                        message: sprintf('Sentence has %d prose words, above threshold %d. Split condition, actor, action, and result.', count($words), $thresholds['max_sentence_words']),
                    );
                }
            }
        }

        if ($wordCount === 0 || $sentenceLengths === []) {
            return $findings;
        }

        $lix = (float) (array_sum($sentenceLengths) / count($sentenceLengths)) + (($longWordCount * 100) / $wordCount);

        if ($lix > $thresholds['max_lix']) {
            $findings[] = $this->warning(
                relativePath: $relativePath,
                line: 1,
                message: sprintf('Document LIX is %.1f, above threshold %.1f. Prefer shorter sentences and simpler prose words.', $lix, $thresholds['max_lix']),
            );
        }

        $longSentences = count(array_filter(
            $sentenceLengths,
            static fn (int $length): bool => $length > $thresholds['max_long_sentence_words'],
        ));
        $longSentenceShare = $longSentences / count($sentenceLengths);

        if (count($sentenceLengths) >= 4 && $longSentenceShare > $thresholds['max_long_sentence_share']) {
            $findings[] = $this->warning(
                relativePath: $relativePath,
                line: 1,
                message: sprintf(
                    '%.0f%% of sentences exceed %d prose words, above threshold %.0f%%. Split dense requirement prose.',
                    $longSentenceShare * 100,
                    $thresholds['max_long_sentence_words'],
                    $thresholds['max_long_sentence_share'] * 100,
                ),
            );
        }

        return $findings;
    }

    private function warning(string $relativePath, int $line, string $message): Finding
    {
        return new Finding(
            path: "docs/{$relativePath}",
            line: $line,
            severity: FindingSeverity::Warning,
            rule: self::RULE,
            message: $message,
        );
    }
}
