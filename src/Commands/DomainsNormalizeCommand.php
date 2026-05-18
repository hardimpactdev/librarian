<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Commands;

use HardImpact\Librarian\Commands\Concerns\InteractsWithLintOutput;
use HardImpact\Librarian\Docs\MarkdownSnapshot;
use HardImpact\Librarian\Domains\Domain;
use HardImpact\Librarian\Domains\DomainRenumberer;
use HardImpact\Librarian\Domains\DomainRepository;
use HardImpact\Librarian\Generation\GeneratedDocs;
use HardImpact\Librarian\Linting\Linter;
use HardImpact\Librarian\Markdown\MarkdownLinkRewriter;
use Illuminate\Console\Command;
use Throwable;

final class DomainsNormalizeCommand extends Command
{
    use InteractsWithLintOutput;

    protected $signature = 'librarian:domains:normalize';

    protected $description = 'Normalize Librarian domain ordering';

    public function handle(
        DomainRepository $domains,
        DomainRenumberer $renumberer,
        MarkdownLinkRewriter $linkRewriter,
        GeneratedDocs $generatedDocs,
        Linter $linter,
        MarkdownSnapshot $markdownSnapshot,
    ): int {
        $orderedSlugs = array_map(
            static fn (Domain $domain): string => $domain->slug,
            $domains->all(),
        );
        $renames = $renumberer->renamePlan($orderedSlugs, $domains);
        $snapshot = $markdownSnapshot->capture();
        $renamesApplied = false;

        try {
            $renumberer->apply($renames);
            $renamesApplied = $renames !== [];
            $linkRewriter->rewriteDocsLinks($renames);
            $generatedDocs->write();
        } catch (Throwable $exception) {
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

        $result = $linter->lint();

        $this->renderLintResult($result);

        return $result->passed() ? self::SUCCESS : self::FAILURE;
    }
}
