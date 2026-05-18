<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Commands\Concerns;

use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\LintResult;

trait InteractsWithLintOutput
{
    protected function renderLintResult(LintResult $result): void
    {
        if ($result->findings === []) {
            $this->line('Lint passed.');

            return;
        }

        foreach ($result->findings as $finding) {
            $this->line($this->formatFinding($finding));
        }

        $this->newLine();
        $this->line(sprintf(
            '%d Librarian lint issue(s) found: %d error(s), %d warning(s).',
            count($result->findings),
            count($result->errors()),
            count($result->warnings()),
        ));
    }

    private function formatFinding(Finding $finding): string
    {
        $location = $finding->line === null ? $finding->path : "{$finding->path}:{$finding->line}";

        return "[{$finding->severity->value}] {$finding->rule} {$location} {$finding->message}";
    }
}
