<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use FilesystemIterator;
use HardImpact\Librarian\Docs\DocsFilesystem;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Linting\Rule;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class LocalMarkdownLinksRule implements GroupedRule
{
    private const string RULE = 'librarian.links';

    public function __construct(
        private DocsFilesystem $filesystem,
    ) {}

    public function group(): string
    {
        return 'references';
    }

    public function check(): array
    {
        $docsPath = $this->filesystem->docsPath();

        if (! is_dir($docsPath)) {
            return [];
        }

        $findings = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsPath, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }

            $content = file_get_contents($file->getPathname()) ?: '';

            foreach (preg_split('/\R/', $content) ?: [] as $index => $line) {
                foreach ($this->markdownLinkTargets($line) as $target) {
                    if (! $this->shouldCheckTarget($target)) {
                        continue;
                    }

                    $normalizedTarget = $this->normalizeTarget($target);
                    $resolvedPath = $this->resolveTargetPath($file->getPathname(), $normalizedTarget);

                    if (file_exists($resolvedPath)) {
                        continue;
                    }

                    $findings[] = new Finding(
                        path: 'docs/'.$this->filesystem->relativePath($file->getPathname()),
                        line: $index + 1,
                        severity: FindingSeverity::Error,
                        rule: self::RULE,
                        message: "Local markdown link target [{$normalizedTarget}] does not exist.",
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function markdownLinkTargets(string $line): array
    {
        $targets = [];
        $offset = 0;

        while (($start = strpos($line, '[', $offset)) !== false) {
            if ($start > 0 && $line[$start - 1] === '!') {
                $offset = $start + 1;

                continue;
            }

            $labelEnd = strpos($line, '](', $start);

            if ($labelEnd === false) {
                break;
            }

            $destinationStart = $labelEnd + 2;
            $depth = 1;
            $cursor = $destinationStart;

            while (isset($line[$cursor])) {
                if ($line[$cursor] === '(') {
                    $depth++;
                } elseif ($line[$cursor] === ')') {
                    $depth--;

                    if ($depth === 0) {
                        $targets[] = substr($line, $destinationStart, $cursor - $destinationStart);
                        $offset = $cursor + 1;

                        continue 2;
                    }
                }

                $cursor++;
            }

            break;
        }

        return $targets;
    }

    private function shouldCheckTarget(string $target): bool
    {
        if ($target === '' || str_starts_with($target, '#')) {
            return false;
        }

        if (preg_match('/^(?:https?|mailto):/i', $target) === 1) {
            return false;
        }

        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) !== 1;
    }

    private function normalizeTarget(string $target): string
    {
        $target = trim($target);
        $target = preg_replace('/\s+(?:"[^"]*"|\'[^\']*\'|\([^\)]*\))\s*$/', '', $target) ?? $target;
        $target = preg_replace('/#.*$/', '', $target) ?? '';

        return preg_replace('/\?.*$/', '', $target) ?? '';
    }

    private function resolveTargetPath(string $currentFile, string $target): string
    {
        $path = str_starts_with($target, '/')
            ? $this->filesystem->docsPath(ltrim($target, '/'))
            : dirname($currentFile).'/'.$target;

        $path = str_replace('\\', '/', $path);
        $prefix = '';

        if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
            $prefix = substr($path, 0, 3);
            $path = substr($path, 3);
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $path = ltrim($path, '/');
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $prefix.implode('/', $segments);
    }
}
