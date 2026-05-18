<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Commands;

use HardImpact\Librarian\Docs\MarkdownSnapshot;
use HardImpact\Librarian\Domains\Domain;
use HardImpact\Librarian\Domains\DomainRenumberer;
use HardImpact\Librarian\Domains\DomainRepository;
use HardImpact\Librarian\Domains\DomainScaffolder;
use HardImpact\Librarian\Generation\GeneratedDocs;
use HardImpact\Librarian\Markdown\MarkdownLinkRewriter;
use Illuminate\Console\Command;
use Throwable;

final class DomainCommand extends Command
{
    protected $signature = 'librarian:domain {slug} {--before=} {--after=}';

    protected $description = 'Create a Librarian documentation domain';

    public function handle(
        DomainRepository $domains,
        DomainScaffolder $scaffolder,
        DomainRenumberer $renumberer,
        MarkdownLinkRewriter $linkRewriter,
        GeneratedDocs $generatedDocs,
        MarkdownSnapshot $markdownSnapshot,
    ): int {
        $slug = (string) $this->argument('slug');
        $before = $this->option('before');
        $after = $this->option('after');

        if ($before !== null && $after !== null) {
            $this->error('The --before and --after options cannot be used together.');

            return self::FAILURE;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
            $this->error('The domain slug must be lowercase kebab-case.');

            return self::FAILURE;
        }

        if ($domains->findBySlug($slug) !== null) {
            $this->error("Domain [{$slug}] already exists.");

            return self::FAILURE;
        }

        $existingDomains = $domains->all();
        $orderedSlugs = array_map(static fn (Domain $domain): string => $domain->slug, $existingDomains);

        if (is_string($before) && ! in_array($before, $orderedSlugs, true)) {
            $this->error("Domain [{$before}] does not exist.");

            return self::FAILURE;
        }

        if (is_string($after) && ! in_array($after, $orderedSlugs, true)) {
            $this->error("Domain [{$after}] does not exist.");

            return self::FAILURE;
        }

        $insertAt = count($orderedSlugs);

        if (is_string($before)) {
            $insertAt = array_search($before, $orderedSlugs, true);
        }

        if (is_string($after)) {
            $insertAt = array_search($after, $orderedSlugs, true) + 1;
        }

        array_splice($orderedSlugs, $insertAt, 0, [$slug]);

        $renames = $renumberer->renamePlan($orderedSlugs, $domains);
        $snapshot = $markdownSnapshot->capture();
        $newDomainDirectory = 'domains/'.($insertAt + 1)."_{$slug}";
        $renamesApplied = false;

        try {
            $renumberer->apply($renames);
            $renamesApplied = $renames !== [];
            $scaffolder->scaffold($slug, $insertAt + 1);
            $linkRewriter->rewriteDocsLinks($renames);
            $generatedDocs->write();
        } catch (Throwable $exception) {
            $this->deleteDirectory($newDomainDirectory);

            $rollbackException = null;

            if ($renamesApplied) {
                try {
                    $renumberer->apply(array_flip($renames));
                } catch (Throwable $rollbackFailure) {
                    $rollbackException = $rollbackFailure;
                }
            }

            $markdownSnapshot->restore($snapshot);

            $this->error(($rollbackException ?? $exception)->getMessage());

            return self::FAILURE;
        }

        $this->line("Domain `{$slug}` created.");
        $this->line('');
        $this->line('Document the domain, then run `php artisan librarian:build` to update generated documentation and check structural consistency.');

        return self::SUCCESS;
    }

    private function deleteDirectory(string $relativePath): void
    {
        $absolutePath = config('librarian.path').'/'.ltrim($relativePath, '/');

        if (! is_dir($absolutePath)) {
            return;
        }

        foreach (scandir($absolutePath) ?: [] as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $path = "{$absolutePath}/{$entry}";

            if (is_dir($path)) {
                $this->deleteDirectory($relativePath.'/'.$entry);

                continue;
            }

            unlink($path);
        }

        rmdir($absolutePath);
    }
}
