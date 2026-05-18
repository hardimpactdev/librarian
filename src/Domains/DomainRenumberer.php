<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Domains;

use HardImpact\Librarian\Docs\DocsFilesystem;
use RuntimeException;

final readonly class DomainRenumberer
{
    public function __construct(
        private DocsFilesystem $filesystem,
    ) {}

    /**
     * @param  array<int, string>  $orderedSlugs
     * @return array<string, string>
     */
    public function renamePlan(array $orderedSlugs, DomainRepository $domains): array
    {
        $existing = [];

        foreach ($domains->all() as $domain) {
            $existing[$domain->slug] = $domain->directoryName;
        }

        $renames = [];

        foreach ($orderedSlugs as $index => $slug) {
            if (! array_key_exists($slug, $existing)) {
                continue;
            }

            $target = sprintf('%d_%s', $index + 1, $slug);

            if ($existing[$slug] === $target) {
                continue;
            }

            $renames[$existing[$slug]] = $target;
        }

        return $renames;
    }

    /**
     * @param  array<string, string>  $renames
     */
    public function apply(array $renames): void
    {
        if ($renames === []) {
            return;
        }

        $token = bin2hex(random_bytes(8));
        $temporaryNames = [];
        $movedSources = [];
        $finalizedTargets = [];

        try {
            foreach ($renames as $from => $to) {
                $source = $this->filesystem->docsPath("domains/{$from}");

                if (! is_dir($source)) {
                    throw new RuntimeException("Missing planned domain directory [{$source}].");
                }

                $temporary = $this->filesystem->docsPath("domains/__tmp__{$token}_{$to}");

                if (file_exists($temporary)) {
                    throw new RuntimeException("Temporary domain directory [{$temporary}] already exists.");
                }

                if (! rename($source, $temporary)) {
                    throw new RuntimeException("Unable to rename domain directory [{$source}] to [{$temporary}].");
                }

                $temporaryNames[$temporary] = $this->filesystem->docsPath("domains/{$to}");
                $movedSources[$temporary] = $source;
            }

            foreach ($temporaryNames as $temporary => $target) {
                if (! rename($temporary, $target)) {
                    throw new RuntimeException("Unable to rename domain directory [{$temporary}] to [{$target}].");
                }

                $finalizedTargets[$target] = $movedSources[$temporary];
            }
        } catch (\Throwable $exception) {
            $this->restoreMovedDirectories($temporaryNames, $movedSources, $finalizedTargets, $token);

            throw $exception;
        }
    }

    /**
     * @param  array<string, string>  $temporaryNames
     * @param  array<string, string>  $movedSources
     * @param  array<string, string>  $finalizedTargets
     */
    private function restoreMovedDirectories(array $temporaryNames, array $movedSources, array $finalizedTargets, string $token): void
    {
        $rollbackNames = [];

        foreach ($finalizedTargets as $target => $original) {
            if (! is_dir($target)) {
                continue;
            }

            $rollback = $this->filesystem->docsPath('domains/__rollback__'.$token.'_'.basename($original));

            if (file_exists($rollback)) {
                throw new RuntimeException("Rollback domain directory [{$rollback}] already exists.");
            }

            if (! rename($target, $rollback)) {
                throw new RuntimeException("Unable to prepare domain directory [{$target}] for rollback.");
            }

            $rollbackNames[$rollback] = $original;
        }

        foreach (array_keys($temporaryNames) as $temporary) {
            if (! is_dir($temporary)) {
                continue;
            }

            $original = $movedSources[$temporary] ?? null;

            if ($original === null) {
                continue;
            }

            if (! rename($temporary, $original)) {
                throw new RuntimeException("Unable to restore domain directory [{$temporary}] to [{$original}].");
            }
        }

        foreach ($rollbackNames as $rollback => $original) {
            if (! rename($rollback, $original)) {
                throw new RuntimeException("Unable to restore domain directory [{$rollback}] to [{$original}].");
            }
        }
    }
}
