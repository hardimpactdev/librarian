<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Markdown;

use FilesystemIterator;
use HardImpact\Librarian\Docs\DocsFilesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final readonly class MarkdownLinkRewriter
{
    public function __construct(
        private DocsFilesystem $filesystem,
    ) {}

    /**
     * @param  array<string, string>  $renames
     */
    public function rewrite(string $contents, array $renames): string
    {
        return preg_replace_callback(
            '/(?<!!)\[(?<text>[^\]]+)\]\((?<target>[^)]+)\)/',
            static function (array $matches) use ($renames): string {
                $target = $matches['target'];

                if (self::shouldSkipTarget($target)) {
                    return $matches[0];
                }

                foreach ($renames as $from => $to) {
                    $target = str_replace("/domains/{$from}/", "/domains/{$to}/", $target);
                    $target = str_replace("domains/{$from}/", "domains/{$to}/", $target);
                }

                return sprintf('[%s](%s)', $matches['text'], $target);
            },
            $contents,
        ) ?? $contents;
    }

    private static function shouldSkipTarget(string $target): bool
    {
        return str_starts_with($target, '#')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) === 1;
    }

    /**
     * @param  array<string, string>  $renames
     */
    public function rewriteDocsLinks(array $renames): void
    {
        if ($renames === []) {
            return;
        }

        $docsPath = $this->filesystem->docsPath();

        if (! is_dir($docsPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($docsPath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if (! $item->isFile() || $item->getExtension() !== 'md') {
                continue;
            }

            $path = $item->getPathname();
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException("Unable to read markdown file [{$path}].");
            }

            $rewritten = $this->rewrite($contents, $renames);

            if ($rewritten === $contents) {
                continue;
            }

            if (file_put_contents($path, $rewritten) === false) {
                throw new RuntimeException("Unable to write markdown file [{$path}].");
            }
        }
    }
}
