<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Commands;

use HardImpact\Librarian\Commands\Concerns\InteractsWithLintOutput;
use HardImpact\Librarian\Generation\GeneratedDocs;
use HardImpact\Librarian\Linting\Linter;
use Illuminate\Console\Command;

final class BuildCommand extends Command
{
    use InteractsWithLintOutput;

    protected $signature = 'librarian:build';

    protected $description = 'Generate Librarian docs and lint them';

    public function handle(GeneratedDocs $generatedDocs, Linter $linter): int
    {
        $generatedDocs->write();

        $result = $linter->lint();

        $this->renderLintResult($result);

        return $result->passed() ? self::SUCCESS : self::FAILURE;
    }
}
