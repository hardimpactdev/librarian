<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use HardImpact\Librarian\Docs\MarkdownSnapshot;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Markdown\ProseSegmenter;

final readonly class CompoundNounStackRule implements GroupedRule
{
    private const string RULE = 'librarian.compound_noun_stack';

    private const int WARN_THRESHOLD = 4;

    private const int MAX_CHAIN_LENGTH = 5;

    private const array STOP_WORDS = [
        'the', 'a', 'an', 'this', 'that', 'these', 'those',
        'each', 'every', 'all', 'any', 'some', 'no', 'both', 'either', 'neither',
        'another', 'such', 'same', 'one', 'two', 'three', 'four', 'five', 'six',
        'many', 'most', 'few', 'several',
        'and', 'or', 'but', 'nor', 'so', 'yet',
        'of', 'in', 'on', 'at', 'by', 'for', 'from', 'to', 'with', 'as', 'into', 'onto',
        'over', 'under', 'above', 'below', 'between', 'through', 'during',
        'against', 'without', 'within', 'across', 'about', 'around',
        'is', 'are', 'was', 'were', 'be', 'been', 'being', 'has', 'have', 'had',
        'will', 'would', 'should', 'may', 'might', 'must', 'can', 'could',
        'do', 'does', 'did', 'not',
        'when', 'where', 'while', 'after', 'before', 'because', 'if', 'unless', 'until',
        'i', 'you', 'we', 'they', 'he', 'she', 'it',
        'its', 'their', 'your', 'our', 'his', 'her', 'my',
        'use', 'uses', 'using', 'used',
        'see', 'sees', 'seeing',
        'turn', 'turns', 'turning', 'turned',
        'make', 'makes', 'making', 'made',
        'run', 'runs', 'running', 'ran',
        'render', 'renders', 'rendering', 'rendered',
        'serve', 'serves', 'serving', 'served',
        'hold', 'holds', 'holding', 'held',
        'flow', 'flows', 'flowing', 'flowed',
        'expose', 'exposes', 'exposing', 'exposed',
        'apply', 'applies', 'applying', 'applied',
        'connect', 'connects', 'connecting', 'connected',
        'store', 'stores', 'storing', 'stored',
        'come', 'comes', 'coming', 'came',
        'know', 'knows', 'knowing', 'knew',
        'go', 'goes', 'going', 'gone', 'went',
        'get', 'gets', 'getting', 'got',
        'give', 'gives', 'giving', 'gave',
        'take', 'takes', 'taking', 'took',
        'fall', 'falls', 'falling', 'fell',
        'live', 'lives', 'living', 'lived',
        'write', 'writes', 'writing', 'wrote', 'written',
        'read', 'reads', 'reading',
        'install', 'installs', 'installing', 'installed',
        'manage', 'manages', 'managing', 'managed',
        'supervise', 'supervises', 'supervising', 'supervised',
        'isolate', 'isolates', 'isolating', 'isolated',
        'split', 'splits', 'splitting',
        'route', 'routes', 'routing', 'routed',
        'stream', 'streams', 'streaming', 'streamed',
        'send', 'sends', 'sending', 'sent',
        'receive', 'receives', 'receiving', 'received',
        'process', 'processes', 'processing', 'processed',
        'handle', 'handles', 'handling', 'handled',
        'create', 'creates', 'creating', 'created',
        'remove', 'removes', 'removing', 'removed',
        'add', 'adds', 'adding', 'added',
        'set', 'sets', 'setting',
        'list', 'lists', 'listing', 'listed',
        'show', 'shows', 'showing', 'shown',
        'allow', 'allows', 'allowing', 'allowed',
        'enable', 'enables', 'enabling', 'enabled',
        'reach', 'reaches', 'reaching', 'reached',
        'mean', 'means', 'meaning', 'meant',
        'leave', 'leaves', 'leaving', 'left',
        'keep', 'keeps', 'keeping', 'kept',
        'find', 'finds', 'finding', 'found',
        'build', 'builds', 'building', 'built',
        'support', 'supports', 'supporting', 'supported',
        'execute', 'executes', 'executing', 'executed',
        'host', 'hosts', 'hosting', 'hosted',
        'mint', 'mints', 'minting', 'minted',
        'verify', 'verifies', 'verifying', 'verified',
        'bootstrap', 'bootstraps', 'bootstrapping', 'bootstrapped',
        'join', 'joins', 'joining', 'joined',
        'mount', 'mounts', 'mounting', 'mounted',
        'restart', 'restarts', 'restarting', 'restarted',
        'depend', 'depends', 'depending', 'depended',
        'belong', 'belongs', 'belonging', 'belonged',
        'short-circuit',
    ];

    private const array ACCEPTED_COMPOUNDS = [
        'php-fpm',
        'wireguard',
        'cloudflare',
        'caddyfile',
        'sqlite',
        'systemd',
        'open-source',
        'end-to-end',
        'in-memory',
        'long-running',
        'server-sent',
        'cross-cutting',
        'opt-in',
        'opt-out',
        'real-time',
        'machine-readable',
        'human-readable',
        'first-party',
        'third-party',
        'read-only',
        'read-write',
        'non-interactive',
        'side-by-side',
        'top-level',
        'low-level',
        'high-level',
        'self-signed',
        'up-to-date',
    ];

    private const array BOUNDARY_CHARS = ['.', ',', ';', ':', '(', ')', '[', ']', '!', '?', '"', "\u{201C}", "\u{201D}", "\u{2014}"];

    /**
     * @param  array{accepted_compounds?: list<string>, warn_threshold?: int, max_chain_length?: int}  $options
     */
    public function __construct(
        private MarkdownSnapshot $snapshot,
        private array $options = [],
    ) {}

    public function group(): string
    {
        return 'prose';
    }

    public function check(): array
    {
        $findings = [];

        foreach ($this->snapshot->capture() as $relativePath => $contents) {
            foreach (ProseSegmenter::segment($contents) as $paragraph) {
                foreach ($this->stacksInText($paragraph['text']) as $stack) {
                    $findings[] = new Finding(
                        path: "docs/{$relativePath}",
                        line: $paragraph['line'],
                        severity: FindingSeverity::Warning,
                        rule: self::RULE,
                        message: sprintf(
                            'Compound noun phrase "%s" stacks %d modifiers before the head noun. Decompose into a sentence ("X that is Y by Z") instead.',
                            $stack['phrase'],
                            $stack['modifiers'],
                        ),
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return list<array{phrase: string, modifiers: int}>
     */
    private function stacksInText(string $text): array
    {
        $stacks = [];

        foreach ($this->splitClauses($text) as $clause) {
            foreach ($this->stacksInClause($clause) as $stack) {
                $stacks[] = $stack;
            }
        }

        return $stacks;
    }

    /**
     * @return list<string>
     */
    private function splitClauses(string $text): array
    {
        $pattern = '/['.preg_quote(implode('', self::BOUNDARY_CHARS), '/').']+/u';
        $clauses = preg_split($pattern, $text) ?: [];

        return array_values(array_filter(array_map('trim', $clauses)));
    }

    /**
     * @return list<array{phrase: string, modifiers: int}>
     */
    private function stacksInClause(string $clause): array
    {
        $tokens = $this->tokenize($clause);
        $stacks = [];

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if (! $this->isInventedHyphenation($token)) {
                continue;
            }

            $chain = [$token];

            for ($next = $index + 1; $next < count($tokens); $next++) {
                $candidate = $tokens[$next];

                if ($this->isStopWord($candidate)) {
                    break;
                }

                $chain[] = $candidate;

                if (count($chain) >= $this->maxChainLength()) {
                    break;
                }
            }

            if (count($chain) >= $this->warnThreshold()) {
                $stacks[] = [
                    'phrase' => implode(' ', $chain),
                    'modifiers' => count($chain) - 1,
                ];
            }

            $index += count($chain) - 1;
        }

        return $stacks;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        preg_match_all('/[A-Za-z][A-Za-z\'-]*/', $text, $matches);

        return $matches[0];
    }

    private function isStopWord(string $token): bool
    {
        return in_array(strtolower($token), self::STOP_WORDS, true);
    }

    private function isInventedHyphenation(string $token): bool
    {
        return str_contains($token, '-') && ! in_array(strtolower($token), $this->acceptedCompounds(), true);
    }

    /**
     * @return list<string>
     */
    private function acceptedCompounds(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $compound): string => strtolower($compound),
            [...self::ACCEPTED_COMPOUNDS, ...($this->options['accepted_compounds'] ?? [])],
        )));
    }

    private function warnThreshold(): int
    {
        return $this->options['warn_threshold'] ?? self::WARN_THRESHOLD;
    }

    private function maxChainLength(): int
    {
        return $this->options['max_chain_length'] ?? self::MAX_CHAIN_LENGTH;
    }
}
